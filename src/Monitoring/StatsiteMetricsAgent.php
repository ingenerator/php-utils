<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */


namespace Ingenerator\PHPUtils\Monitoring;

use DateTimeImmutable;
use Ingenerator\PHPUtils\DateTime\DateTimeDiff;
use function array_shift;
use function explode;
use function gethostname;
use function socket_sendto;
use function sprintf;
use function strlen;

/**
 * Supports:
 *  - COUNTER
 *  - GAUGE
 *  - KEY VALUE
 *  - TIMER
 *
 * See https://github.com/statsite/statsite
 * for more info on metric types supported by statsite
 *
 */
class StatsiteMetricsAgent implements MetricsAgent
{
    private const TYPE_COUNTER  = 'c';
    private const TYPE_GAUGE    = 'g';
    private const TYPE_KEYVALUE = 'kv';
    private const TYPE_TIMER    = 'ms';

    /**
     * @var false|resource
     */
    protected $socket;

    protected string $statsite_host;

    protected int $statsite_port;

    protected string $source_hostname;

    public function __construct(string $statsite_host = '127.0.0.1', int $statsite_port = 8125)
    {
        $this->statsite_host = $statsite_host;
        $this->statsite_port = $statsite_port;
        $this->socket        = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        $this->setSourceHostname(gethostname());
    }

    public function __destruct()
    {
        if ($this->socket) {
            socket_close($this->socket);
        }
    }

    /**
     * Traditional timer metric calculated stored as floating point in milliseconds
     * Use addSample for other measurable values which do not represent time.
     */
    public function addTimer(MetricId $metric, DateTimeImmutable $start_time, DateTimeImmutable $end_time): void
    {
        $time_ms = DateTimeDiff::microsBetween($start_time, $end_time) / 1000;
        $this->pushMetric(self::TYPE_TIMER, $metric, $time_ms);
    }

    /**
     * Coalesced to timer on statsite as can be any measurable value where min, max, mean are useful
     */
    public function addSample(MetricId $metric, float $value): void
    {
        $this->pushMetric(self::TYPE_TIMER, $metric, $value);
    }

    /**
     * Simple incremental-only counter
     */
    public function incrementCounterByOne(MetricId $metric): void
    {
        $this->pushMetric(self::TYPE_COUNTER, $metric, 1);
    }

    /**
     * Gauge, similar to key value but only the last value per key is retained by statsite at each flush interval
     */
    public function setGauge(MetricId $metric, float $value): void
    {
        $this->pushMetric(self::TYPE_GAUGE, $metric, $value);
    }

    /**
     * Not at all a counter from the perspective of statsite but a key-value store
     * Up to the user to interpret these when statsite flushes them.
     */
    public function recordCounterIntegral(MetricId $metric, float $value): void
    {
        $this->pushMetric(self::TYPE_KEYVALUE, $metric, $value);
    }

    /**
     * MetricIds can be created with a placeholder as the source for hostname replacement
     * The current system hostname is set in the construction of this class but can be overridden using this setter
     *
     * NB hostnames cannot contain periods so are split and only the first segment used.
     */
    public function setSourceHostname(string $source_hostname): void
    {
        // We have to take just the first part of hostname - periods are not allowed
        $host                  = explode('.', $source_hostname);
        $this->source_hostname = array_shift($host);
    }

    private function pushMetric(string $type, MetricId $metric, $value): void
    {
        // Replace source with hostname if required
        if ($metric->getSource() === MetricId::SOURCE_HOST_REPLACEMENT) {
            $metric->setSource($this->source_hostname);
        }

        $message = sprintf("%s=%s:%s|%s", $metric->getName(), $metric->getSource(), $value, $type);

        $this->sendToSocket($message);
    }

    protected function sendToSocket(string $message): void
    {
        socket_sendto($this->socket, $message, strlen($message), 0, $this->statsite_host, $this->statsite_port);
    }
}
