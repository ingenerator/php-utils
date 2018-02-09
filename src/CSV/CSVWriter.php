<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace Ingenerator\PHPUtils\CSV;


use Ingenerator\PHPUtils\CSV\MismatchedSchemaException;

class CSVWriter
{
    const UTF8_BOM = "\xEF\xBB\xBF";

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
        'write_utf8_bom' => FALSE
    ];

    /**
     * @param string $file
     * @param array  $options
     */
    public function open($file, array $options = [])
    {
        if (is_resource($file) AND (get_resource_type($file) === 'stream')) {
            $this->resource      = $file;
            $this->owns_resource = FALSE;
        } elseif (is_string($file)) {
            $this->resource      = fopen($file, 'w');
            $this->owns_resource = TRUE;
        } else {
            throw new \InvalidArgumentException(
                'Expected `file` to be string or an existing resource'
            );
        }
        $this->expect_schema = NULL;
        $this->options       = array_merge($this->options, $options);
    }

    /**
     * @param array $row
     */
    public function write(array $row)
    {
        if ( ! $this->isResourceOpen()) {
            throw new \LogicException('Cannot write to a closed file');
        }

        $row_schema = array_keys($row);

        if ($this->expect_schema === NULL) {
            if ($this->options['write_utf8_bom']) {
                fputs($this->resource, static::UTF8_BOM);
            }
            $this->expect_schema = $row_schema;
            fputcsv($this->resource, $this->expect_schema);
        } elseif ($this->expect_schema !== $row_schema) {
            throw MismatchedSchemaException::forSchema($this->expect_schema, $row_schema);
        }

        fputcsv($this->resource, $row);
    }

    protected function isResourceOpen()
    {
        return $this->resource && (get_resource_type($this->resource) === 'stream');
    }

    public function close()
    {
        if ( ! $this->resource) {
            throw new \LogicException('Cannot close a file that has not been opened!');
        }

        if ($this->owns_resource) {
            fclose($this->resource);
        }

        $this->resource = NULL;
    }
}
