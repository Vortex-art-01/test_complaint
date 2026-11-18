<?php

declare(strict_types=1);

/*
 * Окружение CodeIgniter для тестов представлений: константы и get_instance().
 */

defined('BASEPATH') || define('BASEPATH', dirname(__DIR__, 5) . '/system/');
defined('APPPATH') || define('APPPATH', dirname(__DIR__, 4) . '/');

if (!function_exists('get_instance')) {
    function get_instance(): object
    {
        return Complaints\Tests\Web\CodeIgniterStub::$instance
            ?? throw new LogicException('CodeIgniterStub не создан.');
    }
}

require_once APPPATH . 'helpers/complaints_helper.php';
