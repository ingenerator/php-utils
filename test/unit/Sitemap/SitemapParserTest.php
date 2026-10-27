<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Sitemap;

use Ingenerator\PHPUtils\Sitemap\SitemapParser;
use InvalidArgumentException;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SitemapParserTest extends TestCase
{
    /**
     * @return Iterator<int, array<string>>
     */
    public static function provider_invalid(): Iterator
    {
        yield [
            '{"json":true}',
            'Start tag expected',
        ];
        yield [
            '<?xml version="1.0" encoding="UTF-8"?><foobar></foobar>',
            'foobar',
        ];
        yield [
            // NB that a sitemap must have at least one URL to be valid
            '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
            'Missing child element(s)',
        ];
        yield [
            // NB that a sitemap must have at least one URL to be valid
            <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                       <url>
                            <randomness>foobar</randomness>
                        </url>  
                    </urlset>
                    XML,
            'randomness',
        ];
    }

    #[DataProvider('provider_invalid')]
    public function testItThrowsOnAttemptToParseInvalidXmlOrIfNotSchemaValid(string $input, string $expect_msg): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expect_msg);
        SitemapParser::parse($input);
    }

    public function testItThrowsWithDuplicateUrlEntries(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate sitemap entry for http://www.example.com/foobar');
        SitemapParser::parse(
            <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url>
                      <loc>http://www.example.com/foobar</loc>
                      <lastmod>2005-01-01</lastmod>
                      <priority>0.8</priority>
                   </url>  
                   <url>
                      <loc>http://www.example.com/foobar</loc>
                      <lastmod>2010-05-02</lastmod>
                      <changefreq>monthly</changefreq>
                      <priority>0.5</priority>
                   </url>  
                </urlset>
                XML,
        );
    }

    /**
     * @return Iterator<int, array<mixed>>
     */
    public static function provider_parse(): Iterator
    {
        yield [
            // Simple, one with all props
            <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                       <url>
                          <loc>http://www.example.com/</loc>
                          <lastmod>2005-01-01</lastmod>
                          <changefreq>monthly</changefreq>
                          <priority>0.8</priority>
                       </url>  
                    </urlset>
                    XML,
            [
                'http://www.example.com/' => [
                    'lastmod' => '2005-01-01',
                    'changefreq' => 'monthly',
                    'priority' => '0.8',
                ],
            ],
        ];
        yield [
            // Simple, one with no extra props
            <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                       <url>
                          <loc>http://www.example.com/</loc>
                       </url>  
                    </urlset>
                    XML,
            [
                'http://www.example.com/' => [
                    'lastmod' => null,
                    'changefreq' => null,
                    'priority' => null,
                ],
            ],
        ];
        yield [
            // Multiples
            <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                        <url>
                          <loc>http://www.example.com/foobar</loc>
                          <lastmod>2005-01-01</lastmod>
                          <priority>0.8</priority>
                       </url>  
                       <url>
                          <loc>http://www.example.com/biffbox</loc>
                          <lastmod>2010-05-02</lastmod>
                          <changefreq>monthly</changefreq>
                          <priority>0.5</priority>
                       </url>  
                    </urlset>
                    XML,
            [
                'http://www.example.com/foobar' => [
                    'lastmod' => '2005-01-01',
                    'changefreq' => null,
                    'priority' => '0.8',
                ],
                'http://www.example.com/biffbox' => [
                    'lastmod' => '2010-05-02',
                    'changefreq' => 'monthly',
                    'priority' => '0.5',
                ],
            ],
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $expect
     */
    #[DataProvider('provider_parse')]
    public function testItParsesValidSitemapToMapOfUrlToProperties(string $xml, array $expect): void
    {
        $this->assertSame($expect, SitemapParser::parse($xml));
    }
}
