<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
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
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
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
    public function totpEnable(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isTotpAuthenticationEnabled()) {
            return $this->redirectToRoute('app_mfa_setup');
        }

        // Generate a fresh TOTP secret (not yet saved — only saved after verification)
        $secret = $this->totpAuthenticator->generateSecret();
        $user->setMfaSecret($secret);
        $this->entityManager->flush();

        $qrCodeContent = $this->totpAuthenticator->getQRContent($user);
        $qrSvg = $this->generateQrSvg($qrCodeContent);

        return $this->render('mfa/totp_enable.html.twig', [
            'qr_svg' => $qrSvg,
            'secret' => $secret,
        ]);
    }

    #[Route('/totp/verify', name: 'app_mfa_totp_verify', methods: ['POST'])]
    public function totpVerify(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $code = $request->request->get('code', '');

        if (!$this->totpAuthenticator->checkCode($user, (string) $code)) {
            $this->addFlash('error', 'mfa.totp.invalid_code');

            return $this->redirectToRoute('app_mfa_totp_enable');
        }

        $methods = $user->getMfaMethods() !== null ? explode(',', $user->getMfaMethods()) : [];
        if (!in_array('totp', $methods, true)) {
            $methods[] = 'totp';
        }
        $user->setMfaMethods(implode(',', $methods));
        $user->setIsMfaEnabled(true);
        $this->entityManager->flush();

        $this->addFlash('success', 'mfa.totp.enabled');

        return $this->redirectToRoute('app_mfa_setup');
    }

    #[Route('/totp/disable', name: 'app_mfa_totp_disable', methods: ['POST'])]
    public function totpDisable(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $methods = $user->getMfaMethods() !== null
            ? array_filter(explode(',', $user->getMfaMethods()), static fn (string $m) => $m !== 'totp')
            : [];

        $user->setMfaMethods($methods !== [] ? implode(',', $methods) : null);
        $user->setMfaSecret(null);

        if ($methods === []) {
            $user->setIsMfaEnabled(false);
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'mfa.totp.disabled');

        return $this->redirectToRoute('app_mfa_setup');
    }

    #[Route('/email/enable', name: 'app_mfa_email_enable', methods: ['POST'])]
    public function emailEnable(): Response
    {
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
    public function emailDisable(): Response
    {
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
