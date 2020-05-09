<?php


namespace test\unit\Ingenerator\PHPUtils\Logging;


use Ingenerator\PHPUtils\Logging\DefaultLogMetadata;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class DefaultLogMetadataTest extends TestCase
{
    /**
     * ---------------------------------
     * httpContext
     * ---------------------------------
     */

    /**
     * @testWith [{"REMOTE_ADDR": "192.182.382.39"}, "192.182.382.39"]
     *           [{}, "{na}"]
     */
    public function test_its_http_context_provides_client_ip_from_server_vars($server, $expect)
    {
        $result = DefaultLogMetadata::httpContext($server);
        $this->assertSame($expect, $result['context']['httpRequest']['remoteIp']);
    }

    /**
     * @testWith [{"REQUEST_URI": "/foobar/foo"}, "/foobar/foo"]
     *           [{"REQUEST_URI": "/foobar/foo?arg=what"}, "/foobar/foo?arg=what"]
     *           [{}, null]
     */
    public function test_its_http_context_provides_request_url_from_server_vars($server, $expect)
    {
        $result = DefaultLogMetadata::httpContext($server);
        $this->assertSame($expect, $result['context']['httpRequest']['requestUrl']);
    }

    /**
     * @testWith [{"REQUEST_METHOD": "POST"}, "POST"]
     *           [{}, null]
     */
    public function test_its_http_context_provides_request_method_from_server_vars($server, $expect)
    {
        $result = DefaultLogMetadata::httpContext($server);
        $this->assertSame($expect, $result['context']['httpRequest']['requestMethod']);
    }


    /**
     * ---------------------------------
     * requestTrace
     * ---------------------------------
     */

    public function test_its_request_tracing_data_adds_random_request_id()
    {
        $result1 = DefaultLogMetadata::requestTrace('abc');
        $this->assertRegExp(
            '/^[a-z0-9]+\.[0-9]+$/',
            $result1['context']['req'],
            'Should match expected format'
        );
        $result3 = \Ingenerator\PHPUtils\Logging\DefaultLogMetadata::requestTrace('abc');
        $this->assertNotSame(
            $result1['context']['req'],
            $result3['context']['req'],
            'Should be different for different instance'
        );
    }

    /**
     * @testWith [null, "{na}"]
     *           ["abasd23", "abasd23"]
     */
    public function test_its_request_tracing_data_adds_provided_session_id($sid, $expect)
    {
        $this->assertSame(
            $expect,
            \Ingenerator\PHPUtils\Logging\DefaultLogMetadata::requestTrace($sid)['context']['sess']
        );
    }

    public function test_its_request_tracing_data_adds_trace_id_with_request_id_if_no_headers_available()
    {
        $result = \Ingenerator\PHPUtils\Logging\DefaultLogMetadata::requestTrace('abc');
        $this->assertSame(
            $result['context']['req'],
            $result['logging.googleapis.com/trace'],
            'Should assign trace key to request ID'
        );
    }

    public function provider_trace_header()
    {
        return [
            [
                // Doesn't match expected format, pass through as-is (with or without project)
                ['HTTP_X_CLOUD_TRACE_CONTEXT' => 'abcdef-invalid'],
                NULL,
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
                NULL,
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

    /**
     * @dataProvider provider_trace_header
     */
    public function test_its_request_tracing_data_adds_trace_id_with_from_header_and_optionally_project_if_available(
        $server,
        $project,
        $expect
    ) {
        $result = DefaultLogMetadata::requestTrace('abc', $server, $project);
        $this->assertSame(
            $expect,
            $result['logging.googleapis.com/trace'],
            'Should assign expected trace ID'
        );
    }

    /**
     * ---------------------------------
     * serviceContext
     * ---------------------------------
     */

    public function test_its_service_context_provides_service_name()
    {
        $result = \Ingenerator\PHPUtils\Logging\DefaultLogMetadata::serviceContext('my-svc');
        $this->assertSame('my-svc', $result['serviceContext']['service']);
    }

    /**
     * @testWith ["<?php I am the borg", "#ERROR#"]
     *           ["<?php throw new BadMethodCallException('Whoops');", "#ERROR#"]
     *           ["<?php 'abcdefe';", "#ERROR#"]
     *           ["<?php return 'abcdef123';", "abcdef123"]
     *           [false, "#ERROR#"]
     */
    public function test_its_service_context_loads_version_from_file_ignoring_any_errors($file_content, $expect)
    {
        $vfs = vfsStream::setup();
        if ($file_content !== FALSE) {
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
