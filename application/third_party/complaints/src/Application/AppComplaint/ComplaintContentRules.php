<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint;

use Complaints\Application\Exception\ValidationErrors;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Shared\ComplaintSettings;

/**
 * Проверка полей «Суть жалобы»: телефон и описание.
 */
final class ComplaintContentRules
{
    private const PHONE_PATTERN = '/^\+?[0-9][0-9 ()\-]{4,29}$/';

    public function __construct(
        private readonly ComplaintSettings $settings,
    ) {
    }

    /**
     * @param bool $requireFilled true — перед отправкой на подпись поля обязательны
     *
     * @throws ValidationFailed
     */
    public function check(?string $contactPhone, ?string $description, bool $requireFilled): void
    {
        $errors = new ValidationErrors();
        $contactPhone = trim((string) $contactPhone);
        $description = trim((string) $description);

        if ($contactPhone === '') {
            if ($requireFilled) {
                $errors->add('contact_phone', 'complaints_error_required');
            }
        } elseif (preg_match(self::PHONE_PATTERN, $contactPhone) !== 1) {
            $errors->add('contact_phone', 'complaints_error_phone_format');
        }

        if ($description === '') {
            if ($requireFilled) {
                $errors->add('description', 'complaints_error_required');
            }
        } elseif (mb_strlen($description) > $this->settings->descriptionMaxLength) {
            $errors->add('description', 'complaints_error_description_length', [
                'max' => (string) $this->settings->descriptionMaxLength,
            ]);
        }

        $errors->throwIfAny();
    }

    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
