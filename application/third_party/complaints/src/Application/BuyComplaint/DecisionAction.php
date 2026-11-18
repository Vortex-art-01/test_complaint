<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint;

/**
 * Действие над решением, доступное текущему пользователю сейчас.
 */
enum DecisionAction: string
{
    /** Рассматривающий: выбрать или сменить способ рассмотрения. */
    case ChooseMethod = 'choose_method';

    /** Рассматривающий: заполнять вердикты, ответы и типы решений. */
    case EditAnswers = 'edit_answers';

    /** Рассматривающий: сформировать сводный документ (после ФЛК). */
    case FormConsolidated = 'form_consolidated';

    /** Рассматривающий: подписать сводный документ и отправить по маршруту. */
    case Send = 'send';

    /** Рассматривающий: отозвать с согласования до подписи утверждающего. */
    case Recall = 'recall';

    /** Рассматривающий: вернуть отозванное решение к правке. */
    case Reopen = 'reopen';

    /** Утверждающий органа: снять закрепление за рассматривающим. */
    case Release = 'release';

    /** Согласующий: согласовать и подписать. */
    case Coordinate = 'coordinate';

    /** Утверждающий: утвердить и подписать. */
    case Approve = 'approve';

    /** Организатор: отменить лоты с решением «отмена закупки». */
    case CancelLots = 'cancel_lots';

    /** Организатор: вернуть лоты на пересмотр итогов. */
    case StartRevision = 'start_revision';
}
