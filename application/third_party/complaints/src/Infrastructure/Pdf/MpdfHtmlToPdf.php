<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Pdf;

use LogicException;

/**
 * PDF через mPDF 7+, если он уже установлен в проекте (класс \Mpdf\Mpdf).
 * Модуль mPDF не устанавливает.
 */
final class MpdfHtmlToPdf implements HtmlToPdfConverter
{
    private const MPDF = 'Mpdf\\Mpdf';

    public function __construct(
        private readonly string $workDirectory,
    ) {
    }

    public static function isAvailable(): bool
    {
        return class_exists(self::MPDF);
    }

    public function convert(string $html): string
    {
        if (!self::isAvailable()) {
            throw new LogicException('mPDF 7+ не найден: укажите pdf_converter = \'project\' в config/complaints.php.');
        }

        $class = self::MPDF;
        $mpdf = new $class([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'tempDir' => $this->workDirectory,
        ]);
        $mpdf->WriteHTML($html);

        return (string) $mpdf->Output('', 'S');
    }
}
