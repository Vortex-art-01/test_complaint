<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use Complaints\Application\Exception\SignatureRejected;

/**
 * Проверка ЭЦП на сервере.
 */
interface SignatureVerifier
{
    /**
     * Проверяет отсоединённую CMS-подпись над байтами документа: подпись
     * сходится с документом, сертификат действует и не отозван, ключ
     * предназначен для подписи.
     *
     * @param string $documentBytes байты подписанного PDF
     * @param string $cmsBase64     CMS-подпись в base64
     *
     * @throws SignatureRejected подпись не принята; причина — в ключе сообщения
     */
    public function verify(string $documentBytes, string $cmsBase64): VerifiedSignature;
}
