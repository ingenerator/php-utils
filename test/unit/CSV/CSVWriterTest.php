<?php

declare(strict_types=1);

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\CSV;

use ErrorException;
use Ingenerator\PHPUtils\CSV\CSVWriter;
use Ingenerator\PHPUtils\CSV\MismatchedSchemaException;
use Iterator;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function fclose;
use function fgetcsv;
use function file_get_contents;
use function fopen;
use function fread;
use function is_resource;
use function rewind;
use function stream_get_contents;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class CSVWriterTest extends TestCase
{
    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(CSVWriter::class, $this->newSubject());
    }

    public function testItThrowsIfFileCannotBeOpened(): void
    {
        $subject = $this->newSubject();
        $this->expectException(ErrorException::class);
        $subject->open('/invalid_csv_file');
    }

    public function testItThrowsIfWritingBeforeOpeningFile(): void
    {
        $subject = $this->newSubject();
        $this->expectException(LogicException::class);
        $subject->write(['any' => 'junk']);
    }

    public function testItThrowsIfWritingAfterClosingFile(): void
    {
        $subj = $this->newSubject();
        $subj->open('php://temp');
        $subj->close();
        $this->expectException(LogicException::class);
        $subj->write(['any' => 'content']);
    }

    public function testOpenAcceptsAndAllowsWritingToExistingStreamResource(): void
    {
        $file = fopen('php://memory', 'w');
        try {
            $subj = $this->newSubject();
            $subj->open($file);
            $subj->write(['some' => 'csv']);
            rewind($file);
            $this->assertNotEmpty(stream_get_contents($file));
        } finally {
            fclose($file);
        }
    }

    public function testItThrowsIfWritingToExternallyClosedResource(): void
    {
        $file = fopen('php://memory', 'w');
        try {
            $subj = $this->newSubject();
            $subj->open($file);
        } finally {
            fclose($file);
        }

        $this->expectException(LogicException::class);
        $subj->write(['some' => 'csv']);
    }

    public function testItCanOpenAndWriteToFilename(): void
    {
        $name = tempnam(sys_get_temp_dir(), 'csv-test.csv');
        try {
            $subj = $this->newSubject();
            $subj->open($name, []);
            $subj->write(['a' => 'b', '1' => 2]);
            $subj->write(['a' => 'c', '1' => 3]);
            $subj->close();
            $this->assertEquals("a,1\nb,2\nc,3\n", file_get_contents($name));
        } finally {
            unlink($name);
        }
    }

    public function testItWritesColumnHeadersBeforeFirstRow(): void
    {
        $file = fopen('php://memory', 'w');
        $subj = $this->newSubject();
        $subj->open($file);
        $subj->write(['our' => 'data', 'is' => 'here']);
        $this->assertCSVContent(
            [
                ['our', 'is'],
                ['data', 'here'],
            ],
            $file
        );
        fclose($file);
    }

    public function testItDoesNotWriteColumnHeadersBeforeSubsequentRows(): void
    {
        $file = fopen('php://memory', 'w');
        $subj = $this->newSubject();
        $subj->open($file);
        $subj->write(['our' => 'data', 'is' => 'here']);
        $subj->write(['our' => 'second', 'is' => 'there']);
        $this->assertCSVContent(
            [
                ['our', 'is'],
                ['data', 'here'],
                ['second', 'there'],
            ],
            $file
        );
        fclose($file);
    }

    /**
     * @return Iterator<string, array<(array<(bool | string)> | string)>>
     */
    public static function providerQuotedHeaders(): Iterator
    {
        $row = ['our data' => 'is at times', 'really big' => 'often'];
        yield 'default (with quotes)' => [
            $row,
            [],
            <<<CSV
                "our data","really big"
                "is at times",often

                CSV,
        ];
        yield 'with quotes' => [
            $row,
            ['quote_headers' => true],
            <<<CSV
                "our data","really big"
                "is at times",often

                CSV,
        ];
        yield 'without quotes' => [
            $row,
            ['quote_headers' => false],
            <<<CSV
                our data,really big
                "is at times",often

                CSV,
        ];
    }

    /**
     * @param array<string, string> $row
     * @param array<string, bool|string> $options
     */
    #[DataProvider('providerQuotedHeaders')]
    public function testItOptionallyWritesColumnHeadersWithoutQuotes(array $row, array $options, string $expect): void
    {
        $file = fopen('php://memory', 'w');
        $subj = $this->newSubject();
        $subj->open($file, $options);
        $subj->write($row);
        rewind($file);
        $this->assertSame($expect, stream_get_contents($file));
        fclose($file);
    }

    public function testItThrowsIfColumnHeadersContainCommaWhenUnquoted(): void
    {
        $file = fopen('php://memory', 'w');
        $subj = $this->newSubject();
        $subj->open($file, ['quote_headers' => false]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Column header `really, really big` cannot contain comma if headers are not quoted');
        $subj->write(['our data' => 'is, at times', 'really, really big' => 'often']);
        fclose($file);
    }

    /**
     * @return Iterator<string, array<mixed>>
     */
    public static function providerLineEndings(): Iterator
    {
        yield 'default' => [
            [],
            "\"our big\",is\ndata,here\n",
        ];
        yield 'default, with unquoted headers' => [
            ['quote_headers' => false],
            "our big,is\ndata,here\n",
        ];
        yield 'unix' => [
            ['eol' => "\n"],
            "\"our big\",is\ndata,here\n",
        ];
        yield 'mac' => [
            ['eol' => "\r"],
            "\"our big\",is\rdata,here\r",
        ];
        yield 'windows' => [
            ['eol' => "\r\n"],
            "\"our big\",is\r\ndata,here\r\n",
        ];
        yield 'windows, with unquoted headers' => [
            ['eol' => "\r\n", 'quote_headers' => false],
            "our big,is\r\ndata,here\r\n",
        ];
    }

    /** @param array<string, bool|string> $options */
    #[DataProvider('providerLineEndings')]
    public function testItsLineEndingsCanBeConfigured(array $options, string $expect): void
    {
        $file = fopen('php://memory', 'w');
        $subj = $this->newSubject();
        $subj->open($file, $options);
        $subj->write(['our big' => 'data', 'is' => 'here']);
        rewind($file);
        $this->assertSame($expect, stream_get_contents($file));
        fclose($file);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testItOptionallyWritesByteOrderMarkAtStartOfFile(bool $write_bom): void
    {
        $file = fopen('php://memory', 'w');
        $subj = $this->newSubject();
        $subj->open($file, ['write_utf8_bom' => $write_bom]);
        $subj->write(['first' => 'row']);
        rewind($file);
        if ($write_bom) {
            $this->assertSame(CSVWriter::UTF8_BOM, fread($file, strlen(CSVWriter::UTF8_BOM)));
        }
        $this->assertSame(['first'], fgetcsv($file, escape: '\\'));
    }

    /** @param array<string, string> $second_row */
    #[TestWith([['is' => 'jumbled', 'our' => 'up']])]
    #[TestWith([['our' => 'things', 'went' => 'bad']])]
    public function testItThrowsIfSubsequentRowHeadersDoNotMatch(array $second_row): void
    {
        $file = fopen('php://memory', 'w');
        try {
            $subj = $this->newSubject();
            $subj->open($file);
            $subj->write(['our' => 'data', 'is' => 'here']);
            $this->expectException(MismatchedSchemaException::class);
            $subj->write($second_row);
        } finally {
            fclose($file);
        }
    }

    public function testItDoesNotCloseExternallyProvidedStream(): void
    {
        $file = fopen('php://memory', 'w');
        try {
            $subj = $this->newSubject();
            $subj->open($file);
            $subj->close();
            $this->assertTrue(is_resource($file));
        } finally {
            fclose($file);
        }
    }

    /** @param array<array<string|null>> $expect */
    protected function assertCSVContent(array $expect, $file): void
    {
        rewind($file);
        $actual = [];
        while ($row = fgetcsv($file, escape: '\\')) {
            $actual[] = $row;
        }

        $this->assertSame($expect, $actual, 'CSV content should match');
    }

    private function newSubject(): CSVWriter
    {
        return new CSVWriter();
    }
}
