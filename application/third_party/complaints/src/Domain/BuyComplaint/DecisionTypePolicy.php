<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

/**
 * Согласование типа решения по лоту с вердиктами жалоб этого лота.
 *
 * Все жалобы лота отклонены — допустимо только «без изменений».
 * Есть удовлетворённая жалоба — допустим любой тип.
 */
final class DecisionTypePolicy
{
    /**
     * @param list<Verdict> $verdicts вердикты всех жалоб лота
     * @return list<DecisionType>
     */
    public function allowedTypes(array $verdicts): array
    {
        if (in_array(Verdict::Satisfied, $verdicts, true)) {
            return DecisionType::cases();
        }

        return [DecisionType::NoAction];
    }

    /**
     * @param list<Verdict> $verdicts
     */
    public function isAllowed(DecisionType $type, array $verdicts): bool
    {
        return in_array($type, $this->allowedTypes($verdicts), true);
    }

    /**
     * Тип, который ставится сам, если выбора нет.
     *
     * @param list<Verdict> $verdicts
     */
    public function forcedType(array $verdicts): ?DecisionType
    {
        $allowed = $this->allowedTypes($verdicts);

        return count($allowed) === 1 ? $allowed[0] : null;
    }
}
