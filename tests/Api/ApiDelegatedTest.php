<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Api\Operation\WikiApiDelegatedOperations;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Tests\Api\App\ApiAuditRecorder;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

use function array_filter;
use function array_map;
use function array_values;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;

/**
 * 1.24 (spec 218 FR-033, FR-037 – FR-039): wiki lifecycle and files, through the host's hooks.
 */
final class ApiDelegatedTest extends ApiTestCase
{
    public function testWikiLifecycleAtFull(): void
    {
        $created = $this->call('POST', '/wikis', self::ANA, ['owner_type' => 'project', 'owner_id' => 'owner-1', 'name' => 'Handbook', 'description' => 'How we work']);
        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
        self::assertSame('handbook', $this->json($created)['slug']);
        $this->assertRefusal($this->call('POST', '/wikis', self::ANA, ['owner_type' => 'project', 'owner_id' => 'owner-1', 'name' => 'Handbook']), 409, 'WIKI_003');
        $this->assertRefusal($this->call('POST', '/wikis', self::ANA, ['owner_type' => 'project', 'owner_id' => 'owner-1', 'name' => ' ']), 400, 'INPUT_002');

        self::assertSame('Engineering 2', $this->json($this->call('PATCH', '/wikis/eng', self::ANA, ['name' => 'Engineering 2']))['name']);
        self::assertTrue($this->json($this->call('POST', '/wikis/eng/archive', self::ANA))['archived']);
        $this->assertRefusal($this->call('PATCH', '/wikis/eng', self::ANA, ['name' => 'x']), 403, 'WIKI_002');
        self::assertFalse($this->json($this->call('POST', '/wikis/eng/unarchive', self::ANA))['archived']);
        // An unopened wiki: the level alone, the host decides.
        self::assertTrue($this->json($this->call('POST', '/wikis/handbook/archive', self::ANA))['archived']);

        self::assertSame(['wiki.create', 'wiki.update', 'wiki.archive', 'wiki.unarchive', 'wiki.archive'], array_map(static fn ($e): string => $e->operation, ApiAuditRecorder::$events));
        self::assertSame(['name'], ApiAuditRecorder::$events[1]->changedFields);
    }

    public function testWikiLifecycleRefusals(): void
    {
        $this->token('editor', 'u-ana', WikiApiLevel::Edit);
        $this->assertRefusal($this->call('POST', '/wikis', 'editor', ['owner_type' => 'project', 'owner_id' => 'owner-1', 'name' => 'x']), 403, 'TOKEN_001');
        $this->assertRefusal($this->call('PATCH', '/wikis/eng', 'editor', ['name' => 'x']), 403, 'TOKEN_001');
        ApiTestHost::$wikis['new'] = ['unopened' => true];
        $this->assertRefusal($this->call('POST', '/wikis/new/archive', 'editor'), 403, 'TOKEN_001');
        $this->token('bob', 'u-bob', WikiApiLevel::Full);
        $this->assertRefusal($this->call('POST', '/wikis/eng/archive', 'bob'), 403, 'ACCESS_002');
        $this->assertRefusal($this->call('POST', '/wikis/ops/archive', 'bob'), 404, 'ACCESS_001');
    }

    public function testAWikiListedByNameOnlyIsNotManagedAndCreatesStayInReach(): void
    {
        // A restricted wiki the token names but the person cannot open: 404, as reads (audit IF-L7).
        ApiTestHost::$wikis['eng']['restricted'] = true;
        ApiTestHost::$wikis['eng']['can_open']   = false;
        $this->token('named', 'u-ana', WikiApiLevel::Full, WikiApiWhere::limitedTo([], ['eng']));
        foreach ([['PATCH', '/wikis/eng', ['name' => 'x']], ['POST', '/wikis/eng/archive', null], ['POST', '/wikis/eng/unarchive', null]] as [$method, $path, $body]) {
            $this->assertRefusal($this->call($method, $path, 'named', $body), 404, 'ACCESS_001');
        }

        // A token limited to owner-1 cannot create under owner-2: refused before the hook, nothing created (IF-L8).
        $this->token('owner-1', 'u-ana', WikiApiLevel::Full, WikiApiWhere::limitedTo(['owner-1'], []));
        $this->assertRefusal($this->call('POST', '/wikis', 'owner-1', ['owner_type' => 'project', 'owner_id' => 'owner-2', 'name' => 'Elsewhere']), 404, 'ACCESS_001');
        $this->assertRefusal($this->call('POST', '/wikis', 'owner-1', ['owner_type' => 'project', 'owner_id' => 'nobody', 'name' => 'Nowhere']), 404, 'ACCESS_001');
        self::assertArrayNotHasKey('elsewhere', ApiTestHost::$wikis);
        self::assertSame(201, $this->call('POST', '/wikis', 'owner-1', ['owner_type' => 'project', 'owner_id' => 'owner-1', 'name' => 'Here'])->getStatusCode());

        // A host that restricts new wikis: the token that created one gets it back (judged by its owner).
        $restricted = $this->call('POST', '/wikis', 'owner-1', ['owner_type' => 'restricted', 'owner_id' => 'owner-1', 'name' => 'Private']);
        self::assertSame(201, $restricted->getStatusCode(), (string) $restricted->getContent());
        self::assertTrue($this->json($restricted)['restricted']);
        // Later requests follow the usual reach: a restricted wiki must be named.
        $this->assertRefusal($this->call('GET', '/wikis/private', 'owner-1'), 404, 'ACCESS_001');
        self::assertSame(['wiki.create', 'wiki.create'], array_map(static fn ($e): string => $e->operation, ApiAuditRecorder::$events));
    }

    public function testAHostThatFilesAWikiUnderAnotherOwner(): void
    {
        $this->token('owner-1', 'u-ana', WikiApiLevel::Full, WikiApiWhere::limitedTo(['owner-1'], []));
        $body = ['owner_type' => 'misfiled', 'owner_id' => 'owner-1', 'name' => 'Misfiled'];

        $this->assertRefusal($this->call('POST', '/wikis', 'owner-1', $body, ['Idempotency-Key' => 'k-misfiled']), 500, 'SERVER_001');

        self::assertSame(['wiki.create'], array_map(static fn ($e): string => $e->operation, ApiAuditRecorder::$events));
        $logger = static::getContainer()->get('logger');
        self::assertInstanceOf(RecordingLogger::class, $logger);
        $errors = array_values(array_filter($logger->records, static fn (array $r): bool => $r['message'] === 'Wiki API: the wiki management hook created a wiki under another owner than the one asked.'));
        self::assertCount(1, $errors);
        self::assertSame(ApiTestHost::class, $errors[0]['context']['hook']);
        // The claim is kept: a retry with the same key does not create it again.
        $retry = $this->call('POST', '/wikis', 'owner-1', $body, ['Idempotency-Key' => 'k-misfiled']);
        self::assertNotSame(201, $retry->getStatusCode());
        self::assertCount(1, ApiAuditRecorder::$events);
    }

    public function testFilesUploadListAndDownload(): void
    {
        $page   = $this->page($this->eng, 'Guide', 'x');
        $upload = $this->upload('/wikis/eng/files', 'report.pdf');
        self::assertSame(201, $upload->getStatusCode(), (string) $upload->getContent());
        $file = $this->json($upload);
        self::assertSame('[report.pdf](beacon-file:' . $file['uuid'] . ')', $file['markdown']);
        self::assertSame('file.upload', ApiAuditRecorder::$events[0]->operation);

        self::assertSame([['uuid' => $file['uuid'], 'name' => 'report.pdf', 'kind' => 'file', 'mime_type' => 'application/pdf', 'size' => 5]], $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/files'))['files']);

        $download = $this->call('GET', '/wikis/eng/files/' . $file['uuid']);
        self::assertSame(200, $download->getStatusCode());
        self::assertSame('attachment', $download->headers->get('Content-Disposition'));
        self::assertSame(WikiApiDelegatedOperations::DOWNLOAD_CSP, $download->headers->get('Content-Security-Policy'));
        self::assertSame('nosniff', $download->headers->get('X-Content-Type-Options'));
        $this->assertRefusal($this->call('GET', '/wikis/eng/files/22222222-2222-4222-8222-222222222222'), 404, 'ACCESS_001');
    }

    public function testUploadRefusals(): void
    {
        $this->assertRefusal($this->upload('/wikis/eng/files', 'macro.docm'), 403, 'FILE_002');
        $this->assertRefusal($this->call('POST', '/wikis/eng/files', self::ANA), 400, 'INPUT_002');
        $this->token('reader', 'u-ana', WikiApiLevel::Read);
        $this->assertRefusal($this->upload('/wikis/eng/files', 'a.pdf', 'reader'), 403, 'TOKEN_001');
        ApiTestHost::$wikis['new'] = ['unopened' => true];
        $this->assertRefusal($this->upload('/wikis/new/files', 'a.pdf', 'reader'), 403, 'TOKEN_001');
        self::assertSame(201, $this->upload('/wikis/new/files', 'a.pdf')->getStatusCode());
        self::assertSame([], ApiTestHost::$provisioned);
        ApiTestHost::$wikis['eng']['archived'] = true;
        $this->assertRefusal($this->upload('/wikis/eng/files', 'a.pdf'), 403, 'WIKI_002');
    }

    public function testADownloadOfAnUnopenedWikiAsksTheHostOnly(): void
    {
        ApiTestHost::$wikis['new'] = ['unopened' => true];

        $this->assertRefusal($this->call('GET', '/wikis/new/files/22222222-2222-4222-8222-222222222222'), 404, 'ACCESS_001');
    }

    private function upload(string $path, string $name, string $secret = self::ANA): Response
    {
        $file = tempnam(sys_get_temp_dir(), 'wiki-api');
        file_put_contents((string) $file, 'bytes');
        $this->client->request('POST', '/api/wiki/v1' . $path, [], ['file' => new UploadedFile((string) $file, $name, 'application/pdf', null, true)], ['HTTP_AUTHORIZATION' => 'Bearer ' . $secret]);
        $this->em()->clear();

        return $this->client->getResponse();
    }
}
