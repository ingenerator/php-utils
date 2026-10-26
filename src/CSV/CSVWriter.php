<?php

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\CSV;

use InvalidArgumentException;
use LogicException;
use RuntimeException;

use function array_keys;
use function array_merge;
use function fclose;
use function fopen;
use function fputcsv;
use function get_resource_type;
use function is_resource;
use function is_string;

class CSVWriter
{
    public const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * @var resource
     */
    protected $resource;

    /**
     * @var bool
     */
    protected $owns_resource;

    /**
     * @var array
     */
    protected $expect_schema;

    /**
     * @var array
     */
    protected $options = [
        'write_utf8_bom' => false,
        'quote_headers' => true,
        'eol' => "\n",
    ];

    /**
     * @param string $file
     */
    public function open($file, array $options = []): void
    {
        if (is_resource($file) && get_resource_type($file) === 'stream') {
            $this->resource = $file;
            $this->owns_resource = false;
        } elseif (is_string($file)) {
            $this->resource = fopen($file, 'w');
            $this->owns_resource = true;
        } else {
            throw new InvalidArgumentException(
                'Expected `file` to be string or an existing resource'
            );
        }
        $this->expect_schema = null;
        $this->options = array_merge($this->options, $options);
    }

    public function write(array $row): void
    {
        if (!$this->isResourceOpen()) {
            throw new LogicException('Cannot write to a closed file');
        }

        $row_schema = array_keys($row);

        if ($this->expect_schema === null) {
            if ($this->options['write_utf8_bom']) {
                fwrite($this->resource, static::UTF8_BOM);
            }
            $this->expect_schema = $row_schema;
            $this->writeHeaders($this->expect_schema);
        } elseif ($this->expect_schema !== $row_schema) {
            throw MismatchedSchemaException::forSchema($this->expect_schema, $row_schema);
        }

        fputcsv($this->resource, $row, eol: $this->options['eol']);
    }

    protected function isResourceOpen(): bool
    {
        return $this->resource && (get_resource_type($this->resource) === 'stream');
    }

    public function close(): void
    {
        if (!$this->resource) {
            throw new LogicException('Cannot close a file that has not been opened!');
        }

        if ($this->owns_resource) {
            fclose($this->resource);
        }

        $this->resource = null;
    }

    private function writeHeaders(array $keys): void
    {
        if ($this->options['quote_headers']) {
            fputcsv($this->resource, $keys, eol: $this->options['eol']);
        } else {
            array_walk(
                $keys,
                fn (string $str): true => !str_contains($str, ',')
                    ?: throw new RuntimeException(
                        "Column header `$str` cannot contain comma if headers are not quoted"
                    )
            );
            fwrite($this->resource, implode(',', $keys).$this->options['eol']);
        }
    }
}
