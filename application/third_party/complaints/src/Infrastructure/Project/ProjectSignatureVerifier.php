<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Project;

use Complaints\Application\Exception\SignatureRejected;
use Complaints\Application\Port\SignatureVerifier;
use Complaints\Application\Port\VerifiedSignature;

/**
 * Проверка ЭЦП НУЦ РК сервисом проекта. Подпись создаётся в браузере через
 * NCALayer (createCMSSignatureFromBase64, отсоединённая CMS) над байтами PDF,
 * которые модуль выдал для подписи.
 */
final class ProjectSignatureVerifier implements SignatureVerifier
{
    /**
     * @throws SignatureRejected
     */
    public function verify(string $documentBytes, string $cmsBase64): VerifiedSignature
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Тем же сервисом, что проверяет ЭЦП протоколов и заявок, проверить отсоединённую
        // CMS-подпись $cmsBase64 над байтами $documentBytes:
        //  - подпись математически верна для этих байтов;
        //  - сертификат НУЦ РК действовал на момент подписи и не отозван (OCSP/CRL);
        //  - ключ для подписи (RSA/GOST «Подписание»), а не для аутентификации.
        // Вернуть new VerifiedSignature(ФИО из CN, ИИН из SERIALNUMBER (12 цифр без «IIN»),
        //     БИН из OU (12 цифр без «BIN») или null для физлица, Subject целиком).
        // Отказ — throw new SignatureRejected('complaints_error_signature_invalid'),
        // для просроченного или отозванного сертификата — ключи
        // complaints_error_signature_expired и complaints_error_signature_revoked.
        throw ProjectServiceMissing::in(__METHOD__);
    }
}
