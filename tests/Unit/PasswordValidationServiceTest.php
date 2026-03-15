<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\PasswordValidationService;
use PHPUnit\Framework\TestCase;

class PasswordValidationServiceTest extends TestCase
{
    private PasswordValidationService $service;

    protected function setUp(): void
    {
        $this->service = new PasswordValidationService();
    }

    public function test_short_password_is_invalid(): void
    {
        $result = $this->service->validate('short');

        $this->assertSame('auth.password.too_short', $result);
    }

    public function test_exactly_minimum_length_common_password_is_invalid(): void
    {
        // 'password' is 8 chars but extremely common — zxcvbn score < 2
        $result = $this->service->validate('password');

        $this->assertSame('auth.password.too_common', $result);
    }

    public function test_common_longer_password_is_invalid(): void
    {
        $result = $this->service->validate('password123');

        $this->assertNotNull($result);
    }

    public function test_strong_password_is_valid(): void
    {
        $result = $this->service->validate('CorrectHorse99!');

        $this->assertNull($result);
    }

    public function test_is_valid_returns_true_for_strong_password(): void
    {
        $this->assertTrue($this->service->isValid('CorrectHorse99!'));
    }

    public function test_is_valid_returns_false_for_short_password(): void
    {
        $this->assertFalse($this->service->isValid('abc'));
    }

    public function test_is_valid_returns_false_for_common_password(): void
    {
        $this->assertFalse($this->service->isValid('password'));
    }
}
