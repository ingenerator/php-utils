<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace Ingenerator\PHPUtils\CSV;


use Ingenerator\PHPUtils\CSV\MismatchedSchemaException;

class CSVReader
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
    protected $options = [
        'read_utf8_bom' => FALSE
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
            $this->resource      = fopen($file, 'r');
            $this->owns_resource = TRUE;
        } else {
            throw new \InvalidArgumentException(
                'Expected `file` to be string or an existing resource'
            );
        }
        $this->options       = array_merge($this->options, $options);
    }

    /**
     * @return array
     */
    public function read()
    {
        if ( ! $this->isResourceOpen()) {
            throw new \LogicException('Cannot read to a closed file');
        }

        $row = fgetcsv($this->resource);
        if ($this->options['read_utf8_bom']) {
            $row = str_replace(static::UTF8_BOM, '', $row);
        }

        return $row;
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
