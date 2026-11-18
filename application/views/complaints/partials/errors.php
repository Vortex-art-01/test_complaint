<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Блок ошибок и сообщений действий (заполняет complaints.js).
 *
 * @var string|null $error ошибка, известная при построении страницы
 */
?>
<div class="alert alert-danger complaints-errors" role="alert"<?= empty($error) ? ' style="display: none;"' : '' ?>><?= empty($error) ? '' : complaints_e($error) ?></div>
<div class="alert alert-success complaints-message" role="status" style="display: none;"></div>
