<?php

declare(strict_types=1);

namespace Complaints\Tests\Application;

use Complaints\Application\AppComplaint\ComplaintAction;
use Complaints\Application\AppComplaint\UseCase\AddComplaintAttachment;
use Complaints\Application\AppComplaint\UseCase\ChangeDraftLot;
use Complaints\Application\AppComplaint\UseCase\CreateComplaintDraft;
use Complaints\Application\AppComplaint\UseCase\CreateComplaintDraftCommand;
use Complaints\Application\AppComplaint\UseCase\DeleteComplaint;
use Complaints\Application\AppComplaint\UseCase\FindComplainableBuy;
use Complaints\Application\AppComplaint\UseCase\ListOrganizationComplaints;
use Complaints\Application\AppComplaint\UseCase\ReturnComplaintToDraft;
use Complaints\Application\AppComplaint\UseCase\SendComplaintForSignature;
use Complaints\Application\AppComplaint\UseCase\ShowComplaint;
use Complaints\Application\AppComplaint\UseCase\SignComplaint;
use Complaints\Application\AppComplaint\UseCase\SubmitComplaint;
use Complaints\Application\AppComplaint\UseCase\UpdateComplaintDraft;
use Complaints\Application\AppComplaint\UseCase\UpdateComplaintDraftCommand;
use Complaints\Application\AppComplaint\UseCase\WithdrawComplaint;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\SignatureRejected;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\NotificationType;
use Complaints\Application\Service\SignatureService;
use Complaints\Application\Shared\UploadedFile;
use Complaints\Domain\AppComplaint\AppComplaintStatus as Status;
use Complaints\Domain\Document\DocumentType;
use Complaints\Domain\Signature\SignatureStatus;
use Complaints\Tests\Support\World;
use PHPUnit\Framework\TestCase;

final class ComplaintLifecycleTest extends TestCase
{
    private World $world;

    protected function setUp(): void
    {
        $this->world = new World();
    }

    public function testStepOneFindsOnlyLotsOfOwnApplicationWithOpenAppeal(): void
    {
        $this->world->actAs(World::AUTHOR);

        $buy = $this->world->get(FindComplainableBuy::class)->execute(' 125407-1 ');

        self::assertSame([World::APP_LOT_A, World::APP_LOT_B], array_map(static fn ($lot): int => $lot->appLot->id, $buy->lots));
    }

    public function testStepOneRejectsClosedAppeal(): void
    {
        $this->world->actAs(World::AUTHOR);
        $this->world->at('2026-10-20 00:00:00+05:00');

        $this->expectExceptionObject(ValidationFailed::field('buy_number', 'complaints_error_appeal_closed'));
        $this->world->get(FindComplainableBuy::class)->execute('125407-1');
    }

    public function testHappyPathFromDraftToSubmission(): void
    {
        $id = $this->world->draft();
        $this->world->get(SendComplaintForSignature::class)->execute($id);

        $document = $this->world->documents->current(DocumentType::AppComplaint, $id);
        self::assertNotNull($document);
        self::assertSame(Status::AwaitingSignature, $this->world->complaints->get($id)->status);
        self::assertSame([World::HEAD], $this->world->notifier->recipientsOf(NotificationType::ComplaintAwaitingSignature->value));

        $this->world->actAs(World::HEAD);
        $this->world->get(SignComplaint::class)->execute($id, base64_encode('cms'));
        self::assertSame(Status::Signed, $this->world->complaints->get($id)->status);
        self::assertSame(
            [SignatureStatus::Signed],
            array_map(static fn ($s) => $s->status, $this->world->get(SignatureService::class)->signatures($document)),
        );
        self::assertCount(1, $this->world->fileSignatures->of($document->fileId));

        $this->world->get(SubmitComplaint::class)->execute($id);
        $complaint = $this->world->complaints->get($id);
        self::assertSame(Status::Submitted, $complaint->status);
        self::assertNotNull($complaint->submittedAt);
        self::assertSame(World::HEAD, $complaint->submittedByUserId);
    }

    public function testSendingRequiresPhoneAndDescription(): void
    {
        $this->world->actAs(World::AUTHOR);
        $id = $this->world->get(CreateComplaintDraft::class)->execute(new CreateComplaintDraftCommand(World::BUY, World::APP_LOT_A, World::HEAD));

        try {
            $this->world->get(SendComplaintForSignature::class)->execute($id);
            self::fail('Ожидалась ошибка проверки.');
        } catch (ValidationFailed $error) {
            self::assertSame(['contact_phone', 'description'], array_keys($error->errors()));
        }
        self::assertSame(Status::Draft, $this->world->complaints->get($id)->status);
    }

    public function testDescriptionLimitIsFiveHundredCharacters(): void
    {
        $id = $this->world->draft();

        $this->expectException(ValidationFailed::class);
        $this->world->get(UpdateComplaintDraft::class)->execute(new UpdateComplaintDraftCommand($id, '+7 701 000 00 00', str_repeat('ж', 501)));
    }

    public function testOnlyChosenHeadSignsAndCertificateMustBeHis(): void
    {
        $id = $this->world->draft();
        $this->world->get(SendComplaintForSignature::class)->execute($id);

        $this->world->actAs(World::OTHER_HEAD);
        try {
            $this->world->get(SignComplaint::class)->execute($id, base64_encode('cms'));
            self::fail('Подписать может только выбранный руководитель.');
        } catch (AccessDenied) {
        }

        $this->world->actAs(World::HEAD);
        $this->world->verifier->signingUserId = World::OTHER_HEAD;
        $this->expectExceptionObject(new SignatureRejected('complaints_error_signature_iin'));
        $this->world->get(SignComplaint::class)->execute($id, base64_encode('cms'));
    }

    public function testSubmissionAfterAppealEndIsRejected(): void
    {
        $id = $this->world->signed();
        $this->world->at('2026-10-20 00:00:00+05:00');

        $this->expectException(ValidationFailed::class);
        $this->world->get(SubmitComplaint::class)->execute($id);
    }

    public function testWithdrawnComplaintIsResubmittedWithSameSignature(): void
    {
        $id = $this->world->submitted();
        $this->world->get(WithdrawComplaint::class)->execute($id);
        self::assertSame(Status::Withdrawn, $this->world->complaints->get($id)->status);

        $this->world->get(SubmitComplaint::class)->execute($id);

        self::assertSame(Status::Submitted, $this->world->complaints->get($id)->status);
        self::assertCount(1, array_filter($this->world->documents->documents, static fn ($d): bool => $d->ownerId === $id));
    }

    public function testReturningToDraftRetiresSignedVersion(): void
    {
        $id = $this->world->signed();
        $document = $this->world->documents->current(DocumentType::AppComplaint, $id);

        $this->world->get(ReturnComplaintToDraft::class)->execute($id);

        self::assertSame(Status::Draft, $this->world->complaints->get($id)->status);
        self::assertNull($this->world->documents->current(DocumentType::AppComplaint, $id));
        self::assertFalse($this->world->documents->get($document->id)->isCurrent);

        $this->world->get(SendComplaintForSignature::class)->execute($id);
        $resent = $this->world->documents->current(DocumentType::AppComplaint, $id);
        self::assertSame(2, $resent?->version);
        self::assertSame(
            [SignatureStatus::Pending],
            array_map(static fn ($s) => $s->status, $this->world->get(SignatureService::class)->signatures($resent)),
        );
    }

    public function testOneActiveComplaintPerApplicationLot(): void
    {
        $this->world->draft();

        $this->expectExceptionObject(ValidationFailed::field('app_lot_id', 'complaints_error_duplicate_complaint'));
        $this->world->draft();
    }

    public function testLotOfAnotherOrganizationCannotBeChosen(): void
    {
        $this->world->actAs(World::AUTHOR);

        $this->expectExceptionObject(ValidationFailed::field('app_lot_id', 'complaints_error_lot_not_complainable'));
        $this->world->get(CreateComplaintDraft::class)->execute(new CreateComplaintDraftCommand(World::BUY, World::APP2_LOT_A, World::HEAD));
    }

    public function testDraftLotCanBeChangedWithinTheSameApplication(): void
    {
        $id = $this->world->draft();

        $this->world->get(ChangeDraftLot::class)->execute($id, World::APP_LOT_B);

        self::assertSame(World::LOT_B, $this->world->complaints->lotOf($id)->buyLotId);
        self::assertCount(1, $this->world->complaints->lots);
    }

    public function testSubmittedComplaintCannotBeDeletedButDraftCan(): void
    {
        $submitted = $this->world->submitted();
        try {
            $this->world->get(DeleteComplaint::class)->execute($submitted);
            self::fail('Поданную жалобу удалить нельзя.');
        } catch (StatusConflict) {
        }

        $draft = $this->world->draft(appLotId: World::APP_LOT_B);
        $this->world->get(DeleteComplaint::class)->execute($draft);
        self::assertNotNull($this->world->complaints->complaints[$draft]['deleted_at']);
    }

    public function testConcurrentStatusChangeIsAConflict(): void
    {
        $id = $this->world->signed();
        $this->world->complaints->complaints[$id]['status'] = Status::Draft;

        $this->expectException(StatusConflict::class);
        $this->world->get(SubmitComplaint::class)->execute($id);
    }

    public function testAttachmentRulesAndRegistryActions(): void
    {
        $id = $this->world->draft();
        $this->world->get(AddComplaintAttachment::class)->execute($id, new UploadedFile('РУ.pdf', 'application/pdf', 'bytes'));

        try {
            $this->world->get(AddComplaintAttachment::class)->execute($id, new UploadedFile('script.exe', 'application/octet-stream', 'x'));
            self::fail('Тип файла должен проверяться.');
        } catch (ValidationFailed $error) {
            self::assertSame('complaints_error_file_type', $error->messageKey());
        }

        $registry = $this->world->get(ListOrganizationComplaints::class)->execute(1);
        self::assertSame(1, $registry->total);
        self::assertSame([ComplaintAction::Edit, ComplaintAction::Delete], $registry->items[0]->actions);

        $card = $this->world->get(ShowComplaint::class)->execute($id);
        self::assertCount(1, $card->attachments);
        self::assertSame('ТОО «Поставщик-1»', $card->requisites->supplierName->ru);
    }
}
