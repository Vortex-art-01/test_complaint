<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'libraries/Complaints_controller.php';

use Complaints\Application\BuyComplaint\UseCase\ShowDecision;
use Complaints\Application\Execution\UseCase\CancelDecisionLots;
use Complaints\Application\Execution\UseCase\ListOrganizerDecisions;
use Complaints\Application\Execution\UseCase\StartLotsRevision;
use Complaints\Domain\BuyComplaint\ExecutionStatus;

/**
 * Организатор: утверждённые решения по своим закупкам и их исполнение.
 */
class Complaint_execution extends Complaints_controller
{
    public function index($page = 1): void
    {
        $this->screen(function () use ($page): array {
            $status = ExecutionStatus::tryFrom((string) $this->query_text('status'));
            $number = $this->query_text('number');

            return [
                'view' => 'complaints/execution/index',
                'title' => complaints_t('complaints_title_execution_registry'),
                'data' => [
                    'page' => $this->module(ListOrganizerDecisions::class)->execute($status, $number, $this->page_number($page)),
                    'status' => $status,
                    'number' => (string) $number,
                ],
            ];
        });
    }

    public function show($id = 0): void
    {
        $this->screen(function () use ($id): array {
            $overview = $this->module(ShowDecision::class)->execute((int) $id);

            return [
                'view' => 'complaints/execution/show',
                'title' => complaints_t('complaints_title_decision', ['buy' => $overview->buy->number]),
                'data' => ['overview' => $overview],
            ];
        });
    }

    public function cancel_lots($id = 0): void
    {
        $this->action(function () use ($id): array {
            $count = $this->module(CancelDecisionLots::class)->execute((int) $id);

            return ['reload' => true, 'message' => complaints_t('complaints_lots_cancelled', ['count' => $count])];
        });
    }

    public function start_revision($id = 0): void
    {
        $this->action(function () use ($id): array {
            $count = $this->module(StartLotsRevision::class)->execute((int) $id);

            return ['reload' => true, 'message' => complaints_t('complaints_lots_revised', ['count' => $count])];
        });
    }
}
