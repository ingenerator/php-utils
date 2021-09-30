<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\PHPUtils\unit\Monitoring;

use Ingenerator\PHPUtils\Monitoring\MetricId;
use Ingenerator\PHPUtils\Monitoring\StatsiteMetricsAgent;
use PHPUnit\Framework\TestCase;

class SpyingStatsiteMetricsAgent extends StatsiteMetricsAgent
{

    private array $messages = [];

    public function getMessages(): array
    {
        return $this->messages;
    }

    protected function sendToSocket(string $message): void
    {
        $this->messages[] = $message;
    }

}

class StatsiteMetricsAgentTest extends TestCase
{
    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(StatsiteMetricsAgent::class, $this->newSubject());
    }

    public function test_it_increments_counter_by_one()
    {
        $subject = $this->newSubject();
        $subject->incrementCounterByOne(new MetricId('logins', 'test'));
        $subject->incrementCounterByOne(new MetricId('login_failed', 'test'));
        $subject->incrementCounterByOne(new MetricId('logins', 'test'));
        $this->assertSame(['logins=test:1|c', 'login_failed=test:1|c', 'logins=test:1|c'], $subject->getMessages());
    }

    public function test_it_sets_gauge()
    {
        $subject = $this->newSubject();
        $subject->setGauge(new MetricId('active_sessions', 'test'), 205);
        $subject->setGauge(new MetricId('active_sessions', 'test'), 108);
        $this->assertSame(['active_sessions=test:205|g', 'active_sessions=test:108|g'], $subject->getMessages());
    }

    public function test_it_records_counter_integral()
    {
        $subject = $this->newSubject();
        $subject->recordCounterIntegral(new MetricId('opcache_oom', 'test'), 2);
        $this->assertSame(['opcache_oom=test:2|kv'], $subject->getMessages());
    }

    public function test_it_adds_timer()
    {
        $subject = $this->newSubject();
        $subject->addTimer(
            new MetricId('email_sent', 'test'),
            new \DateTimeImmutable('2020-03-30 10:01:02.3455'),
            new \DateTimeImmutable('2020-03-30 10:01:03.5000')
        );
        $this->assertSame(['email_sent=test:1154.5|ms'], $subject->getMessages());
    }

    public function test_it_adds_sample()
    {
        $subject = $this->newSubject();
        $subject->addSample(new MetricId('mem_used', 'test'), 17.24);
        $this->assertSame(['mem_used=test:17.24|ms'], $subject->getMessages());
    }

    private function newSubject(): SpyingStatsiteMetricsAgent
    {
        return new SpyingStatsiteMetricsAgent();
    }
}