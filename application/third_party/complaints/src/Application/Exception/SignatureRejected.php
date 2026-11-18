<?php

declare(strict_types=1);

namespace Complaints\Application\Exception;

/**
 * Подпись ЭЦП не принята: недействительна, не того подписанта или не над тем документом.
 */
final class SignatureRejected extends ApplicationException
{
    /**
     * @param array<string, scalar> $params
     */
    public function __construct(string $messageKey = 'complaints_error_signature_invalid', array $params = [])
    {
        parent::__construct($messageKey, $params);
    }
}
