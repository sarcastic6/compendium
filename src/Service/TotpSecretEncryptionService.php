<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Encrypts and decrypts TOTP secrets at rest using sodium_crypto_secretbox (XSalsa20-Poly1305).
 *
 * The encryption key is a 32-byte secret provided via the TOTP_ENCRYPTION_KEY environment variable
 * (base64-encoded). Ciphertext is stored as base64(nonce || ciphertext) in the database.
 *
 * Generate a key: php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
 */
class TotpSecretEncryptionService
{
    private string $key;

    public function __construct(string $encryptionKey)
    {
        if (strlen($encryptionKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException(
                sprintf(
                    'TOTP_ENCRYPTION_KEY must decode to exactly %d bytes. Generate with: php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"',
                    SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
                ),
            );
        }

        $this->key = $encryptionKey;
    }

    public function encrypt(string $plainSecret): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plainSecret, $nonce, $this->key);

        return base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encrypted): string
    {
        $decoded = base64_decode($encrypted, true);
        if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Invalid encrypted TOTP secret format.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);
        if ($plain === false) {
            throw new \RuntimeException('Failed to decrypt TOTP secret. The encryption key may have changed.');
        }

        return $plain;
    }
}
