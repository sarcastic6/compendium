<?php

declare(strict_types=1);

namespace App\Service;

use ZxcvbnPhp\Zxcvbn;

class PasswordValidationService
{
    private const MIN_LENGTH = 8;
    /** ZxcvbnPhp score threshold (0-4). Score < 2 means the password is too common/weak. */
    private const MIN_ZXCVBN_SCORE = 2;

    public function validate(string $password): ?string
    {
        if (strlen($password) < self::MIN_LENGTH) {
            return 'auth.password.too_short';
        }

        $zxcvbn = new Zxcvbn();
        $result = $zxcvbn->passwordStrength($password);

        if ($result['score'] < self::MIN_ZXCVBN_SCORE) {
            return 'auth.password.too_common';
        }

        return null;
    }

    public function isValid(string $password): bool
    {
        return $this->validate($password) === null;
    }
}
