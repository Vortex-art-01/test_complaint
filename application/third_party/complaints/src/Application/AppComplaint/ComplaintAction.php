<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint;

/**
 * Действие над жалобой, доступное текущему пользователю сейчас.
 */
enum ComplaintAction: string
{
    /** Открыть форму черновика (автор). */
    case Edit = 'edit';

    /** Подписать ЭЦП (выбранный руководитель). */
    case Sign = 'sign';

    /** Вернуть в черновик с аннулированием подписи (автор). */
    case ReturnToDraft = 'return_to_draft';

    /** Подать (автор или руководитель). */
    case Submit = 'submit';

    /** Отозвать (автор). */
    case Withdraw = 'withdraw';

    /** Удалить (автор). */
    case Delete = 'delete';
}
