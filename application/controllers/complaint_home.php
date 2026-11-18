<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'libraries/Complaints_controller.php';

use Complaints\Application\Access\ModuleRole;
use Complaints\Application\Exception\NotAuthenticated;
use Complaints\Application\Port\CurrentUserProvider;

/**
 * Вход в раздел «Жалобы»: реестр по роли пользователя.
 */
class Complaint_home extends Complaints_controller
{
    public function index(): void
    {
        try {
            $user = $this->module(CurrentUserProvider::class)->currentUser();
        } catch (NotAuthenticated) {
            redirect('/' . complaints_language() . '/user/login');
        }

        $target = match (true) {
            $user->hasAnyRole(ModuleRole::Reviewer, ModuleRole::Coordinator, ModuleRole::Approver) => 'complaint_review/index',
            $user->hasRole(ModuleRole::Organizer) => 'complaint_execution/index',
            $user->hasRole(ModuleRole::Supplier) => 'complaint_supplier/index',
            default => null,
        };
        if ($target === null) {
            show_error(complaints_te('complaints_error_access_denied'), 403);
        }

        redirect(complaints_url($target));
    }
}
