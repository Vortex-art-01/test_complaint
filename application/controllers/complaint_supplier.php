<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'libraries/Complaints_controller.php';

use Complaints\Application\AppComplaint\ComplaintAction;
use Complaints\Application\AppComplaint\UseCase\AddComplaintAttachment;
use Complaints\Application\AppComplaint\UseCase\ChangeDraftHead;
use Complaints\Application\AppComplaint\UseCase\ChangeDraftLot;
use Complaints\Application\AppComplaint\UseCase\CreateComplaintDraft;
use Complaints\Application\AppComplaint\UseCase\CreateComplaintDraftCommand;
use Complaints\Application\AppComplaint\UseCase\DeleteComplaint;
use Complaints\Application\AppComplaint\UseCase\FindComplainableBuy;
use Complaints\Application\AppComplaint\UseCase\ListOrganizationComplaints;
use Complaints\Application\AppComplaint\UseCase\ListSupplierHeads;
use Complaints\Application\AppComplaint\UseCase\RemoveComplaintAttachment;
use Complaints\Application\AppComplaint\UseCase\ReturnComplaintToDraft;
use Complaints\Application\AppComplaint\UseCase\SendComplaintForSignature;
use Complaints\Application\AppComplaint\UseCase\ShowComplainableBuy;
use Complaints\Application\AppComplaint\UseCase\ShowComplaint;
use Complaints\Application\AppComplaint\UseCase\SignComplaint;
use Complaints\Application\AppComplaint\UseCase\SubmitComplaint;
use Complaints\Application\AppComplaint\UseCase\UpdateComplaintDraft;
use Complaints\Application\AppComplaint\UseCase\UpdateComplaintDraftCommand;
use Complaints\Application\AppComplaint\UseCase\WithdrawComplaint;
use Complaints\Application\Exception\ValidationFailed;

/**
 * Жалобы поставщика: реестр, подача (шаги 1–2), черновик, подпись руководителя,
 * подача, отзыв и ответ органа.
 */
class Complaint_supplier extends Complaints_controller
{
    public function index($page = 1): void
    {
        $this->screen(fn (): array => [
            'view' => 'complaints/supplier/index',
            'title' => complaints_t('complaints_title_supplier_registry'),
            'data' => ['page' => $this->module(ListOrganizationComplaints::class)->execute($this->page_number($page))],
        ]);
    }

    /**
     * Шаг 1: поиск закупки по номеру объявления и выбор лота своей заявки.
     */
    public function create(): void
    {
        $this->screen(function (): array {
            $number = $this->query_text('number');
            $buy = null;
            $error = null;
            if ($number !== null) {
                try {
                    $buy = $this->module(FindComplainableBuy::class)->execute($number);
                } catch (ValidationFailed $failed) {
                    $error = complaints_t($failed->messageKey(), $failed->params());
                }
            }

            return [
                'view' => 'complaints/supplier/create',
                'title' => complaints_t('complaints_title_create'),
                'data' => [
                    'number' => (string) $number,
                    'buy' => $buy,
                    'error' => $error,
                    'heads' => $buy === null ? [] : $this->module(ListSupplierHeads::class)->execute(),
                ],
            ];
        });
    }

    /**
     * Шаг 2: черновик по выбранному лоту и руководителю.
     */
    public function create_draft(): void
    {
        $this->action(function (): array {
            $id = $this->module(CreateComplaintDraft::class)->execute(new CreateComplaintDraftCommand(
                $this->post_id('buy_id'),
                $this->post_id('app_lot_id'),
                $this->post_id('head_user_id'),
            ));

            return ['redirect' => complaints_url('complaint_supplier/edit/' . $id)];
        });
    }

    public function edit($id = 0): void
    {
        $this->screen(function () use ($id): array {
            $card = $this->module(ShowComplaint::class)->execute((int) $id);
            if (!$card->allows(ComplaintAction::Edit)) {
                redirect(complaints_url('complaint_supplier/show/' . $card->complaint->id));
            }

            try {
                $buy = $this->module(ShowComplainableBuy::class)->execute($card->complaint->buyId);
            } catch (ValidationFailed) {
                $buy = null;
            }

            return [
                'view' => 'complaints/supplier/edit',
                'title' => complaints_t('complaints_title_edit', ['number' => $card->complaint->number->value]),
                'data' => [
                    'card' => $card,
                    'buy' => $buy,
                    'heads' => $this->module(ListSupplierHeads::class)->execute(),
                    'descriptionMaxLength' => (int) complaints_config('description_max_length'),
                ],
            ];
        });
    }

    public function show($id = 0): void
    {
        $this->screen(function () use ($id): array {
            $card = $this->module(ShowComplaint::class)->execute((int) $id);

            return [
                'view' => 'complaints/supplier/show',
                'title' => complaints_t('complaints_title_complaint', ['number' => $card->complaint->number->value]),
                'data' => ['card' => $card],
            ];
        });
    }

    public function save($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(UpdateComplaintDraft::class)->execute(new UpdateComplaintDraftCommand(
                (int) $id,
                $this->post_text('contact_phone'),
                $this->post_text('description'),
            ));

            return ['message' => complaints_t('complaints_saved')];
        });
    }

    public function change_lot($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(ChangeDraftLot::class)->execute((int) $id, $this->post_id('app_lot_id'));

            return ['reload' => true];
        });
    }

    public function change_head($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(ChangeDraftHead::class)->execute((int) $id, $this->post_id('head_user_id'));

            return ['message' => complaints_t('complaints_saved')];
        });
    }

    public function attach($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(AddComplaintAttachment::class)->execute((int) $id, $this->uploaded_file('file'));

            return ['reload' => true];
        });
    }

    public function detach($id = 0, $attachmentId = 0): void
    {
        $this->action(function () use ($id, $attachmentId): array {
            $this->module(RemoveComplaintAttachment::class)->execute((int) $id, (int) $attachmentId);

            return ['reload' => true];
        });
    }

    public function send_for_signature($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(SendComplaintForSignature::class)->execute((int) $id);

            return ['redirect' => complaints_url('complaint_supplier/show/' . (int) $id)];
        });
    }

    /**
     * Подпись руководителя: CMS из NCALayer над байтами PDF (complaint_files/source).
     */
    public function sign($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(SignComplaint::class)->execute((int) $id, $this->post_text('signature'));

            return ['reload' => true, 'message' => complaints_t('complaints_signed')];
        });
    }

    public function return_to_draft($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(ReturnComplaintToDraft::class)->execute((int) $id);

            return ['redirect' => complaints_url('complaint_supplier/edit/' . (int) $id)];
        });
    }

    public function submit($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(SubmitComplaint::class)->execute((int) $id);

            return ['reload' => true, 'message' => complaints_t('complaints_submitted')];
        });
    }

    public function withdraw($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(WithdrawComplaint::class)->execute((int) $id);

            return ['reload' => true, 'message' => complaints_t('complaints_withdrawn')];
        });
    }

    public function delete($id = 0): void
    {
        $this->action(function () use ($id): array {
            $this->module(DeleteComplaint::class)->execute((int) $id);

            return ['redirect' => complaints_url('complaint_supplier/index')];
        });
    }
}
