<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TotpSecretEncryptionService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TOTP secret encryption at rest.
 *
 * Covers: Fix 2 — TOTP secrets encrypted via sodium_crypto_secretbox.
 */
class TotpSecretEncryptionServiceTest extends TestCase
{
    private TotpSecretEncryptionService $service;

    protected function setUp(): void
    {
        // 32-byte test key — safe for tests, never used in production.
        $this->service = new TotpSecretEncryptionService(str_repeat('a', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }

    public function test_encrypt_decrypt_roundtrip(): void
    {
        $plain = 'JBSWY3DPEHPK3PXP';

        $decrypted = $this->service->decrypt($this->service->encrypt($plain));

        $this->assertSame($plain, $decrypted);
    }

    public function test_encrypted_output_differs_from_plaintext(): void
    {
        $plain = 'JBSWY3DPEHPK3PXP';

        $encrypted = $this->service->encrypt($plain);

        $this->assertNotSame($plain, $encrypted);
    }

    public function test_decrypt_fails_on_tampered_ciphertext(): void
    {
        $encrypted = $this->service->encrypt('JBSWY3DPEHPK3PXP');

        // Flip the last byte of the base64-decoded blob, then re-encode.
        $decoded = base64_decode($encrypted, true);
        assert($decoded !== false);
        $decoded[-1] = chr(ord($decoded[-1]) ^ 0xFF);
        $tampered = base64_encode($decoded);

        $this->expectException(\RuntimeException::class);
        $this->service->decrypt($tampered);
    }

    public function test_decrypt_fails_on_wrong_key(): void
    {
        $encrypted = $this->service->encrypt('JBSWY3DPEHPK3PXP');

        $otherService = new TotpSecretEncryptionService(str_repeat('b', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));

        $this->expectException(\RuntimeException::class);
        $otherService->decrypt($encrypted);
    }

    public function test_each_encryption_produces_unique_output(): void
    {
        $plain = 'JBSWY3DPEHPK3PXP';

        $first = $this->service->encrypt($plain);
        $second = $this->service->encrypt($plain);

        // Each call uses a fresh random nonce, so ciphertexts must differ.
        $this->assertNotSame($first, $second);
    }
}
