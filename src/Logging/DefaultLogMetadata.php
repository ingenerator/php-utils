<?php

namespace Ingenerator\PHPUtils\Logging;

use Throwable;

use function array_shift;
use function uniqid;

/**
 * A set of methods that may be able to provide standard log metadata in most environments.
 *
 * If they do not give the correct result in your environment, don't include them in the setup of your logger :)
 */
class DefaultLogMetadata
{
    /**
     * The device identity, from the user's device_id cookie.
     *
     * This is returned as a lazy function so that it can pick up the value once the DeviceIdentifier
     * class has been initialised
     */
    public static function deviceIdentityLazy(): callable
    {
        return fn (): array => [
            'context' => ['did' => DeviceIdentifier::get()],
        ];
    }

    /**
     * The basic request method, uri and remote IP for the request.
     *
     * Note that these values are also "hoisted" by the request logger rather than look them up separately so you should
     * always include this or an equivalent metadata set with the same values.
     *
     * @param array $server the $_SERVER global
     */
    public static function httpContext(array $server): array
    {
        return [
            'context' => [
                'httpRequest' => [
                    'requestMethod' => $server['REQUEST_METHOD'] ?? null,
                    'requestUrl' => $server['REQUEST_URI'] ?? null,
                    'remoteIp' => $server['REMOTE_ADDR'] ?? '{na}',
                ],
            ],
        ];
    }

    /**
     * Basic request tracing information including a random unique request ID and google trace if any.
     */
    public static function requestTrace(array $server = [], ?string $gcp_project = null): array
    {
        $request_id = uniqid('', true);

        // Attempt to parse the GCP trace ID header as per https://cloud.google.com/run/docs/logging#writing_structured_logs
        // Note this only works if we have a GCP project as a constructor arg
        // If that doesn't work then just use the request ID as the trace ID - it won't match anything
        // in stackdriver but will still tie the logs for the request together.
        $trace_hdr_parts = explode('/', $server['HTTP_X_CLOUD_TRACE_CONTEXT'] ?? $request_id);
        if ($gcp_project && count($trace_hdr_parts) === 2) {
            $trace_id = 'projects/'.$gcp_project.'/traces/'.$trace_hdr_parts[0];
        } else {
            $trace_id = array_shift($trace_hdr_parts);
        }

        return [
            'context' => [
                'req' => $request_id,
            ],
            'logging.googleapis.com/trace' => $trace_id,
        ];
    }

    /**
     * The service context (app/service name and version).
     *
     * The version will be read from the provided file if it exists. This should be a PHP file, likely rendered
     * at the time your application is built / deployed. The file should return the version string.
     *
     * e.g.
     *    <?php return 'abc123123223';
     *
     * If the file does not exist or cannot be read the version will be returned as '#ERROR#'
     *
     * @return array|array[]
     */
    public static function serviceContext(string $service_name, ?string $version_file_path = null): array
    {
        try {
            $version = include $version_file_path;
            if ($version === 1) {
                // The file was included but did not return anything
                $version = '#ERROR#';
            }
        } catch (Throwable) {
            $version = '#ERROR#';
        }

        return [
            'serviceContext' => [
                'service' => $service_name,
                'version' => $version,
            ],
        ];
    }
}
