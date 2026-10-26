<?php

namespace test\unit\Ingenerator\PHPUtils\Logging;

use Ingenerator\PHPUtils\Logging\DefaultLogMetadata;
use Ingenerator\PHPUtils\Logging\DeviceIdentifier;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class DefaultLogMetadataTest extends TestCase
{
    /**
     * ---------------------------------
     * deviceIdentity
     * ---------------------------------.
     */
    public function testItsDeviceIdentityProvidesDeviceIdAsDeferredValue(): void
    {
        $result = DefaultLogMetadata::deviceIdentityLazy();
        $this->assertIsCallable($result);
        DeviceIdentifier::forceGlobalTestValue('abc4567890123456789012');
        $this->assertSame(['context' => ['did' => 'abc4567890123456789012']], $result());
    }

    #[TestWith([['REMOTE_ADDR' => '192.182.382.39'], '192.182.382.39'])]
    #[TestWith([[], '{na}'])]
    public function testItsHttpContextProvidesClientIpFromServerVars($server, $expect): void
    {
        $result = DefaultLogMetadata::httpContext($server);
        $this->assertSame($expect, $result['context']['httpRequest']['remoteIp']);
    }

    #[TestWith([['REQUEST_URI' => '/foobar/foo'], '/foobar/foo'])]
    #[TestWith([['REQUEST_URI' => '/foobar/foo?arg=what'], '/foobar/foo?arg=what'])]
    #[TestWith([[], null])]
    public function testItsHttpContextProvidesRequestUrlFromServerVars($server, $expect): void
    {
        $result = DefaultLogMetadata::httpContext($server);
        $this->assertSame($expect, $result['context']['httpRequest']['requestUrl']);
    }

    #[TestWith([['REQUEST_METHOD' => 'POST'], 'POST'])]
    #[TestWith([[], null])]
    public function testItsHttpContextProvidesRequestMethodFromServerVars($server, $expect): void
    {
        $result = DefaultLogMetadata::httpContext($server);
        $this->assertSame($expect, $result['context']['httpRequest']['requestMethod']);
    }

    /**
     * ---------------------------------
     * requestTrace
     * ---------------------------------.
     */
    public function testItsRequestTracingDataAddsRandomRequestId(): void
    {
        $result1 = DefaultLogMetadata::requestTrace();
        $this->assertMatchesRegularExpression(
            '/^[a-z0-9]+\.[0-9]+$/',
            $result1['context']['req'],
            'Should match expected format'
        );
        $result3 = DefaultLogMetadata::requestTrace();
        $this->assertNotSame(
            $result1['context']['req'],
            $result3['context']['req'],
            'Should be different for different instance'
        );
    }

    public function testItsRequestTracingDataAddsTraceIdWithRequestIdIfNoHeadersAvailable(
    ): void {
        $result = DefaultLogMetadata::requestTrace();
        $this->assertSame(
            $result['context']['req'],
            $result['logging.googleapis.com/trace'],
            'Should assign trace key to request ID'
        );
    }

    public static function provider_trace_header(): array
    {
        return [
            [
                // Doesn't match expected format, pass through as-is (with or without project)
                ['HTTP_X_CLOUD_TRACE_CONTEXT' => 'abcdef-invalid'],
                null,
                'abcdef-invalid',
            ],
            [
                // Doesn't match expected format, pass through as-is (with or without project)
                ['HTTP_X_CLOUD_TRACE_CONTEXT' => 'abcdef-invalid'],
                'ig-smth',
                'abcdef-invalid',
            ],
            [
                // Expected format but no GCP project - pass through the first key
                ['HTTP_X_CLOUD_TRACE_CONTEXT' => 'a82732dfd72b2872/935674659030955431'],
                null,
                'a82732dfd72b2872',
                'projects/a82732dfd72b2872/traces/935674659030955431',
            ],
            [
                // Expected format and GCP project - convert to GCP format
                ['HTTP_X_CLOUD_TRACE_CONTEXT' => 'a82732dfd72b2872/935674659030955431'],
                'ig-smth',
                'projects/ig-smth/traces/a82732dfd72b2872',
            ],
        ];
    }

    #[DataProvider('provider_trace_header')]
    public function testItsRequestTracingDataAddsTraceIdWithFromHeaderAndOptionallyProjectIfAvailable(
        array $server,
        ?string $project,
        string $expect,
    ): void {
        $result = DefaultLogMetadata::requestTrace($server, $project);
        $this->assertSame(
            $expect,
            $result['logging.googleapis.com/trace'],
            'Should assign expected trace ID'
        );
    }

    /**
     * ---------------------------------
     * serviceContext
     * ---------------------------------.
     */
    public function testItsServiceContextProvidesServiceName(): void
    {
        $result = DefaultLogMetadata::serviceContext('my-svc');
        $this->assertSame('my-svc', $result['serviceContext']['service']);
    }

    #[TestWith(['<?php I am the borg', '#ERROR#'])]
    #[TestWith(["<?php throw new BadMethodCallException('Whoops');", '#ERROR#'])]
    #[TestWith(["<?php 'abcdefe';", '#ERROR#'])]
    #[TestWith(["<?php return 'abcdef123';", 'abcdef123'])]
    #[TestWith([false, '#ERROR#'])]
    public function testItsServiceContextLoadsVersionFromFileIgnoringAnyErrors($file_content, $expect): void
    {
        $vfs = vfsStream::setup();
        if ($file_content !== false) {
            $version_file_path = vfsStream::newFile('version.php')
                ->at($vfs)
                ->withContent($file_content)
                ->url();
        } else {
            $version_file_path = $vfs->url().'/no-file.php';
        }

        $result = DefaultLogMetadata::serviceContext('any', $version_file_path);
        $this->assertSame($expect, $result['serviceContext']['version']);
    }
}
