<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'libraries/Complaints_controller.php';

use Complaints\Application\BuyComplaint\UseCase\AddAnswerAttachment;
use Complaints\Application\BuyComplaint\UseCase\ApproveDecision;
use Complaints\Application\BuyComplaint\UseCase\ChooseReviewMethod;
use Complaints\Application\BuyComplaint\UseCase\CoordinateDecision;
use Complaints\Application\BuyComplaint\UseCase\FormAnswerDocument;
use Complaints\Application\BuyComplaint\UseCase\FormConsolidatedDocument;
use Complaints\Application\BuyComplaint\UseCase\ListAuthorityDecisions;
use Complaints\Application\BuyComplaint\UseCase\RecallDecision;
use Complaints\Application\BuyComplaint\UseCase\ReleaseDecision;
use Complaints\Application\BuyComplaint\UseCase\RemoveAnswerAttachment;
use Complaints\Application\BuyComplaint\UseCase\ReopenDecision;
use Complaints\Application\BuyComplaint\UseCase\ResetAnswerDocument;
use Complaints\Application\BuyComplaint\UseCase\SaveLotAnswers;
use Complaints\Application\BuyComplaint\UseCase\SaveLotAnswersCommand;
use Complaints\Application\BuyComplaint\UseCase\SendDecisionCommand;
use Complaints\Application\BuyComplaint\UseCase\SendDecisionForApproval;
use Complaints\Application\BuyComplaint\UseCase\ShowDecision;
use Complaints\Application\BuyComplaint\UseCase\ShowDecisionLot;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\BuyComplaint\Verdict;

/**
 * Уполномоченный орган: реестр решений, рассмотрение по лотам, сводный документ,
 * маршрут согласования, согласование и утверждение с подписью ЭЦП.
 */
class Complaint_review extends Complaints_controller
{
    public function index($page = 1): void
    {
        $this->screen(function () use ($page): array {
            $status = BuyComplaintStatus::tryFrom((string) $this->query_text('status'));
            $number = $this->query_text('number');

            return [
                'view' => 'complaints/review/index',
                'title' => complaints_t('complaints_title_review_registry'),
                'data' => [
                    'page' => $this->module(ListAuthorityDecisions::class)->execute($status, $number, $this->page_number($page)),
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
                'view' => 'complaints/review/show',
                'title' => complaints_t('complaints_title_decision', ['buy' => $overview->buy->number]),
                'data' => ['overview' => $overview],
            ];
        });
    }

    public function lot($decisionLotId = 0): void
    {
        $this->screen(function () use ($decisionLotId): array {
            $details = $this->module(ShowDecisionLot::class)->execute((int) $decisionLotId);

            return [
                'view' => 'complaints/review/lot',
                'title' => complaints_t('complaints_title_decision_lot', ['lot' => $details->buyLot->number]),
                'data' => ['details' => $details],
            ];
        });
    }

    public function choose_method($id = 0): void
    {
        $this->action(function () use ($id): array {
            $method = ReviewMethod::tryFrom($this->post_text('method'))
                ?? throw ValidationFailed::field('method', 'complaints_error_required');
            $this->module(ChooseReviewMethod::class)->execute((int) $id, $method);

            return ['reload' => true];
        });
    }

    /**
     * «Сохранить» на экране лота: verdicts[лот жалобы], answers[лот жалобы] или lot_answer, decision_type.
     */
    public function save_lot($decisionLotId = 0): void
    {
        $this->action(function () use ($decisionLotId): array {
            $verdicts = [];
            foreach ($this->post_array('verdicts') as $complaintLotId => $value) {
                $verdicts[(int) $complaintLotId] = Verdict::tryFrom((string) $value);
            }
            $answers = [];
            foreach ($this->post_array('answers') as $complaintLotId => $text) {
                $answers[(int) $complaintLotId] = (string) $text;
            }
            $lotAnswer = $this->input->post('lot_answer');

            $this->module(SaveLotAnswers::class)->execute(new SaveLotAnswersCommand(
                (int) $decisionLotId,
                $verdicts,
                $answers,
                is_string($lotAnswer) ? $lotAnswer : null,
                DecisionType::tryFrom($this->post_text('decision_type')),
            ));

            return ['reload' => true, 'message' => complaints_t('complaints_saved')];
        });
    }

    public function form_answer($answerId = 0): void
    {
        $this->action(function () use ($answerId): array {
            $this->module(FormAnswerDocument::class)->execute((int) $answerId);

            return ['reload' => true];
        });
    }

    public function reset_answer($answerId = 0): void
    {
        $this->action(function () use ($answerId): array {
            $this->module(ResetAnswerDocument::class)->execute((int) $answerId);

            return ['reload' => true];
        });
    }

    public function attach_answer($decisionLotId = 0): void
    {
        $this->action(function () use ($decisionLotId): array {
            $this->module(AddAnswerAttachment::class)->execute(
                (int) $decisionLotId,
                $this->post_optional_id('complaint_lot_id'),
                $this->uploaded_file('file'),
            );

            return ['reload' => true];
        });
    }

    public function detach_answer($attachmentId = 0): void
    {
        $this->action(function () use ($attachmentId): array {
            $this->module(RemoveAnswerAttachment::class)->execute((int) $attachmentId);

            return ['reload' => true];
        });
    }

    public function form_consolidated($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(FormConsolidatedDocument::class)->execute((int) $id);

            return ['reload' => true];
        });
    }

    /**
     * Подпись сводного документа рассматривающим и отправка по маршруту.
     */
    public function send($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(SendDecisionForApproval::class)->execute(new SendDecisionCommand(
                (int) $id,
                $this->post_optional_id('coordinator_user_id'),
                $this->post_id('approver_user_id'),
                $this->post_text('signature'),
            ));

            return ['reload' => true, 'message' => complaints_t('complaints_sent')];
        });
    }

    public function recall($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(RecallDecision::class)->execute((int) $id);

            return ['reload' => true];
        });
    }

    public function reopen($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(ReopenDecision::class)->execute((int) $id);

            return ['reload' => true];
        });
    }

    public function release($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(ReleaseDecision::class)->execute((int) $id);

            return ['reload' => true];
        });
    }

    public function coordinate($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(CoordinateDecision::class)->execute((int) $id, $this->post_text('signature'));

            return ['reload' => true, 'message' => complaints_t('complaints_signed')];
        });
    }

    public function approve($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(ApproveDecision::class)->execute((int) $id, $this->post_text('signature'));

            return ['reload' => true, 'message' => complaints_t('complaints_approved')];
        });
    }
}
