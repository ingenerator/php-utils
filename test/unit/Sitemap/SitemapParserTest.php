<?php

namespace test\unit\Ingenerator\PHPUtils\Sitemap;

use Ingenerator\PHPUtils\Sitemap\SitemapParser;
use PHPUnit\Framework\TestCase;

class SitemapParserTest extends TestCase
{
    public function provider_invalid(): array
    {
        return [
            [
                '{"json":true}',
                'Start tag expected',
            ],
            [
                '<?xml version="1.0" encoding="UTF-8"?><foobar></foobar>',
                'foobar',
            ],
            [
                // NB that a sitemap must have at least one URL to be valid
                '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
                'Missing child element(s)',
            ],
            [
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
            ],
        ];
    }

    /**
     * @dataProvider provider_invalid
     */
    public function test_it_throws_on_attempt_to_parse_invalid_xml_or_if_not_schema_valid($input, $expect_msg): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expect_msg);
        SitemapParser::parse($input);
    }

    public function test_it_throws_with_duplicate_url_entries(): void
    {
        $this->expectException(\InvalidArgumentException::class);
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

    public function provider_parse(): array
    {
        return [
            [
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
            ],
            [
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
            ],
            [
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
            ],
        ];
    }

    /**
     * @dataProvider provider_parse
     */
    public function test_it_parses_valid_sitemap_to_map_of_url_to_properties($xml, $expect): void
    {
        $this->assertSame($expect, SitemapParser::parse($xml));
    }
}
