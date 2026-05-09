<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\ScrapeStatus;
use App\Export\DataDumpExportFormat;
use App\Export\FamiliarExportFormat;
use App\Message\ScrapeWorkMessage;
use App\Repository\WorkRepository;
use App\Service\BulkUrlImportService;
use App\Service\ReadingEntryExportService;
use App\Service\SpreadsheetImportService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/data')]
#[IsGranted('ROLE_USER')]
class DataController extends AbstractController
{
    public function __construct(
        private readonly ReadingEntryExportService $exportService,
        private readonly DataDumpExportFormat $dataDumpFormat,
        private readonly FamiliarExportFormat $familiarFormat,
        private readonly SpreadsheetImportService $spreadsheetImportService,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route('', name: 'app_data_index')]
    public function index(): Response
    {
        return $this->render('data/index.html.twig');
    }

    #[Route('/import', name: 'app_data_import', methods: ['GET'])]
    public function importForm(): Response
    {
        return $this->render('data/import.html.twig');
    }

    #[Route('/import', name: 'app_data_import_post', methods: ['POST'])]
    public function importProcess(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('data_import', $request->request->get('_token'))) {
            $this->addFlash('error', 'security.csrf_invalid');

            return $this->redirectToRoute('app_data_import');
        }

        $file = $request->files->get('import_file');
        if ($file === null) {
            $this->addFlash('error', 'data.import.error.no_file');

            return $this->redirectToRoute('app_data_import');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mimeType  = $file->getMimeType() ?? '';
        $validMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/octet-stream',
            'application/zip',
        ];

        if ($extension !== 'xlsx' || !in_array($mimeType, $validMimes, true)) {
            $this->addFlash('error', 'data.import.error.invalid_file');

            return $this->redirectToRoute('app_data_import');
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'compendium_import_');
        $file->move(dirname($tmpPath), basename($tmpPath));

        try {
            /** @var User $user */
            $user    = $this->getUser();
            $summary = $this->spreadsheetImportService->import($user, $tmpPath);
        } finally {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }

        return $this->render('data/import_result.html.twig', [
            'summary' => $summary,
        ]);
    }

    #[Route('/import/urls', name: 'app_data_import_urls', methods: ['GET'])]
    public function importUrlsForm(): Response
    {
        return $this->render('data/import_urls.html.twig');
    }

    #[Route('/import/urls', name: 'app_data_import_urls_post', methods: ['POST'])]
    public function importUrlsProcess(Request $request, BulkUrlImportService $bulkUrlImportService): Response
    {
        if (!$this->isCsrfTokenValid('data_import_urls', $request->request->get('_token'))) {
            $this->addFlash('error', 'security.csrf_invalid');

            return $this->redirectToRoute('app_data_import_urls');
        }

        $rawInput = $request->request->getString('urls');
        $summary  = $bulkUrlImportService->import($rawInput);

        return $this->render('data/import_urls_result.html.twig', [
            'summary' => $summary,
        ]);
    }

    #[Route('/scrape-status', name: 'app_data_scrape_status', methods: ['GET'])]
    public function scrapeStatus(WorkRepository $workRepository): Response
    {
        return $this->render('data/scrape_status.html.twig', [
            'pending' => $workRepository->findByScrapeStatus(ScrapeStatus::Pending),
            'failed'  => $workRepository->findByScrapeStatus(ScrapeStatus::Failed),
        ]);
    }

    #[Route('/scrape-status/clear-failed', name: 'app_data_scrape_clear_failed', methods: ['POST'])]
    public function clearFailedScrapes(Request $request, WorkRepository $workRepository): Response
    {
        if (!$this->isCsrfTokenValid('clear_failed_scrapes', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $workRepository->clearScrapeStatus(ScrapeStatus::Failed);

        $this->addFlash('success', 'data.scrape_status.cleared');

        return $this->redirectToRoute('app_data_scrape_status');
    }

    #[Route('/scrape-status/{id}/retry', name: 'app_data_scrape_retry', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function retryScrape(int $id, Request $request, WorkRepository $workRepository): Response
    {
        if (!$this->isCsrfTokenValid('retry_scrape_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $work = $workRepository->find($id);
        if ($work === null) {
            throw $this->createNotFoundException();
        }

        $link = $work->getLink();
        if ($link === null) {
            $this->addFlash('error', 'work.refresh.error.no_link');

            return $this->redirectToRoute('app_data_scrape_status');
        }

        $work->setScrapeStatus(ScrapeStatus::Pending);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new ScrapeWorkMessage($work->getId(), $link));

        $this->addFlash('success', 'data.scrape_status.retry_queued');

        return $this->redirectToRoute('app_data_scrape_status');
    }

    #[Route('/export/data-dump', name: 'app_data_export_data_dump')]
    public function exportDataDump(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $spreadsheet = $this->exportService->buildSpreadsheet($user, $this->dataDumpFormat);
        $writer = new Xlsx($spreadsheet);

        $filename = sprintf(
            'compendium-data-dump-export-%s.xlsx',
            (new DateTimeImmutable())->format('Y-m-d'),
        );

        // Write to a temp file so the content can be read back as a string.
        // This keeps the response testable (StreamedResponse content cannot be captured
        // by Symfony's KernelBrowser) and is appropriate for this app's export scale.
        $tmpFile = tempnam(sys_get_temp_dir(), 'compendium_export_');

        try {
            $writer->save($tmpFile);
            $content = (string) file_get_contents($tmpFile);
        } finally {
            @unlink($tmpFile);
        }

        $response = new Response($content, Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename),
        );
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    #[Route('/export/familiar', name: 'app_data_export_familiar')]
    public function exportFamiliar(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $spreadsheet = $this->exportService->buildSpreadsheet($user, $this->familiarFormat);
        $writer = new Xlsx($spreadsheet);

        $filename = sprintf(
            'compendium-familiar-export-%s.xlsx',
            (new DateTimeImmutable())->format('Y-m-d'),
        );

        $tmpFile = tempnam(sys_get_temp_dir(), 'compendium_export_');

        try {
            $writer->save($tmpFile);
            $content = (string) file_get_contents($tmpFile);
        } finally {
            @unlink($tmpFile);
        }

        $response = new Response($content, Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename),
        );
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
