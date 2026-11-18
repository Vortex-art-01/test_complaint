<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * Модуль «Жалобы». Значения с пометкой ПРОЕКТ нужно сверить с проектом
 * до первого запуска: модуль проверяет конфиг и не стартует с неверными значениями.
 */
$config['complaints'] = [
    'time_zone' => 'Asia/Almaty',

    // Сроки в рабочих днях: обжалование, рассмотрение, исполнение решения.
    'appeal_working_days' => 3,
    'review_working_days' => 3,
    'execution_working_days' => 2,

    // ПРОЕКТ: id способов закупки, которые обжалуются в модуле (test_buy.method_id; 2 — тендер).
    'complainable_method_ids' => [2],

    // БИН организаторов, закупки которых обжалуются в модуле (ТОО «СК-Фармация»).
    'organizer_bins' => ['090340007747'],

    // ПРОЕКТ: id организации уполномоченного органа, рассматривающего жалобы.
    'authority_org_id' => 0,

    'description_max_length' => 500,
    'attachment_max_bytes' => 20 * 1024 * 1024,
    'attachment_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'rar'],
    'attachment_max_count' => 10,

    // Сбор жалоб запускается cron; поданные в последние минуты срока жалобы
    // собираются не раньше, чем через столько минут после окончания срока.
    'collection_grace_minutes' => 5,
    'page_size' => 20,

    // ПРОЕКТ: сверить с постановлением Правительства РК о переносе выходных дней на год.
    // Праздник в выходной переносится на следующий рабочий день (Трудовой кодекс РК),
    // религиозные праздники не переносятся.
    'holidays' => [
        '2026-01-01', '2026-01-02', '2026-01-07', '2026-03-09', '2026-03-23', '2026-03-24',
        '2026-03-25', '2026-05-01', '2026-05-07', '2026-05-11', '2026-05-27', '2026-07-06',
        '2026-08-31', '2026-10-26', '2026-12-16',
        '2027-01-01', '2027-01-04', '2027-01-07', '2027-03-08', '2027-03-22', '2027-03-23',
        '2027-03-24', '2027-05-03', '2027-05-07', '2027-05-10', '2027-07-06', '2027-08-30',
        '2027-10-25', '2027-12-16',
    ],
    // Рабочие субботы и воскресенья по постановлению о переносе.
    'working_days' => [],

    // ПРОЕКТ: ключи — коды ролей проекта, как их возвращает
    // ProjectCurrentUserProvider::projectRolesOf(); значения — роли модуля.
    'role_map' => [
        'supplier' => ['supplier'],
        'supplier_head' => ['supplier', 'supplier_head'],
        'complaints_reviewer' => ['reviewer'],
        'complaints_coordinator' => ['coordinator'],
        'complaints_approver' => ['approver'],
        'organizer' => ['organizer'],
    ],

    // Статусы заявки и лота заявки, дающие право на жалобу; пустой список — любой статус.
    // ПРОЕКТ: имена столбцов статуса в test_app и test_app_lots.
    'eligibility' => [
        'app_status_column' => 'status_id',
        'app_statuses' => [],
        'app_lot_status_column' => 'status_id',
        'app_lot_statuses' => [],
    ],

    // 'project' — генератор PDF проекта (Complaints\Infrastructure\Pdf\ProjectHtmlToPdf),
    // 'mpdf' — mPDF 7+, если он уже установлен в проекте.
    'pdf_converter' => 'project',
    'pdf_work_directory' => APPPATH . 'cache/complaints_pdf',

    // ПРОЕКТ: имена таблиц и столбцов проекта, которые читает модуль.
    'project_schema' => [
        'buy' => [
            'table' => 'test_buy',
            'number' => 'number_anno',
            'name_ru' => 'name_ru',
            'name_kz' => 'name_kz',
            'method' => 'method_id',
            'organizer' => 'organizer_org_id',
            'published_at' => 'published_at',
        ],
        'buy_lot' => [
            'table' => 'test_buy_lots',
            'buy' => 'buy_id',
            'number' => 'lot_number',
            'name_ru' => 'name_ru',
            'name_kz' => 'name_kz',
        ],
        'app' => ['table' => 'test_app', 'supplier' => 'supplier_org_id'],
        'app_lot' => ['table' => 'test_app_lots'],
    ],

    // ПРОЕКТ: адреса Bootstrap 3.1.1, jQuery 1.10.2 и стилей портала (для страниц модуля
    // вне общего макета) и файлов модуля из каталога assets/complaints.
    'assets' => [
        'bootstrap_css' => '/css/bootstrap.min.css',
        'portal_css' => '/css/style.css',
        'jquery' => '/js/jquery.js',
        'bootstrap_js' => '/js/bootstrap.min.js',
        'module_css' => '/assets/complaints/css/complaints.css',
        'module_js' => '/assets/complaints/js/complaints.js',
        'signer_js' => '/assets/complaints/js/complaints.signer.js',
    ],

    // Свои адаптеры портов вместо адаптеров модуля: интерфейс => класс.
    // Пример: Complaints\Application\Port\Notifier::class => My_complaints_notifier::class.
    'adapters' => [],
];
