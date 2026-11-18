<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\ApplicationException;
use Complaints\Application\Exception\NotAuthenticated;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Shared\UploadedFile;

/**
 * Общая часть контроллеров модуля «Жалобы».
 *
 * Права и правила проверяет ядро модуля в каждом сценарии; контроллер
 * разбирает запрос, вызывает сценарий и переводит ответ ядра в формат
 * портала: страница или JSON {status: 1|2, message, errors, redirect, data}.
 */
abstract class Complaints_controller extends CI_Controller
{
    protected const STATUS_OK = 1;
    protected const STATUS_ERROR = 2;

    public function __construct()
    {
        parent::__construct();
        $this->load->library('complaints_module');
        $this->load->helper(['url', 'complaints']);
        $this->config->load('complaints');
        $this->lang->load('complaints', complaints_language() === 'kz' ? 'kazakh' : 'russian');
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    protected function module(string $class): object
    {
        return $this->complaints_module->get($class);
    }

    /**
     * Страница модуля: ошибки ядра показываются страницей ошибки портала.
     *
     * @param callable(): array{view: string, title: string, data?: array<string, mixed>} $build
     */
    protected function screen(callable $build): void
    {
        try {
            $page = $build();
            $this->render($page['view'], $page['data'] ?? [], $page['title']);
        } catch (NotAuthenticated) {
            redirect('/' . complaints_language() . '/user/login');
        } catch (AccessDenied $error) {
            show_error(complaints_te($error->messageKey()), 403);
        } catch (NotFound $error) {
            show_error(complaints_te($error->messageKey()), 404);
        } catch (ApplicationException $error) {
            show_error(complaints_te($error->messageKey(), $error->params()), 409);
        } catch (Throwable $error) {
            log_message('error', 'Жалобы: ' . $error);
            show_error(complaints_te('complaints_error_integration'), 500);
        }
    }

    /**
     * Действие по кнопке: только POST (CSRF проверяет CodeIgniter), ответ — JSON.
     *
     * @param callable(): array<string, mixed> $action возвращает message, redirect, reload или data
     */
    protected function action(callable $action): void
    {
        if (strtoupper((string) $this->input->server('REQUEST_METHOD')) !== 'POST') {
            $this->json(['status' => self::STATUS_ERROR, 'message' => complaints_t('complaints_error_invalid_input')], 405);

            return;
        }

        try {
            $result = $action();
            $this->json(['status' => self::STATUS_OK] + $result + ['message' => complaints_t('complaints_done')]);
        } catch (ValidationFailed $error) {
            $errors = [];
            foreach ($error->errors() as $field => $message) {
                $errors[$field] = complaints_t($message['key'], $message['params']);
            }
            $this->json(['status' => self::STATUS_ERROR, 'message' => implode(' ', array_unique($errors)), 'errors' => $errors]);
        } catch (ApplicationException $error) {
            $this->json(['status' => self::STATUS_ERROR, 'message' => complaints_t($error->messageKey(), $error->params())]);
        } catch (Throwable $error) {
            log_message('error', 'Жалобы: ' . $error);
            $this->json(['status' => self::STATUS_ERROR, 'message' => complaints_t('complaints_error_integration')], 500);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data, string $title): void
    {
        $content = $this->load->view($view, $data, true);

        // ПРОЕКТ: заменить вызовом существующего сервиса, если у портала есть общий
        // макет страниц (шапка, меню кабинета, подвал): вывести $content и $title
        // внутри него. Модульный complaints/page подключает Bootstrap 3.1.1 и jQuery
        // по адресам из complaints[assets] и повторяет оформление портала.
        $this->load->view('complaints/page', ['title' => $title, 'content' => $content]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function json(array $payload, int $status = 200): void
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    /**
     * Положительное целое из POST; иначе — ошибка проверки поля.
     *
     * @throws ValidationFailed
     */
    protected function post_id(string $field): int
    {
        $value = $this->input->post($field);
        if (!is_string($value) || preg_match('/^[1-9]\d{0,18}$/', $value) !== 1) {
            throw ValidationFailed::field($field, 'complaints_error_required');
        }

        return (int) $value;
    }

    protected function post_optional_id(string $field): ?int
    {
        $value = $this->input->post($field);

        return is_string($value) && $value !== '' ? $this->post_id($field) : null;
    }

    protected function post_text(string $field): string
    {
        $value = $this->input->post($field);

        return is_string($value) ? $value : '';
    }

    /**
     * @return array<string|int, mixed>
     */
    protected function post_array(string $field): array
    {
        $value = $this->input->post($field);

        return is_array($value) ? $value : [];
    }

    /**
     * Загруженный файл из $_FILES.
     *
     * @throws ValidationFailed файл не загружен
     */
    protected function uploaded_file(string $field): UploadedFile
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw ValidationFailed::field($field, 'complaints_error_file_empty');
        }
        $bytes = file_get_contents((string) $file['tmp_name']);
        if ($bytes === false) {
            throw ValidationFailed::field($field, 'complaints_error_file_empty');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        return new UploadedFile(basename((string) $file['name']), is_string($mime) ? $mime : 'application/octet-stream', $bytes);
    }

    protected function page_number(mixed $page): int
    {
        return is_numeric($page) && (int) $page > 0 ? (int) $page : 1;
    }

    protected function query_text(string $field): ?string
    {
        $value = $this->input->get($field);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
