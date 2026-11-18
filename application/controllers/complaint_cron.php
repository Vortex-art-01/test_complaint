<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Complaints\Application\Collection\CollectSubmittedComplaints;
use Complaints\Application\Execution\UseCase\CompleteLotRevisions;
use Complaints\Application\Notification\SendDeadlineReminders;

/**
 * Задачи cron модуля — только из командной строки (Linux):
 *   php index.php complaint_cron collect            каждые 5 минут
 *   php index.php complaint_cron complete_revisions каждые 15 минут
 *   php index.php complaint_cron remind             раз в сутки, 09:00
 */
class Complaint_cron extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->input->is_cli_request()) {
            show_404();
        }
        $this->load->library('complaints_module');
    }

    public function collect(): void
    {
        $report = $this->complaints_module->get(CollectSubmittedComplaints::class)->execute();
        echo sprintf('Решений создано: %d, жалоб собрано: %d', $report->decisionsCreated, $report->complaintsCollected), PHP_EOL;
        foreach ($report->skipped as $line) {
            echo $line, PHP_EOL;
        }
    }

    public function complete_revisions(): void
    {
        $count = $this->complaints_module->get(CompleteLotRevisions::class)->execute();
        echo sprintf('Лотов с завершённым пересмотром: %d', $count), PHP_EOL;
    }

    public function remind(): void
    {
        $count = $this->complaints_module->get(SendDeadlineReminders::class)->execute();
        echo sprintf('Напоминаний отправлено: %d', $count), PHP_EOL;
    }
}
