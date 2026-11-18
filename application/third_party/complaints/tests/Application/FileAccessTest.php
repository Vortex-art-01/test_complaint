<?php

declare(strict_types=1);

namespace Complaints\Tests\Application;

use Complaints\Application\AppComplaint\UseCase\SendComplaintForSignature;
use Complaints\Application\AppComplaint\UseCase\ShowComplaint;
use Complaints\Application\AppComplaint\UseCase\SignComplaint;
use Complaints\Application\BuyComplaint\UseCase\FormConsolidatedDocument;
use Complaints\Application\BuyComplaint\UseCase\SendDecisionCommand;
use Complaints\Application\BuyComplaint\UseCase\SendDecisionForApproval;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\File\FileKind;
use Complaints\Application\File\UseCase\DownloadFile;
use Complaints\Application\File\UseCase\GetSigningSource;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Document\DocumentType;
use Complaints\Tests\Support\ReviewSteps;
use Complaints\Tests\Support\World;
use PHPUnit\Framework\TestCase;

/**
 * Файлы модуля выдаются по правам на жалобу или решение, а не по ссылке хранилища.
 */
final class FileAccessTest extends TestCase
{
    private World $world;
    private ReviewSteps $steps;

    protected function setUp(): void
    {
        $this->world = new World();
        $this->steps = new ReviewSteps($this->world);
    }

    private function canDownload(int $userId, FileKind $kind, int $id): bool
    {
        $this->world->actAs($userId);
        try {
            $this->world->get(DownloadFile::class)->execute($kind, $id);

            return true;
        } catch (AccessDenied) {
            return false;
        }
    }

    private function documentOf(DocumentType $type, int $ownerId): int
    {
        return $this->world->documents->current($type, $ownerId)?->id ?? self::fail('Нет документа ' . $type->value);
    }

    public function testComplaintDocumentsBelongToSupplierUntilAuthorityGetsThem(): void
    {
        $own = $this->world->submitted();
        $foreign = $this->world->submitted(World::SUPPLIER2_AUTHOR, World::APP2_LOT_A, World::SUPPLIER2_HEAD);
        $document = $this->documentOf(DocumentType::AppComplaint, $own);

        $this->world->actAs(World::HEAD);
        $file = $this->world->get(DownloadFile::class)->execute(FileKind::Document, $document);
        self::assertStringStartsWith('%PDF-app_complaint', $file->bytes);
        self::assertSame('application/pdf', $file->description->mimeType);

        self::assertFalse($this->canDownload(World::SUPPLIER2_AUTHOR, FileKind::Document, $document));
        self::assertFalse($this->canDownload(World::AUTHOR, FileKind::Document, $this->documentOf(DocumentType::AppComplaint, $foreign)));
        self::assertFalse($this->canDownload(World::REVIEWER, FileKind::Document, $document), 'До сбора жалоба не видна органу.');

        $this->world->collect();
        self::assertTrue($this->canDownload(World::REVIEWER, FileKind::Document, $document));
        $signature = $this->world->signatures->ofDocument($document)[0]->id;
        self::assertTrue($this->canDownload(World::REVIEWER, FileKind::Signature, $signature));
        self::assertFalse($this->canDownload(World::SUPPLIER2_AUTHOR, FileKind::Signature, $signature));
    }

    public function testSupplierGetsOwnAnswerOnlyAfterApprovalAndNeverTheConsolidatedDocument(): void
    {
        $own = $this->world->submitted();
        $foreign = $this->world->submitted(World::SUPPLIER2_AUTHOR, World::APP2_LOT_A, World::SUPPLIER2_HEAD);
        $this->world->collect();
        $decisionId = $this->steps->decisionOf($own);
        $this->steps->chooseMethod($decisionId);
        $this->steps->answerAll([$own => Verdict::Satisfied, $foreign => Verdict::Rejected], [World::LOT_A => DecisionType::ReviseResults]);
        $ownAnswer = $this->documentOf(DocumentType::Answer, (int) $this->steps->answerOf($own));
        $foreignAnswer = $this->documentOf(DocumentType::Answer, (int) $this->steps->answerOf($foreign));

        self::assertFalse($this->canDownload(World::AUTHOR, FileKind::Document, $ownAnswer));

        $this->steps->send($decisionId);
        $this->steps->approve($decisionId);
        $consolidated = $this->documentOf(DocumentType::ConsolidatedAnswer, $decisionId);
        $approverSignature = $this->world->signatures->ofDocument($consolidated)[2]->id;

        self::assertTrue($this->canDownload(World::AUTHOR, FileKind::Document, $ownAnswer));
        self::assertFalse($this->canDownload(World::AUTHOR, FileKind::Document, $foreignAnswer));
        self::assertFalse($this->canDownload(World::AUTHOR, FileKind::Document, $consolidated));
        self::assertFalse($this->canDownload(World::AUTHOR, FileKind::Signature, $approverSignature));
        self::assertTrue($this->canDownload(World::ORGANIZER, FileKind::Document, $consolidated));
        self::assertTrue($this->canDownload(World::ORGANIZER, FileKind::Signature, $approverSignature));

        $this->world->actAs(World::AUTHOR);
        $answer = $this->world->get(ShowComplaint::class)->execute($own)->answer;
        self::assertNotNull($answer);
        self::assertSame(Verdict::Satisfied, $answer->verdict);
        self::assertSame(
            ['Сидорова Айгуль Болатовна', 'Ахметов Берик Канатович', 'Касымова Динара Ерлановна'],
            array_map(static fn ($signer): string => $signer->signerName, $answer->signers),
        );
    }

    public function testSigningSourceIsGivenOnlyToAwaitedSigner(): void
    {
        $id = $this->world->draft();
        $this->world->get(SendComplaintForSignature::class)->execute($id);
        $document = $this->world->documents->current(DocumentType::AppComplaint, $id);
        self::assertNotNull($document);

        $this->world->actAs(World::HEAD);
        $source = $this->world->get(GetSigningSource::class)->execute($document->id);
        self::assertSame($this->world->files->files[$document->fileId]['bytes'], base64_decode($source, true));

        $this->world->actAs(World::AUTHOR);
        try {
            $this->world->get(GetSigningSource::class)->execute($document->id);
            self::fail('Автор не подписывает жалобу.');
        } catch (AccessDenied) {
        }

        $this->world->actAs(World::HEAD);
        $this->world->get(SignComplaint::class)->execute($id, base64_encode('cms'));
        $this->expectException(AccessDenied::class);
        $this->world->get(GetSigningSource::class)->execute($document->id);
    }

    public function testReviewerGetsUnsentConsolidatedDocumentToSignOnSending(): void
    {
        $complaint = $this->world->submitted();
        $this->world->collect();
        $decisionId = $this->steps->decisionOf($complaint);
        $this->steps->chooseMethod($decisionId);
        $this->steps->answerAll([$complaint => Verdict::Rejected]);
        $this->world->get(FormConsolidatedDocument::class)->execute($decisionId);
        $document = $this->documentOf(DocumentType::ConsolidatedAnswer, $decisionId);

        self::assertNotSame('', $this->world->get(GetSigningSource::class)->execute($document));

        $this->world->actAs(World::SECOND_REVIEWER);
        try {
            $this->world->get(GetSigningSource::class)->execute($document);
            self::fail('Решение закреплено за другим рассматривающим.');
        } catch (AccessDenied) {
        }

        $this->world->actAs(World::REVIEWER);
        $this->world->get(SendDecisionForApproval::class)->execute(
            new SendDecisionCommand($decisionId, World::COORDINATOR, World::APPROVER, base64_encode('cms-reviewer')),
        );
        $this->world->actAs(World::COORDINATOR);
        self::assertNotSame('', $this->world->get(GetSigningSource::class)->execute($document));
        $this->world->actAs(World::REVIEWER);
        $this->expectException(AccessDenied::class);
        $this->world->get(GetSigningSource::class)->execute($document);
    }
}
