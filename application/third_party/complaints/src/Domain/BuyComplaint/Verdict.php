<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

/**
 * Вердикт по лоту жалобы.
 */
enum Verdict: string
{
    case Satisfied = 'satisfied';
    case Rejected = 'rejected';
}
