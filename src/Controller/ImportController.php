<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ImportUrlFormType;
use App\Repository\WorkRepository;
use App\Scraper\ScraperRegistry;
use App\Scraper\ScrapingException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/import')]
#[IsGranted('ROLE_USER')]
class ImportController extends AbstractController
{
    public function __construct(
        private readonly ScraperRegistry $scraperRegistry,
        private readonly WorkRepository $workRepository,
    ) {
    }

    #[Route('', name: 'app_import', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $form = $this->createForm(ImportUrlFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{url: string} $data */
            $data = $form->getData();
            $url = $data['url'];

            $scraper = $this->scraperRegistry->getScraperForUrl($url);
            if ($scraper === null) {
                $this->addFlash('error', 'import.error.unsupported_url');

                return $this->render('import/url.html.twig', ['form' => $form]);
            }

            try {
                $scraped = $scraper->scrape($url);
            } catch (ScrapingException $e) {
                $this->addFlash('error', 'import.error.scrape_failed');

                return $this->render('import/url.html.twig', ['form' => $form]);
            }

            // Duplicate detection: if a work with this URL already exists, warn the user
            if ($scraped->sourceUrl !== null) {
                $existing = $this->workRepository->findByLink($scraped->sourceUrl);
                if ($existing !== null) {
                    $this->addFlash('warning', 'import.warning.duplicate');
                }
            }

            // Store DTO in session — WorkController reads it on the next GET and applies mapping
            $request->getSession()->set('import_scraped_work', $scraped);

            return $this->redirectToRoute('app_work_new');
        }

        return $this->render('import/url.html.twig', [
            'form' => $form,
        ]);
    }
}
