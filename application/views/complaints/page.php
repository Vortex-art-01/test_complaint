<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Страница модуля вне общего макета портала: Bootstrap 3.1.1 и jQuery проекта.
 *
 * @var string $title
 * @var string $content
 */
$assets = (array) complaints_config('assets');
?>
<!DOCTYPE html>
<html lang="<?= complaints_e(complaints_language()) ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="<?= complaints_e(complaints_csrf_name()) ?>" content="<?= complaints_e(complaints_csrf_hash()) ?>">
    <title><?= complaints_e($title) ?></title>
    <link href="<?= complaints_e($assets['bootstrap_css'] ?? '') ?>" rel="stylesheet">
    <link href="<?= complaints_e($assets['portal_css'] ?? '') ?>" rel="stylesheet">
    <script src="<?= complaints_e($assets['jquery'] ?? '') ?>"></script>
    <script src="<?= complaints_e($assets['bootstrap_js'] ?? '') ?>"></script>
</head>
<body>
<div class="wrap">
    <div class="container-full">
        <div class="content-block">
            <?= $content ?>
        </div>
    </div>
</div>
</body>
</html>
