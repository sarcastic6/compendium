<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Export\DataDumpExportFormat;
use App\Service\ReadingEntryExportService;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {
    }

    #[Route('', name: 'app_data_index')]
    public function index(): Response
    {
        return $this->render('data/index.html.twig');
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
}
