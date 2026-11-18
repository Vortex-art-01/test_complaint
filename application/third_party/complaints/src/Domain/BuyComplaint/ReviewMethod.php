<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

/**
 * Способ рассмотрения, выбирается один раз на всю закупку.
 */
enum ReviewMethod: string
{
    /** Ответ на каждый лот жалобы. */
    case PerComplaint = 'per_complaint';

    /** Один ответ на все жалобы по лоту закупки. */
    case PerLot = 'per_lot';
}
