<?php

declare(strict_types=1);

namespace Complaints\Tests\Infrastructure;

use Complaints\Application\AppComplaint\UseCase\SendComplaintForSignature;
use Complaints\Application\AppComplaint\UseCase\SignComplaint;
use Complaints\Application\AppComplaint\UseCase\SubmitComplaint;
use Complaints\Application\AppComplaint\UseCase\UpdateComplaintDraft;
use Complaints\Application\AppComplaint\UseCase\UpdateComplaintDraftCommand;
use Complaints\Application\Exception\IntegrationFailure;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Document\DocumentType;
use Complaints\Infrastructure\Pdf\HtmlToPdfConverter;
use Complaints\Infrastructure\Pdf\TemplatePdfRenderer;
use Complaints\Tests\Support\ReviewSteps;
use Complaints\Tests\Support\World;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Шаблоны PDF на данных настоящего сценария: переменные, которые сценарии
 * передали фейковому PdfRenderer, отрисовываются настоящими шаблонами.
 */
final class PdfTemplatesTest extends TestCase
{
    private TemplatePdfRenderer $renderer;

    /** @var list<array{template: DocumentType, variables: array<string, mixed>}> */
    private array $rendered;

    protected function setUp(): void
    {
        $this->renderer = new TemplatePdfRenderer(
            new class () implements HtmlToPdfConverter {
                public function convert(string $html): string
                {
                    return '%PDF-1.4 ' . strlen($html);
                }
            },
            dirname(__DIR__, 2) . '/templates/pdf',
        );

        $world = new World();
        $steps = new ReviewSteps($world);
        $id = $world->draft();
        $world->get(UpdateComplaintDraft::class)->execute(new UpdateComplaintDraftCommand(
            $id,
            '+7 701 123 45 67',
            "Комиссия отклонила заявку <script>alert(1)</script>\nвторая строка",
        ));
        $world->get(SendComplaintForSignature::class)->execute($id);
        $world->actAs(World::HEAD);
        $world->get(SignComplaint::class)->execute($id, base64_encode('cms'));
        $world->get(SubmitComplaint::class)->execute($id);
        $world->collect();
        $steps->decide([$id => Verdict::Satisfied], [World::LOT_A => DecisionType::ReviseResults]);

        $this->rendered = $world->pdf->rendered;
    }

    /**
     * @return array<string, mixed>
     */
    private function variables(DocumentType $type): array
    {
        foreach (array_reverse($this->rendered) as $entry) {
            if ($entry['template'] === $type) {
                return $entry['variables'];
            }
        }
        self::fail('Документ не формировался: ' . $type->value);
    }

    public function testComplaintDocumentIsBilingualAndEscapesUserText(): void
    {
        $html = $this->renderer->html(DocumentType::AppComplaint, $this->variables(DocumentType::AppComplaint));

        self::assertStringContainsString('Жалоба на действия (бездействие) организатора закупа', $html);
        self::assertStringContainsString('Сатып алуды ұйымдастырушының әрекетіне', $html);
        self::assertMatchesRegularExpression('/Ж-\d{6}/', $html);
        self::assertStringContainsString('ТОО «Поставщик-1»', $html);
        self::assertStringContainsString('15.10.2026 00:00 — 19.10.2026 23:59', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;<br>', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testAnswerAndConsolidatedDocumentsShowVerdictsAndChecksums(): void
    {
        $answer = $this->renderer->html(DocumentType::Answer, $this->variables(DocumentType::Answer));
        self::assertStringContainsString('Ответ на жалобу', $answer);
        self::assertStringContainsString('Жалоба удовлетворена', $answer);
        self::assertStringContainsString('4889720-Т1', $answer);

        $variables = $this->variables(DocumentType::ConsolidatedAnswer);
        $consolidated = $this->renderer->html(DocumentType::ConsolidatedAnswer, $variables);
        self::assertStringContainsString('Сводный ответ по жалобам на закупку', $consolidated);
        self::assertStringContainsString('Пересмотр итогов', $consolidated);
        self::assertStringContainsString('По каждой жалобе', $consolidated);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $variables['registry'][0]['sha256']);
        self::assertStringContainsString($variables['registry'][0]['sha256'], $consolidated);
    }

    public function testConverterFailureBecomesIntegrationFailure(): void
    {
        $renderer = new TemplatePdfRenderer(
            new class () implements HtmlToPdfConverter {
                public function convert(string $html): string
                {
                    throw new RuntimeException('генератор недоступен');
                }
            },
            dirname(__DIR__, 2) . '/templates/pdf',
        );

        $this->expectExceptionObject(new IntegrationFailure('complaints_error_pdf'));
        $renderer->render(DocumentType::AppComplaint, $this->variables(DocumentType::AppComplaint));
    }

    public function testRendererReturnsConverterBytes(): void
    {
        self::assertStringStartsWith('%PDF-1.4 ', $this->renderer->render(DocumentType::Answer, $this->variables(DocumentType::Answer)));
    }
}
