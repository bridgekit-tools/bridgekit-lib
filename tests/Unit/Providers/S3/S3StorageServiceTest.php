<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Providers\S3;

use BridgeKit\Providers\S3\Services\S3StorageService;
use BridgeKit\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class S3StorageServiceTest extends TestCase
{
    public function test_get_file_builds_virtual_host_web_url_for_aws_endpoint(): void
    {
        Http::fake([
            'https://s3.eu-west-1.amazonaws.com/*' => Http::response('', 200, [
                'Content-Type' => 'image/png',
                'Content-Length' => '1024',
                'ETag' => '"abc"',
            ]),
        ]);

        $service = new S3StorageService([
            'bucket' => 'my-bucket',
            'region' => 'eu-west-1',
            'key' => 'AKID',
            'secret' => 'secret',
        ]);

        $file = $service->getFile('photos/2025/team.png');

        self::assertSame(
            'https://my-bucket.s3.eu-west-1.amazonaws.com/photos/2025/team.png',
            $file->webUrl,
        );
    }

    public function test_get_file_uses_path_style_url_when_endpoint_is_supplied(): void
    {
        Http::fake([
            'https://minio.local:9000/*' => Http::response('', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Length' => '0',
                'ETag' => '""',
            ]),
        ]);

        $service = new S3StorageService([
            'bucket' => 'docs',
            'region' => 'us-east-1',
            'key' => 'AKID',
            'secret' => 'secret',
            'endpoint' => 'https://minio.local:9000',
        ]);

        $file = $service->getFile('reports/q1.pdf');

        self::assertSame(
            'https://minio.local:9000/docs/reports/q1.pdf',
            $file->webUrl,
        );
    }

    public function test_upload_file_returns_storage_file_with_web_url(): void
    {
        Http::fake([
            '*' => Http::response('', 200, ['ETag' => '"xyz"']),
        ]);

        $service = new S3StorageService([
            'bucket' => 'my-bucket',
            'region' => 'us-east-1',
            'key' => 'AKID',
            'secret' => 'secret',
        ]);

        $file = $service->uploadFile('hello.txt', 'world', 'text/plain');

        self::assertSame(
            'https://my-bucket.s3.us-east-1.amazonaws.com/hello.txt',
            $file->webUrl,
        );
    }

    public function test_presigned_url_contains_signature_query_parameters(): void
    {
        $service = new S3StorageService([
            'bucket' => 'my-bucket',
            'region' => 'us-east-1',
            'key' => 'AKID',
            'secret' => 'secret',
        ]);

        $url = $service->getPresignedUrl('docs/secret.pdf', expiresIn: 600);

        self::assertStringContainsString('https://my-bucket.s3.us-east-1.amazonaws.com/docs/secret.pdf', $url);
        self::assertStringContainsString('X-Amz-Algorithm=AWS4-HMAC-SHA256', $url);
        self::assertStringContainsString('X-Amz-Expires=600', $url);
        self::assertStringContainsString('X-Amz-Signature=', $url);
    }

    public function test_keys_are_url_encoded_in_web_url(): void
    {
        Http::fake([
            '*' => Http::response('', 200, [
                'Content-Type' => 'text/plain',
                'Content-Length' => '0',
                'ETag' => '""',
            ]),
        ]);

        $service = new S3StorageService([
            'bucket' => 'my-bucket',
            'region' => 'us-east-1',
            'key' => 'AKID',
            'secret' => 'secret',
        ]);

        $file = $service->getFile('reports/Q1 2026/summary file.txt');

        self::assertSame(
            'https://my-bucket.s3.us-east-1.amazonaws.com/reports/Q1%202026/summary%20file.txt',
            $file->webUrl,
        );
    }
}
