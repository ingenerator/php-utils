<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\PHPUtils\CSV;


use Ingenerator\PHPUtils\CSV\CSVReader;
use PHPUnit\Framework\TestCase;

class CSVReaderTest extends TestCase
{

    public function setUp()
    {
        file_put_contents(__DIR__ . '/./csv-test.csv', "#,row1,row2,row3\n1,value1,value2,value3\n");
    }

    public function tearDown()
    {
        unlink(__DIR__ . '/./csv-test.csv');
    }


    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(CSVReader::class, $this->newSubject());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function test_it_throws_if_file_is_invalid_type()
    {
        $subj = $this->newSubject();
        $subj->open(12345678);
    }

    /**
     * @expectedException \LogicException
     */
    public function test_it_throws_if_it_cannot_close_file_that_has_not_been_opened()
    {
        $subj = $this->newSubject();
        $subj->close();
    }

    /**
     * @expectedException \ErrorException
     */
    public function test_it_throws_if_file_cannot_be_opened()
    {
        $this->newSubject()->open('/invalid_csv_file');
    }

    /**
     * @expectedException \LogicException
     */
    public function test_it_throws_if_reading_before_opening_file()
    {
        $this->newSubject()->read();
    }

    /**
     * @expectedException \LogicException
     */
    public function test_it_throws_if_reading_after_closing_file()
    {
        try {
            $subj = $this->newSubject();
            $subj->open('php://temp');
            $subj->close();
        } catch (\Exception $e) {
            $this->fail('Unexpected exception '.$e);
        }
        $subj->read();
    }

    public function test_open_accepts_and_allows_reading_to_existing_stream_resource()
    {
        $file = fopen('php://memory', 'r');
        try {
            $subj = $this->newSubject();
            $subj->open($file);
            $subj->read();
            rewind($file);
            $this->assertSame('', stream_get_contents($file));
        } finally {
            fclose($file);
        }
    }

    /**
     * @expectedException \LogicException
     */
    public function test_it_throws_if_reading_to_externally_closed_resource()
    {
        $file = fopen('php://memory', 'r');
        try {
            $subj = $this->newSubject();
            $subj->open($file);
        } finally {
            fclose($file);
        }

        $subj->read();
    }

    public function test_it_can_open_and_read_to_filename()
    {
        $name = __DIR__ . '/./csv-test.csv';
        $subj = $this->newSubject();
        $subj->open($name, []);
        $subj->read();
        $subj->read();
        $subj->close();
        $this->assertEquals("#,row1,row2,row3\n1,value1,value2,value3\n", file_get_contents($name));
    }

    public function test_it_read_column_headers_before_first_row()
    {
        $file = fopen(__DIR__ . '/./csv-test.csv', 'r');
        $subj = $this->newSubject();
        $subj->open($file);
        $subj->read();
        $this->assertCSVContent(
            [
                ['#', 'row1', 'row2', 'row3'],
                ['1', 'value1', 'value2', 'value3']
            ],
            $file
        );
        fclose($file);
    }

    public function test_it_optionally_reads_byte_order_mark_at_start_of_file()
    {
        $file = fopen(__DIR__ . '/../../fixtures/utf8-bom.csv', 'r');
        $subj = $this->newSubject();
        $subj->open($file, ['read_utf8_bom' => true]);
        $subj->read();
        rewind($file);
        $this->assertSame(CSVReader::UTF8_BOM, fread($file, strlen(CSVReader::UTF8_BOM)));
    }

    public function test_it_can_read_one_row_from_file()
    {
        $file = fopen(__DIR__ . '/./csv-test.csv', 'r');
        $subj = $this->newSubject();
        $subj->open($file);
        $this->assertSame(
            [
                '#',
                'row1',
                'row2',
                'row3',
            ],
            $subj->read()
        );
    }

    public function test_it_does_not_close_externally_provided_stream()
    {
        $file = fopen(__DIR__ . '/./csv-test.csv', 'r');
        try {
            $subj = $this->newSubject();
            $subj->open($file);
            $subj->close();
            $this->assertTrue(is_resource($file));
        } finally {
            fclose($file);
        }
    }

    protected function assertCSVContent(array $expect, $file)
    {
        rewind($file);
        $actual = [];
        while ($row = fgetcsv($file)) {
            $actual[] = $row;
        }

        $this->assertSame($expect, $actual, 'CSV content should match');
    }


    protected function newSubject()
    {
        return new CSVReader();
    }

}
