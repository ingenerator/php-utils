<?php


namespace Ingenerator\PHPUtils\Logging;


/**
 * Finds the last external call to a particular [set of] internal classes.
 *
 * Used to locate the place that actually called a log method. With the PSR logger this may be a couple of calls back
 * due to the sugar `->info()` etc methods that call `->log()` internally. If other classes / logging frameworks
 * send their logs into the logger these should take responsibility for filtering their own class hierarchy out of the
 * trace and provide a custom `sourceLocation` key in their log context entry.
 */
class ExternalCallSiteFinder
{

    /**
     * Returns a `sourceLocation` formatted array with the file, line and function making the external call.
     *
     * This logic is additionally complex because PHP splits the calling class/function and file/line across two stack
     * frames (which makes sense in some contexts but not this one).
     *
     * e.g.
     *    class Proxy {
     *        function proxyLog() { return (new Backend)->warning(); }
     *    }
     *    class AbstractBackend {
     *        function warning() { return $this->>log(); }
     *    }
     *    class Backend extends AbstractBackend {
     *        function log() {
     *            return (new ExternalCallSiteFinder)->findExternalCall(
     *                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
     *                [static::class, Proxy::class]
     *            );
     *        }
     *    }
     *    class Caller {
     *      function do() {
     *        $site = (new Proxy)->proxyLog();
     *        assert(
     *          $site === ['file' => __FILE__, 'line' => __LINE - 1, 'function' => 'Caller->do']
     *          // The calculated site ignores all calls internal to Proxy, Backend and AbstractBackend and
     *          // correctly reports that it was this method that kicked off the chain by calling Proxy->proxyLog()
     *          // In practice of course the call site is included in the log metadata, not returned up the chain
     *        );
     *      }
     *    }
     *
     *
     * @param array $trace
     * @param array $ignore_classes
     *
     * @return array
     */
    public function findExternalCall(array $trace, array $ignore_classes): array
    {
        $orig_trace = $trace;

        // Find the call to this class. The info is split across two stack frames
        //  - file and line come from the entry that contains the external call to our logger method
        //    which may be eg AbstractLogger->info or StackdriverApplicationLogger->log or some other
        //    parent / child class method.
        //  - the name of the *calling* function / code that contains that file and line comes from
        //    the next stack frame.
        //
        // So we need to collect all the frames that refer to this class so we can find the last one.
        $internal_calls = [];
        while ($next_call = \array_shift($trace)) {
            if ($this->isIgnoredClass($next_call['class'] ?? '{unknown class}', $ignore_classes)) {
                $internal_calls[] = $next_call;
            } else {
                break;
            }
        }

        $called_from = \array_pop($internal_calls);

        if ( ! ($called_from and $next_call)) {
            // Edge case - WTF??
            // Should be impossible, but these traces are not always straightforward so better to include something
            // for future debugging of why we didn't get the expected calls. It could be the client did not pass a deep
            // enough stack trace.
            return ['_unexpected_trace_' => $orig_trace];
        }

        if ( ! isset($next_call['function'])) {
            // Also unlikely / not produced in testing
            $func = '{unknown}';
        } elseif ($next_call['function'] === '{closure}') {
            // `class` appears to stay in the frames if there was a class that e.g. required a file
            // that itself called an anonmyous func. And that gets messy fast.
            // So just report closure with no parent scope (we have the file/line anyway).
            $func = '{closure}';
        } elseif (isset($next_call['class'])) {
            // This was a class method - either -> for instance scope, or :: for static
            $func = $next_call['class'].$next_call['type'].$next_call['function'];
        } else {
            // This was a pure (named) function
            $func = $next_call['function'];
        }

        return [
            'file'     => $called_from['file'],
            'line'     => $called_from['line'],
            'function' => $func,
        ];
    }

    protected function isIgnoredClass(string $class, array $ignore_classes): bool
    {
        foreach ($ignore_classes as $ignored_class) {
            if (is_a($ignored_class, $class, TRUE)) {

                return TRUE;
            }
        }

        return FALSE;
    }
}
