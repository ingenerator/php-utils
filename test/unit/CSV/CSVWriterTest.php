<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\CSV;


use Ingenerator\PHPUtils\CSV\CSVWriter;
use Ingenerator\PHPUtils\CSV\MismatchedSchemaException;
use PHPUnit\Framework\TestCase;

class CSVWriterTest extends TestCase
{

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(CSVWriter::class, $this->newSubject());
    }

    public function test_it_throws_if_file_cannot_be_opened()
    {
        $subject = $this->newSubject();
        $this->expectException(\ErrorException::class);
        $subject->open('/invalid_csv_file');
    }

    public function test_it_throws_if_writing_before_opening_file()
    {
        $subject = $this->newSubject();
        $this->expectException(\LogicException::class);
        $subject->write(['any' => 'junk']);
    }

    public function test_it_throws_if_writing_after_closing_file()
    {
        $subj = $this->newSubject();
        $subj->open('php://temp');
        $subj->close();
        $this->expectException(\LogicException::class);
        $subj->write(['any' => 'content']);
    }

    public function test_open_accepts_and_allows_writing_to_existing_stream_resource()
    {
        $file = \fopen('php://memory', 'w');
        try {
            $subj = $this->newSubject();
            $subj->open($file);
            $subj->write(['some' => 'csv']);
            \rewind($file);
            $this->assertNotEmpty(\stream_get_contents($file));
        } finally {
            \fclose($file);
        }
    }

    public function test_it_throws_if_writing_to_externally_closed_resource()
    {
        $file = \fopen('php://memory', 'w');
        try {
            $subj = $this->newSubject();
            $subj->open($file);
        } finally {
            \fclose($file);
        }

        $this->expectException(\LogicException::class);
        $subj->write(['some' => 'csv']);
    }

    public function test_it_can_open_and_write_to_filename()
    {
        $name = \tempnam(\sys_get_temp_dir(), 'csv-test.csv');
        try {
            $subj = $this->newSubject();
            $subj->open($name, []);
            $subj->write(['a' => 'b', '1' => 2]);
            $subj->write(['a' => 'c', '1' => 3]);
            $subj->close();
            $this->assertEquals("a,1\nb,2\nc,3\n", \file_get_contents($name));
        } finally {
            \unlink($name);
        }
    }

    public function test_it_writes_column_headers_before_first_row()
    {
        $file = \fopen('php://memory', 'w');
        $subj = $this->newSubject();
        $subj->open($file);
        $subj->write(['our' => 'data', 'is' => 'here']);
        $this->assertCSVContent(
            [
                ['our', 'is'],
                ['data', 'here']
            ],
            $file
        );
        \fclose($file);
    }

    public function test_it_does_not_write_column_headers_before_subsequent_rows()
    {
        $file = \fopen('php://memory', 'w');
        $subj = $this->newSubject();
        $subj->open($file);
        $subj->write(['our' => 'data', 'is' => 'here']);
        $subj->write(['our' => 'second', 'is' => 'there']);
        $this->assertCSVContent(
            [
                ['our', 'is'],
                ['data', 'here'],
                ['second', 'there']
            ],
            $file
        );
        \fclose($file);
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function test_it_optionally_writes_byte_order_mark_at_start_of_file($write_bom)
    {
        $file = \fopen('php://memory', 'w');
        $subj = $this->newSubject();
        $subj->open($file, ['write_utf8_bom' => $write_bom]);
        $subj->write(['first' => 'row']);
        \rewind($file);
        if ($write_bom) {
            $this->assertSame(CSVWriter::UTF8_BOM, \fread($file, \strlen(CSVWriter::UTF8_BOM)));
        }
        $this->assertSame(['first'], \fgetcsv($file));
    }

    /**
     * @testWith [{"is": "jumbled", "our": "up"}]
     *           [{"our": "things", "went": "bad"}]
     */
    public function test_it_throws_if_subsequent_row_headers_do_not_match($second_row)
    {
        $file = \fopen('php://memory', 'w');
        try {
            $subj = $this->newSubject();
            $subj->open($file);
            $subj->write(['our' => 'data', 'is' => 'here']);
            $this->expectException(MismatchedSchemaException::class);
            $subj->write($second_row);
        } finally {
            \fclose($file);
        }
    }

    public function test_it_does_not_close_externally_provided_stream()
    {
        $file = \fopen('php://memory', 'w');
        try {
            $subj = $this->newSubject();
            $subj->open($file);
            $subj->close();
            $this->assertTrue(\is_resource($file));
        } finally {
            \fclose($file);
        }
    }

    protected function assertCSVContent(array $expect, $file)
    {
        \rewind($file);
        $actual = [];
        while ($row = \fgetcsv($file)) {
            $actual[] = $row;
        }

        $this->assertSame($expect, $actual, 'CSV content should match');
    }


    protected function newSubject()
    {
        return new CSVWriter();
    }

}
