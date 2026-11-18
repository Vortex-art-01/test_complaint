<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint;

use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\ApplicationEligibility;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Port\ProcurementLotGateway;
use Complaints\Application\Repository\ApplicationCard;
use Complaints\Application\Repository\BuyCard;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Application\Service\DeadlineCalculator;
use Complaints\Application\Shared\ComplaintSettings;
use DateTimeImmutable;

/**
 * Что организация может обжаловать: закупка организатора и способа из
 * настроек, своя заявка, лоты заявки с правом на жалобу и открытым сроком.
 * Все сведения берутся из БД, а не из параметров запроса.
 */
final class FilingRules
{
    public function __construct(
        private readonly ProcurementRepository $procurement,
        private readonly ProcurementLotGateway $lotGateway,
        private readonly ApplicationEligibility $eligibility,
        private readonly OrganizationDirectory $directory,
        private readonly DeadlineCalculator $deadlines,
        private readonly ComplaintSettings $settings,
    ) {
    }

    /**
     * @throws ValidationFailed
     */
    public function buyByNumber(string $buyNumber, int $supplierOrgId, DateTimeImmutable $now): ComplainableBuy
    {
        $buyNumber = trim($buyNumber);
        if ($buyNumber === '') {
            throw ValidationFailed::field('buy_number', 'complaints_error_required');
        }

        $buy = $this->procurement->findBuyByNumber($buyNumber)
            ?? throw ValidationFailed::field('buy_number', 'complaints_error_buy_not_found');

        return $this->complainable($buy, $supplierOrgId, $now, 'buy_number');
    }

    /**
     * @throws ValidationFailed
     */
    public function buyById(int $buyId, int $supplierOrgId, DateTimeImmutable $now): ComplainableBuy
    {
        return $this->complainable($this->procurement->buy($buyId), $supplierOrgId, $now, 'buy_id');
    }

    /**
     * Лот заявки организации, по которому сейчас можно подать жалобу.
     *
     * @throws ValidationFailed
     */
    public function lot(int $buyId, int $appLotId, int $supplierOrgId, DateTimeImmutable $now): ComplainableLot
    {
        foreach ($this->buyById($buyId, $supplierOrgId, $now)->lots as $lot) {
            if ($lot->appLot->id === $appLotId) {
                return $lot;
            }
        }

        throw ValidationFailed::field('app_lot_id', 'complaints_error_lot_not_complainable');
    }

    /**
     * Подписант — сотрудник организации с ролью «Руководитель».
     *
     * @throws ValidationFailed
     */
    public function assertHead(int $supplierOrgId, int $headUserId): void
    {
        foreach ($this->directory->headsOf($supplierOrgId) as $head) {
            if ($head->id === $headUserId) {
                return;
            }
        }

        throw ValidationFailed::field('head_user_id', 'complaints_error_head_not_found');
    }

    private function complainable(BuyCard $buy, int $supplierOrgId, DateTimeImmutable $now, string $field): ComplainableBuy
    {
        if (!$this->isComplainableProcurement($buy)) {
            throw ValidationFailed::field($field, 'complaints_error_buy_not_complainable');
        }

        $application = $this->procurement->supplierApplication($buy->id, $supplierOrgId)
            ?? throw ValidationFailed::field($field, 'complaints_error_no_application');

        $lots = $this->openLots($application, $now);
        if ($lots === []) {
            throw ValidationFailed::field($field, 'complaints_error_appeal_closed');
        }

        return new ComplainableBuy($buy, $application, $lots);
    }

    private function isComplainableProcurement(BuyCard $buy): bool
    {
        return in_array($buy->methodId, $this->settings->complainableMethodIds, true)
            && in_array($this->directory->organization($buy->organizerOrgId)->bin, $this->settings->organizerBins, true);
    }

    /**
     * @return list<ComplainableLot>
     */
    private function openLots(ApplicationCard $application, DateTimeImmutable $now): array
    {
        $appLots = $this->procurement->applicationLots($application->id);
        $buyLots = $this->procurement->buyLots(array_map(static fn ($appLot): int => $appLot->buyLotId, $appLots));

        $open = [];
        foreach ($appLots as $appLot) {
            $publishedAt = $this->lotGateway->resultsProtocolPublishedAt($appLot->buyLotId);
            if ($publishedAt === null
                || !isset($buyLots[$appLot->buyLotId])
                || !$this->eligibility->isEligible($application->id, $appLot->id)) {
                continue;
            }

            $period = $this->deadlines->appealPeriod($publishedAt);
            if ($period->isOpenAt($now)) {
                $open[] = new ComplainableLot($appLot, $buyLots[$appLot->buyLotId], $publishedAt, $period);
            }
        }

        return $open;
    }
}
