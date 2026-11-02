<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

use function file_get_contents;
use function is_file;
use function json_decode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * Demo only: runs `wiki:convert-markdown <space> --dry-run --report` in process and answers the JSON report, so the
 * Playwright e2e can check the conversion dry run (nothing is written; a real host runs the command from a shell).
 */
final class ConversionDryRunController extends AbstractController
{
    #[Route('/demo/wiki/conversion-dry-run/{spaceSlug}', name: 'demo_wiki_conversion_dry_run', requirements: ['spaceSlug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function __invoke(KernelInterface $kernel, string $spaceSlug): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $report      = (string) tempnam(sys_get_temp_dir(), 'wiki-conversion-');
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $code   = $application->run(new ArrayInput([
            'command'     => 'wiki:convert-markdown',
            'spaces'      => [$spaceSlug],
            '--author-id' => 'demo@wiki.local',
            '--dry-run'   => true,
            '--report'    => $report,
        ]), $output);

        $json = is_file($report) ? json_decode((string) file_get_contents($report), true, 512, JSON_THROW_ON_ERROR) : null;
        @unlink($report);

        return new JsonResponse(['exit_code' => $code, 'console' => $output->fetch(), 'report' => $json]);
    }
}
