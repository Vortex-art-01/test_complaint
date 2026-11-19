<?php

declare(strict_types=1);

namespace Complaints\Tests\Web;

use Complaints\Application\AppComplaint\UseCase\FindComplainableBuy;
use Complaints\Application\AppComplaint\UseCase\ListOrganizationComplaints;
use Complaints\Application\AppComplaint\UseCase\ListSupplierHeads;
use Complaints\Application\AppComplaint\UseCase\SendComplaintForSignature;
use Complaints\Application\AppComplaint\UseCase\ShowComplainableBuy;
use Complaints\Application\AppComplaint\UseCase\ShowComplaint;
use Complaints\Application\AppComplaint\UseCase\UpdateComplaintDraft;
use Complaints\Application\AppComplaint\UseCase\UpdateComplaintDraftCommand;
use Complaints\Application\BuyComplaint\UseCase\FormConsolidatedDocument;
use Complaints\Application\BuyComplaint\UseCase\ListAuthorityDecisions;
use Complaints\Application\BuyComplaint\UseCase\ShowDecision;
use Complaints\Application\BuyComplaint\UseCase\ShowDecisionLot;
use Complaints\Application\Execution\UseCase\ListOrganizerDecisions;
use Complaints\Bootstrap\ModuleContainer;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Tests\Support\ReviewSteps;
use Complaints\Tests\Support\World;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/functions.php';

/**
 * Экраны модуля на данных настоящих сценариев: представления отрисовываются
 * без ошибок и предупреждений, текст пользователя экранирован, кнопки
 * соответствуют правам пользователя.
 */
final class ViewsTest extends TestCase
{
    private World $world;
    private ReviewSteps $steps;

    protected function setUp(): void
    {
        $this->world = new World();
        $this->steps = new ReviewSteps($this->world);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function render(string $view, array $data, string $language = 'ru'): string
    {
        $application = dirname(__DIR__, 4);
        $config = [];
        require $application . '/config/complaints.php';
        $lines = ModuleContainer::language($application . '/language/' . ($language === 'kz' ? 'kazakh' : 'russian') . '/complaints_lang.php');

        $html = (string) (new CodeIgniterStub($application . '/views', $lines, $language, $config['complaints']))->view($view, $data, true);
        self::assertDoesNotMatchRegularExpression('/\bcomplaints_[a-z_]+\b(?![^<]*>)/', strip_tags($html), 'Строка без перевода');

        return $html;
    }

    public function testSupplierRegistryCreateAndDraftScreens(): void
    {
        $id = $this->world->draft();
        $this->world->get(UpdateComplaintDraft::class)->execute(new UpdateComplaintDraftCommand($id, '+7 701 000 00 00', 'Суть <script>alert(1)</script>'));

        $registry = self::render('complaints/supplier/index', ['page' => $this->world->get(ListOrganizationComplaints::class)->execute(1)]);
        self::assertStringContainsString('/ru/complaint_supplier/edit/' . $id, $registry);
        self::assertStringContainsString('Черновик', $registry);

        $buy = $this->world->get(FindComplainableBuy::class)->execute('125407-1');
        $create = self::render('complaints/supplier/create', [
            'number' => '125407-1',
            'buy' => $buy,
            'error' => null,
            'heads' => $this->world->get(ListSupplierHeads::class)->execute(),
        ]);
        self::assertStringContainsString('name="app_lot_id" value="' . World::APP_LOT_A . '"', $create);
        self::assertStringContainsString('Сейтов Бауыржан Нурланович', $create);
        self::assertStringContainsString('15.10.2026 00:00 — 19.10.2026 23:59', $create);

        $notFound = self::render('complaints/supplier/create', ['number' => '1', 'buy' => null, 'error' => 'Закупка не найдена', 'heads' => []]);
        self::assertStringContainsString('Закупка не найдена', $notFound);

        $edit = self::render('complaints/supplier/edit', [
            'card' => $this->world->get(ShowComplaint::class)->execute($id),
            'buy' => $this->world->get(ShowComplainableBuy::class)->execute(World::BUY),
            'heads' => $this->world->get(ListSupplierHeads::class)->execute(),
            'descriptionMaxLength' => 500,
        ], 'kz');
        self::assertStringContainsString('Суть &lt;script&gt;alert(1)&lt;/script&gt;', $edit);
        self::assertStringNotContainsString('<script>alert(1)', $edit);
        self::assertStringContainsString('/kz/complaint_supplier/send_for_signature/' . $id, $edit);
        self::assertStringContainsString('Шағымның мәні', $edit);
    }

    public function testComplaintCardShowsSignButtonOnlyToChosenHead(): void
    {
        $id = $this->world->draft();
        $this->world->get(SendComplaintForSignature::class)->execute($id);

        $this->world->actAs(World::HEAD);
        $forHead = self::render('complaints/supplier/show', ['card' => $this->world->get(ShowComplaint::class)->execute($id)]);
        self::assertStringContainsString('data-complaints-sign="/ru/complaint_supplier/sign/' . $id . '"', $forHead);
        self::assertStringContainsString('data-source="/ru/complaint_files/source/', $forHead);

        $this->world->actAs(World::AUTHOR);
        $forAuthor = self::render('complaints/supplier/show', ['card' => $this->world->get(ShowComplaint::class)->execute($id)]);
        self::assertStringNotContainsString('data-complaints-sign', $forAuthor);
        self::assertStringContainsString('На подписи у руководителя', $forAuthor);
    }

    public function testReviewScreensThroughTheWholeRoute(): void
    {
        $first = $this->world->submitted();
        $second = $this->world->submitted(World::SUPPLIER2_AUTHOR, World::APP2_LOT_A, World::SUPPLIER2_HEAD);
        $this->world->collect();
        $decisionId = $this->steps->decisionOf($first);

        $this->world->actAs(World::REVIEWER);
        $registry = self::render('complaints/review/index', [
            'page' => $this->world->get(ListAuthorityDecisions::class)->execute(null, null, 1),
            'status' => null,
            'number' => '',
        ]);
        self::assertStringContainsString('/ru/complaint_review/show/' . $decisionId, $registry);
        $fresh = self::render('complaints/review/show', ['overview' => $this->world->get(ShowDecision::class)->execute($decisionId)]);
        self::assertStringContainsString('/ru/complaint_review/choose_method/' . $decisionId, $fresh);

        $this->steps->chooseMethod($decisionId);
        $lot = $this->steps->decisionLotOf($first);
        $editable = self::render('complaints/review/lot', ['details' => $this->world->get(ShowDecisionLot::class)->execute($lot)]);
        self::assertStringContainsString('name="verdicts[' . $this->steps->complaintLotOf($second) . ']"', $editable);
        self::assertStringContainsString('form="complaints-lot-form"', $editable);

        $this->steps->answerAll([$first => Verdict::Satisfied, $second => Verdict::Rejected], [World::LOT_A => DecisionType::ReviseResults]);
        $formed = self::render('complaints/review/lot', ['details' => $this->world->get(ShowDecisionLot::class)->execute($lot)]);
        self::assertStringContainsString('/ru/complaint_review/reset_answer/', $formed);
        self::assertStringContainsString('readonly', $formed);

        $this->world->get(FormConsolidatedDocument::class)->execute($decisionId);
        $sendable = self::render('complaints/review/show', ['overview' => $this->world->get(ShowDecision::class)->execute($decisionId)]);
        self::assertStringContainsString('data-complaints-sign="/ru/complaint_review/send/' . $decisionId . '"', $sendable);
        self::assertStringContainsString('Ахметов Берик Канатович', $sendable);

        $this->steps->send($decisionId);
        $this->world->actAs(World::COORDINATOR);
        $coordination = self::render('complaints/review/show', ['overview' => $this->world->get(ShowDecision::class)->execute($decisionId)], 'kz');
        self::assertStringContainsString('/kz/complaint_review/coordinate/' . $decisionId, $coordination);
        self::assertStringContainsString('Келісуде', $coordination);

        $this->steps->approve($decisionId);
        $this->world->actAs(World::AUTHOR);
        $answered = self::render('complaints/supplier/show', ['card' => $this->world->get(ShowComplaint::class)->execute($first)]);
        self::assertStringContainsString('Ответ уполномоченного органа', $answered);
        self::assertStringContainsString('№ ' . $decisionId . ' от ', $answered);

        $this->world->actAs(World::REVIEWER);
        $approved = self::render('complaints/review/show', ['overview' => $this->world->get(ShowDecision::class)->execute($decisionId)]);
        self::assertStringContainsString('№ ' . $decisionId . ' от ', $approved);
        self::assertStringNotContainsString('.p7s', $approved);
    }

    public function testPerLotReviewAndOrganizerScreens(): void
    {
        $first = $this->world->submitted();
        $this->world->collect();
        $decisionId = $this->steps->decisionOf($first);
        $this->steps->chooseMethod($decisionId, ReviewMethod::PerLot);

        $perLot = self::render('complaints/review/lot', ['details' => $this->world->get(ShowDecisionLot::class)->execute($this->steps->decisionLotOf($first))]);
        self::assertStringContainsString('name="lot_answer"', $perLot);
        self::assertStringNotContainsString('name="answers[', $perLot);

        $this->steps->chooseMethod($decisionId);
        $this->steps->decide([$first => Verdict::Satisfied], [World::LOT_A => DecisionType::CancelPurchase]);

        $this->world->actAs(World::ORGANIZER);
        $registry = self::render('complaints/execution/index', [
            'page' => $this->world->get(ListOrganizerDecisions::class)->execute(null, null, 1),
            'status' => null,
            'number' => '',
        ]);
        self::assertStringContainsString('/ru/complaint_execution/show/' . $decisionId, $registry);
        self::assertStringContainsString('На исполнении', $registry);

        $card = self::render('complaints/execution/show', ['overview' => $this->world->get(ShowDecision::class)->execute($decisionId)]);
        self::assertStringContainsString('/ru/complaint_execution/cancel_lots/' . $decisionId, $card);
        self::assertStringNotContainsString('/ru/complaint_execution/start_revision/', $card);
    }

    public function testStandalonePageCarriesCsrfTokenAndPortalAssets(): void
    {
        $html = self::render('complaints/page', ['title' => 'Жалобы', 'content' => '<div class="complaints"></div>']);

        self::assertStringContainsString('<meta name="ci_csrf_token" content="csrf-test-hash">', $html);
        self::assertStringContainsString('/css/bootstrap.min.css', $html);
        self::assertStringContainsString('<div class="complaints"></div>', $html);
    }
}
