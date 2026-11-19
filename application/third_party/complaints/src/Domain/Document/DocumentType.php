<?php

declare(strict_types=1);

namespace Complaints\Domain\Document;

/**
 * Тип сформированного документа (вид строки app_complaint_docs или trd_complaint_docs).
 */
enum DocumentType: string
{
    /** Жалоба поставщика; владелец — app_complaint. */
    case AppComplaint = 'app_complaint';

    /** Ответ на жалобу; владелец — app_complaint_answer. */
    case Answer = 'answer';

    /** Сводный документ ответов; владелец — trd_complaint. */
    case ConsolidatedAnswer = 'consolidated_answer';
}
