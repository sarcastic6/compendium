<?php

declare(strict_types=1);

namespace App\Validator;

use App\Service\PasswordValidationService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class StrongPasswordValidator extends ConstraintValidator
{
    public function __construct(private readonly PasswordValidationService $passwordValidationService)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!($constraint instanceof StrongPassword)) {
            throw new UnexpectedTypeException($constraint, StrongPassword::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        $errorKey = $this->passwordValidationService->validate((string) $value);

        if ($errorKey !== null) {
            $this->context->buildViolation($errorKey)
                ->setTranslationDomain('validators')
                ->addViolation();
        }
    }
}
