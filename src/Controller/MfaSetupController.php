<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\TotpSecretEncryptionService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mfa')]
#[IsGranted('ROLE_USER')]
class MfaSetupController extends AbstractController
{
    /** Session key used to hold the encrypted TOTP secret between totpEnable() and totpVerify(). */
    private const PENDING_TOTP_SECRET_KEY = 'pending_totp_secret';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
        private readonly TotpSecretEncryptionService $encryptionService,
    ) {
    }

    #[Route('', name: 'app_mfa_setup')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('mfa/setup.html.twig', [
            'totp_enabled' => $user->isTotpAuthenticationEnabled(),
            'email_enabled' => $user->isEmailAuthEnabled(),
        ]);
    }

    #[Route('/totp/enable', name: 'app_mfa_totp_enable', methods: ['GET'])]
    public function totpEnable(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isTotpAuthenticationEnabled()) {
            return $this->redirectToRoute('app_mfa_setup');
        }

        // Generate a fresh TOTP secret and store it encrypted in the session.
        // It is NOT saved to the database until the user verifies a code in totpVerify().
        $plainSecret = $this->totpAuthenticator->generateSecret();
        $request->getSession()->set(self::PENDING_TOTP_SECRET_KEY, $this->encryptionService->encrypt($plainSecret));

        // Set the plain secret transiently so getQRContent() can call getTotpAuthenticationConfiguration().
        $user->setDecryptedMfaSecret($plainSecret);
        $qrCodeContent = $this->totpAuthenticator->getQRContent($user);
        $qrSvg = $this->generateQrSvg($qrCodeContent);

        // Clear the transient secret — the QR is generated, and we must not leave it set
        // on the entity since the secret is not yet persisted.
        $user->setDecryptedMfaSecret(null);

        return $this->render('mfa/totp_enable.html.twig', [
            'qr_svg' => $qrSvg,
            'secret' => $plainSecret,
        ]);
    }

    #[Route('/totp/verify', name: 'app_mfa_totp_verify', methods: ['POST'])]
    public function totpVerify(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('mfa_totp_verify', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'security.csrf_invalid');

            return $this->redirectToRoute('app_mfa_setup');
        }

        /** @var User $user */
        $user = $this->getUser();

        $encryptedSecret = $request->getSession()->get(self::PENDING_TOTP_SECRET_KEY);
        if ($encryptedSecret === null) {
            // Session expired or user navigated directly here without going through enable.
            $this->addFlash('error', 'mfa.totp.session_expired');

            return $this->redirectToRoute('app_mfa_totp_enable');
        }

        $plainSecret = $this->encryptionService->decrypt((string) $encryptedSecret);

        // Set the decrypted secret transiently so checkCode() can call getTotpAuthenticationConfiguration().
        // The PostLoad listener found mfaSecret = NULL in the DB (not yet saved), so the decrypted
        // session value must be set here — otherwise TOTP verification will always fail.
        $user->setDecryptedMfaSecret($plainSecret);

        $code = $request->request->get('code', '');
        if (!$this->totpAuthenticator->checkCode($user, (string) $code)) {
            $user->setDecryptedMfaSecret(null);
            $this->addFlash('error', 'mfa.totp.invalid_code');

            return $this->redirectToRoute('app_mfa_totp_enable');
        }

        // Code verified — persist the encrypted secret and enable TOTP.
        $user->setMfaSecret((string) $encryptedSecret);

        $methods = $user->getMfaMethods() !== null ? explode(',', $user->getMfaMethods()) : [];
        if (!in_array('totp', $methods, true)) {
            $methods[] = 'totp';
        }
        $user->setMfaMethods(implode(',', $methods));
        $user->setIsMfaEnabled(true);
        $this->entityManager->flush();

        $request->getSession()->remove(self::PENDING_TOTP_SECRET_KEY);
        $this->addFlash('success', 'mfa.totp.enabled');

        return $this->redirectToRoute('app_mfa_setup');
    }

    #[Route('/totp/disable', name: 'app_mfa_totp_disable', methods: ['POST'])]
    public function totpDisable(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('mfa_totp_disable', $request->request->get('_token'))) {
            $this->addFlash('error', 'security.csrf_invalid');

            return $this->redirectToRoute('app_mfa_setup');
        }

        /** @var User $user */
        $user = $this->getUser();

        $methods = $user->getMfaMethods() !== null
            ? array_filter(explode(',', $user->getMfaMethods()), static fn (string $m) => $m !== 'totp')
            : [];

        $user->setMfaMethods($methods !== [] ? implode(',', $methods) : null);
        $user->setMfaSecret(null);
        $user->setDecryptedMfaSecret(null);

        if ($methods === []) {
            $user->setIsMfaEnabled(false);
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'mfa.totp.disabled');

        return $this->redirectToRoute('app_mfa_setup');
    }

    #[Route('/email/enable', name: 'app_mfa_email_enable', methods: ['POST'])]
    public function emailEnable(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('mfa_email_enable', $request->request->get('_token'))) {
            $this->addFlash('error', 'security.csrf_invalid');

            return $this->redirectToRoute('app_mfa_setup');
        }

        /** @var User $user */
        $user = $this->getUser();

        $methods = $user->getMfaMethods() !== null ? explode(',', $user->getMfaMethods()) : [];
        if (!in_array('email', $methods, true)) {
            $methods[] = 'email';
        }
        $user->setMfaMethods(implode(',', $methods));
        $user->setIsMfaEnabled(true);
        $this->entityManager->flush();

        $this->addFlash('success', 'mfa.email.enabled');

        return $this->redirectToRoute('app_mfa_setup');
    }

    #[Route('/email/disable', name: 'app_mfa_email_disable', methods: ['POST'])]
    public function emailDisable(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('mfa_email_disable', $request->request->get('_token'))) {
            $this->addFlash('error', 'security.csrf_invalid');

            return $this->redirectToRoute('app_mfa_setup');
        }

        /** @var User $user */
        $user = $this->getUser();

        $methods = $user->getMfaMethods() !== null
            ? array_filter(explode(',', $user->getMfaMethods()), static fn (string $m) => $m !== 'email')
            : [];

        $user->setMfaMethods($methods !== [] ? implode(',', $methods) : null);

        if ($methods === []) {
            $user->setIsMfaEnabled(false);
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'mfa.email.disabled');

        return $this->redirectToRoute('app_mfa_setup');
    }

    private function generateQrSvg(string $content): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd(),
        );
        $writer = new Writer($renderer);

        return $writer->writeString($content);
    }
}
