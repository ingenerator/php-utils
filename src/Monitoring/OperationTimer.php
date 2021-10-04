<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */


namespace Ingenerator\PHPUtils\Monitoring;


use Ingenerator\PHPUtils\DateTime\Clock\RealtimeClock;
use InvalidArgumentException;

class OperationTimer
{
    protected MetricsAgent $metrics_agent;

    protected RealtimeClock $realtime_clock;

    public function __construct(MetricsAgent $metrics_agent, RealtimeClock $realtime_clock = NULL)
    {
        $this->metrics_agent  = $metrics_agent;
        $this->realtime_clock = $realtime_clock ?? new RealtimeClock();
    }

    /**
     * Wrap an operation in timing
     *
     *   return $this->monitoring->timeOperation(
     *     function (MetricId $metric) {
     *       try {
     *         return $this->doTheActualStuff();
     *       } catch (Throwable $e) {
     *         // Customise the source to track errors separately
     *         $metric->source = 'err';
     *         throw $e;
     *       }
     *     },
     *     // Default metric and source don't have to be populated, can set in the callback instead as above. They must
     *     // be present by the time we post the metric
     *     'my-operation',
     *     'ok'
     *   );
     *
     * @param callable    $operation
     * @param string|null $default_metric_name
     * @param string|null $default_source
     *
     * @return mixed
     */
    public function timeOperation(
        callable $operation,
        ?string $default_metric_name = NULL,
        ?string $default_source = NULL
    ) {
        $metric     = MetricId::nameAndSource($default_metric_name, $default_source);
        $start_time = $this->realtime_clock->getDateTime();
        try {
            return $operation($metric);
        } finally {
            $end_time = $this->realtime_clock->getDateTime();
            if (empty($metric->getName()) or empty($metric->getSource())) {
                throw new InvalidArgumentException('Must specify `metric_name` and `source` in args or callback');
            }
            $this->metrics_agent->addTimer($metric, $start_time, $end_time);
        }
    }
}
