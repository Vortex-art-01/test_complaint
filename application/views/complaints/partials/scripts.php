<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Стили и скрипты модуля; подключаются в конце каждого экрана модуля,
 * поэтому работают и внутри общего макета портала.
 */
$assets = (array) complaints_config('assets');
$messages = [
    'confirm' => complaints_t('complaints_confirm'),
    'requestFailed' => complaints_t('complaints_error_request'),
    'signerUnavailable' => complaints_t('complaints_error_ncalayer'),
    'signerCancelled' => complaints_t('complaints_signing_cancelled'),
    'signing' => complaints_t('complaints_signing'),
    'left' => complaints_t('complaints_chars_left'),
];
?>
<link href="<?= complaints_e($assets['module_css'] ?? '') ?>" rel="stylesheet">
<script>
    window.ComplaintsConfig = <?= json_encode([
        'csrfName' => complaints_csrf_name(),
        'messages' => $messages,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= complaints_e($assets['signer_js'] ?? '') ?>"></script>
<script src="<?= complaints_e($assets['module_js'] ?? '') ?>"></script>
