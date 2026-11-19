<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'libraries/Complaints_controller.php';

use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\NotAuthenticated;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\File\FileKind;
use Complaints\Application\File\UseCase\DownloadFile;
use Complaints\Application\File\UseCase\GetSigningSource;

/**
 * Файлы модуля: скачивание с проверкой прав и выдача документа для подписи ЭЦП.
 * Конфиденциальные файлы не отдаются по общим ссылкам хранилища.
 */
class Complaint_files extends Complaints_controller
{
    /**
     * /{lang}/complaint_files/download/{document|attachment}/{id}; id — id файла хранилища
     */
    public function download($kind = '', $id = 0): void
    {
        $fileKind = FileKind::tryFrom((string) $kind);
        if ($fileKind === null) {
            show_404();
        }

        try {
            $file = $this->module(DownloadFile::class)->execute($fileKind, (int) $id);
        } catch (NotAuthenticated) {
            redirect('/' . complaints_language() . '/user/login');
        } catch (AccessDenied) {
            show_error(complaints_te('complaints_error_access_denied'), 403);
        } catch (NotFound) {
            show_404();
        } catch (Throwable $error) {
            log_message('error', 'Жалобы: ' . $error);
            show_error(complaints_te('complaints_error_integration'), 500);
        }

        $name = $file->description->fileName;
        $this->output
            ->set_content_type($file->description->mimeType)
            ->set_header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name) . '"; filename*=UTF-8\'\'' . rawurlencode($name))
            ->set_header('Content-Length: ' . strlen($file->bytes))
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_header('Cache-Control: private, no-store')
            ->set_output($file->bytes);
    }

    /**
     * Байты действующей версии документа в base64 для NCALayer (только ожидаемому подписанту).
     */
    public function source($documentId = 0): void
    {
        $this->action(fn (): array => [
            'message' => '',
            'data' => ['document' => $this->module(GetSigningSource::class)->execute((int) $documentId)],
        ]);
    }
}
