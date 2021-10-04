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

    public function addTimer(MetricId $metric, DateTimeImmutable $start_time, DateTimeImmutable $end_time): void
    {
        $time_ms = DateTimeDiff::microsBetween($start_time, $end_time) / 1000;
        $this->pushMetric(self::TYPE_TIMER, $metric, $time_ms);
    }

    public function addSample(MetricId $metric, float $value): void
    {
        $this->pushMetric(self::TYPE_TIMER, $metric, $value);
    }

    public function incrementCounterByOne(MetricId $metric): void
    {
        $this->pushMetric(self::TYPE_COUNTER, $metric, 1);
    }

    public function setGauge(MetricId $metric, float $value): void
    {
        $this->pushMetric(self::TYPE_GAUGE, $metric, $value);
    }

    public function recordCounterIntegral(MetricId $metric, float $value): void
    {
        $this->pushMetric(self::TYPE_KEYVALUE, $metric, $value);
    }

    public function setSourceHostname(string $source_hostname): void
    {
        // We have to take just the first part of hostname - periods are not allowed
        $host                  = explode('.', $source_hostname);
        $this->source_hostname = array_shift($host);
    }

    /**
     * Pushes a metric into statsite. Metrics can be one of a number of types:
     *
     *   TYPE_KEYVALUE - a simple key/value store
     *   TYPE_GAUGE    - like key/value but only the most recent received metric is stored
     *   TYPE_TIMER    - traditionally a timing, but can be any measurable value where min, max, mean are useful
     *   TYPE_COUNTER  - simple counter, value is a positive or negative increment
     *
     * @param string   $type   The type - one of the TYPE_XXX constants
     * @param MetricId $metric The source. If NULL, will be set to hostname, if FALSE will be left empty
     * @param mixed    $value  The value of the metric - int or float for most things, string for set
     *
     * @return void
     */
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
