<?php

declare(strict_types=1);

namespace Complaints\Domain\Document;

/**
 * Тип сформированного документа (test_complaint_documents.doc_type).
 */
enum DocumentType: string
{
    /** Жалоба поставщика; владелец — test_app_complaint. */
    case AppComplaint = 'app_complaint';

    /** Ответ на жалобу; владелец — test_complaint_answers. */
    case Answer = 'answer';

    /** Сводный документ ответов; владелец — test_buy_complaint. */
    case ConsolidatedAnswer = 'consolidated_answer';
}
