<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'third_party/complaints/bootstrap.php';

use Complaints\Bootstrap\Container;
use Complaints\Bootstrap\ModuleConfig;
use Complaints\Bootstrap\ModuleContainer;

/**
 * Точка входа модуля «Жалобы» в CodeIgniter.
 *
 *     $this->load->library('complaints_module');
 *     $card = $this->complaints_module->get(ShowComplaint::class)->execute($id);
 *
 * Контейнер собирается один раз за запрос, при первом обращении.
 */
class Complaints_module
{
    private ?Container $container = null;

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function get(string $class): object
    {
        return $this->container()->get($class);
    }

    public function container(): Container
    {
        if ($this->container === null) {
            $ci = &get_instance();
            $ci->config->load('complaints');
            $this->container = ModuleContainer::build(
                ModuleConfig::fromArray((array) $ci->config->item('complaints')),
                $this->connection(),
                APPPATH . 'language',
                APPPATH . 'third_party/complaints/templates/pdf',
            );
        }

        return $this->container;
    }

    private function connection(): PDO
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса, если модуль должен работать
        // не через соединение php-activerecord по умолчанию. Модуль использует то же
        // PDO-соединение, что и модели проекта: транзакции проекта и модуля общие.
        return \ActiveRecord\ConnectionManager::get_connection()->connection;
    }
}
