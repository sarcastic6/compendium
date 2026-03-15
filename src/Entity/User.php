<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface as EmailTwoFactorInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uq_user_email', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EmailTwoFactorInterface, TotpTwoFactorInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    /** Stored as VARCHAR for SQLite/MySQL/PostgreSQL compatibility. Valid values enforced via PHP enum. */
    #[ORM\Column(type: 'string', length: 20, enumType: UserRole::class)]
    private UserRole $role = UserRole::User;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDisabled = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isMfaEnabled = false;

    /** TOTP secret, encrypted at rest using sodium_crypto_secretbox. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mfaSecret = null;

    /** Comma-separated list of enabled MFA methods, e.g. "email,totp". */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mfaMethods = null;

    /** Temporary email auth code for 2FA — single use, cleared after verification. */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $emailAuthCode = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $name, string $email, string $passwordHash)
    {
        $this->name = $name;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return match ($this->role) {
            UserRole::Admin => ['ROLE_ADMIN', 'ROLE_USER'],
            UserRole::User => ['ROLE_USER'],
        };
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function eraseCredentials(): void
    {
        // No transient plain-text password stored
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function setRole(UserRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function isDisabled(): bool
    {
        return $this->isDisabled;
    }

    public function setIsDisabled(bool $isDisabled): static
    {
        $this->isDisabled = $isDisabled;

        return $this;
    }

    public function isMfaEnabled(): bool
    {
        return $this->isMfaEnabled;
    }

    public function setIsMfaEnabled(bool $isMfaEnabled): static
    {
        $this->isMfaEnabled = $isMfaEnabled;

        return $this;
    }

    public function getMfaSecret(): ?string
    {
        return $this->mfaSecret;
    }

    public function setMfaSecret(?string $mfaSecret): static
    {
        $this->mfaSecret = $mfaSecret;

        return $this;
    }

    public function getMfaMethods(): ?string
    {
        return $this->mfaMethods;
    }

    public function setMfaMethods(?string $mfaMethods): static
    {
        $this->mfaMethods = $mfaMethods;

        return $this;
    }

    // --- EmailTwoFactorInterface ---

    public function isEmailAuthEnabled(): bool
    {
        return $this->mfaMethods !== null
            && in_array('email', explode(',', $this->mfaMethods), true);
    }

    public function getEmailAuthRecipient(): string
    {
        return $this->email;
    }

    public function getEmailAuthCode(): ?string
    {
        return $this->emailAuthCode;
    }

    public function setEmailAuthCode(string $authCode): void
    {
        $this->emailAuthCode = $authCode;
    }

    // --- TotpTwoFactorInterface ---

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->mfaMethods !== null
            && in_array('totp', explode(',', $this->mfaMethods), true)
            && $this->mfaSecret !== null;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if ($this->mfaSecret === null) {
            return null;
        }

        return new TotpConfiguration($this->mfaSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
