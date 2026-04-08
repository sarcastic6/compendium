<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Export\DataDumpExportFormat;
use App\Export\FamiliarExportFormat;
use App\Service\ReadingEntryExportService;
use App\Service\SpreadsheetImportService;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
