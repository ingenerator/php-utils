<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\PHPUtils\unit\Monitoring;

use PHPUnit\Framework\Assert;
use function array_map;

class AssertMetrics
{
    public static function assertCapturedOneTimer(
        array $metrics,
        string $name,
        ?string $source = NULL,
        ?string $msg = NULL
    ): void {
        Assert::assertEquals(
            [['name' => $name, 'source' => $source]],
            array_map(
                fn(array $m) => ['name' => $m['name'], 'source' => $m['source']],
                $metrics
            ),
            $msg ?? 'Expected exactly one timer matching the expectation'
        );
    }
}
