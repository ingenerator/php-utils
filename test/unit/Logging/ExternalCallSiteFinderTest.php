<?php


namespace test\unit\Ingenerator\PHPUtils\Logging;

use Ingenerator\PHPUtils\Logging\ExternalCallSiteFinder;
use PHPUnit\Framework\TestCase;

class ExternalCallSiteFinderTest extends TestCase
{

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(ExternalCallSiteFinder::class, $this->newSubject());
    }

    public function test_it_reports_unknown_location_if_stack_not_deep_enough()
    {
        $trace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $this->assertSame(
            ['_unexpected_trace_' => $trace],
            $this->newSubject()->findExternalCall($trace, [static::class])
        );
    }

    public function test_it_reports_correct_location_for_direct_call_to_external_method()
    {
        $caller    = new TestCallsiteCaller;
        $result    = $caller->singleExternal($this->newSubject());
        $call_line = __LINE__ - 1;

        $this->assertSame(
            [
                'file'     => __FILE__,
                'line'     => $call_line,
                'function' => __CLASS__.'->'.__FUNCTION__,
            ],
            $result
        );
    }

    public function test_it_ignores_internal_calls_within_single_specified_class()
    {
        $caller    = new TestCallsiteCaller;
        $result    = $caller->throughInternalMethod($this->newSubject());
        $call_line = __LINE__ - 1;

        $this->assertSame(
            [
                'file'     => __FILE__,
                'line'     => $call_line,
                'function' => __CLASS__.'->'.__FUNCTION__,
            ],
            $result
        );
    }

    public function test_it_ignores_internal_calls_within_parent_class()
    {
        $caller    = new TestCallsiteCaller;
        $result    = $caller->throughParentMethod($this->newSubject());
        $call_line = __LINE__ - 1;

        $this->assertSame(
            [
                'file'     => __FILE__,
                'line'     => $call_line,
                'function' => __CLASS__.'->'.__FUNCTION__,
            ],
            $result
        );
    }

    public function test_it_ignores_calls_through_expected_external_callers()
    {
        $caller    = new TestCallSiteProxy;
        $result    = $caller->throughExpectedDependency($this->newSubject());
        $call_line = __LINE__ - 1;

        $this->assertSame(
            [
                'file'     => __FILE__,
                'line'     => $call_line,
                'function' => __CLASS__.'->'.__FUNCTION__,
            ],
            $result
        );
    }

    public function provider_source_location_call_sites()
    {
        return [
            [
                <<<'PHP'
                // Raw file include
                use test\unit\Ingenerator\PHPUtils\Logging\TestCallSiteAsserter;
                /* @var TestCallSiteAsserter $asserter */ 
                return $asserter->test(['file' => __FILE__, 'line' => __LINE__, 'function' => 'require']);
PHP
                ,
            ],
            [
                <<<'PHP'
                // Raw function
                function log_ab8123723123($asserter) {
                    return $asserter->test(['file' => __FILE__, 'line' => __LINE__, 'function' => 'log_ab8123723123']);
                }
                return log_ab8123723123($asserter);
PHP
                ,
            ],
            [
                <<<'PHP'
                // Raw function in namespace
                namespace whoops\i\did\it\again;
                function log_ab8123723123($asserter) {
                    return $asserter->test(['file' => __FILE__, 'line' => __LINE__, 'function' => 'whoops\i\did\it\again\log_ab8123723123']);
                }
                return log_ab8123723123($asserter);
PHP
                ,
            ],
            [
                <<<'PHP'
                // Anonymous func
                return (function ($asserter) {
                    return $asserter->test(['file' => __FILE__, 'line' => __LINE__, 'function' => '{closure}']);
                })($asserter);
PHP
                ,
            ],
            [
                <<<'PHP'
                // Static class call
                class Lgt812372321312312321Static {
                  public static function log($asserter) {
                    return $asserter->test(['file' => __FILE__, 'line' => __LINE__, 'function' => 'Lgt812372321312312321Static::log']);
                  }
                }
                return Lgt812372321312312321Static::log($asserter);
PHP
                ,
            ],
            [
                <<<'PHP'
                // Instance method
                class Lgt812372321312312321Inst {
                  public function log($asserter) {
                    return $asserter->test(['file' => __FILE__, 'line' => __LINE__, 'function' => 'Lgt812372321312312321Inst->log']);
                  }
                }
                return (new Lgt812372321312312321Inst)->log($asserter);
PHP
                ,
            ],
            [
                <<<'PHP'
                // Anonymous class static
                $class = new class {
                  public static function log($asserter) {
                    return $asserter->test(['file' => __FILE__, 'line' => __LINE__, 'function' => __CLASS__.'::log']);
                  }
                };
                return $class::log($asserter);
PHP
                ,
            ],
            [
                <<<'PHP'
                // Anonymous class instance
                $class = new class {
                  public function log($asserter) {
                    return $asserter->test(['file' => __FILE__, 'line' => __LINE__, 'function' => __CLASS__.'->log']);
                  }
                };
                return $class->log($asserter);
PHP
                ,
            ],
            [
                <<<'PHP'
                // Class that calls a function
                function ab9123723712391257213($asserter) {
                    return $asserter->test(['file' => __FILE__, 'line' => __LINE__, 'function' => 'ab9123723712391257213']);
                }
                $class = new class {
                  public function log($asserter) {
                    return ab9123723712391257213($asserter);
                  }
                };
                return $class->log($asserter);
PHP
                ,
            ],
        ];
    }

    /**
     * @dataProvider provider_source_location_call_sites
     */
    public function test_it_can_handle_all_possible_call_sites($code)
    {
        $temp = \tempnam(\sys_get_temp_dir(), 'logger_test');
        \file_put_contents($temp, "<?php\n".$code);
        try {
            // Include the code block in anonymous scope so the call is not related to this method
            $func = function (TestCallSiteAsserter $asserter) use ($temp) { return require $temp; };
            $func->bindTo(NULL, NULL);
            $result = $func(new TestCallSiteAsserter($this->newSubject()));
        } finally {
            \unlink($temp);
        }

        $this->assertSame($result['expected'], $result['actual']);
    }

    protected function newSubject()
    {
        return new ExternalCallSiteFinder;
    }

}


class TestCallsiteCaller extends TestCallSiteCallerParent
{
    public function singleExternal(ExternalCallSiteFinder $finder)
    {
        return $finder->findExternalCall(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), [static::class]);
    }

    public function throughInternalMethod(ExternalCallSiteFinder $finder)
    {
        return $this->singleExternal($finder);
    }

    public function fromManager(ExternalCallSiteFinder $finder)
    {
        return $finder->findExternalCall(
            \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
            [
                static::class,
                TestCallSiteProxy::class,
            ]
        );
    }

}

abstract class TestCallSiteCallerParent
{

    abstract public function singleExternal(ExternalCallSiteFinder $finder);

    public function throughParentMethod(ExternalCallSiteFinder $finder)
    {
        return $this->singleExternal($finder);
    }

}

class TestCallSiteProxy
{

    public function throughExpectedDependency(ExternalCallSiteFinder $finder)
    {
        $caller = new TestCallsiteCaller;

        return $caller->fromManager($finder);
    }
}

class TestCallSiteAsserter
{
    /**
     * @var ExternalCallSiteFinder
     */
    private $finder;

    public function __construct(ExternalCallSiteFinder $finder) { $this->finder = $finder; }

    public function test(array $expected)
    {
        $actual = $this->finder->findExternalCall(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), [static::class]);

        ksort($expected);
        ksort($actual);

        return [
            'expected' => $expected,
            'actual'   => $actual,
        ];
    }
}
