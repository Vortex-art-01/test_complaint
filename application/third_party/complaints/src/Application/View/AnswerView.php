<?php

declare(strict_types=1);

namespace Complaints\Application\View;

use Complaints\Domain\BuyComplaint\Answer;

/**
 * Ответ органа, его PDF и вложения.
 */
final readonly class AnswerView
{
    /**
     * @param list<AttachmentView> $attachments
     */
    public function __construct(
        public Answer $answer,
        public ?DocumentView $document,
        public array $attachments,
    ) {
    }

    /**
     * PDF ответа сформирован: текст и файлы ответа меняются только после «Сбросить».
     */
    public function isFormed(): bool
    {
        return $this->document !== null;
    }
}
