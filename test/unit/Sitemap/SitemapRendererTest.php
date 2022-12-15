<?php

namespace test\unit\Ingenerator\PHPUtils\Sitemap;


use Ingenerator\PHPUtils\Sitemap\SitemapParser;
use Ingenerator\PHPUtils\Sitemap\SitemapRenderer;
use PHPUnit\Framework\TestCase;

class SitemapRendererTest extends TestCase
{
    public function test_it_is_initialisable(): void
    {
        $this->assertInstanceOf(SitemapRenderer::class, $this->newSubject());
    }

    public function test_it_throws_on_attempt_to_render_empty_map(): void
    {
        // Sitemaps aren't allowed to contain no <url> elements
        $subject = $this->newSubject();
        $this->expectException(\UnderflowException::class);
        $subject->render();
    }

    public function test_it_throws_on_attempt_to_modify_after_render(): void
    {
        $subject = $this->newSubject();
        $subject->addUrl('http://any.thing/wherever');
        $subject->render();
        $this->expectException(\LogicException::class);
        $subject->addUrl('http://any.thing/other');
    }

    public function test_it_throws_on_attempt_to_render_multiple_times(): void
    {
        $subject = $this->newSubject();
        $subject->addUrl('http://any.thing/wherever');
        $subject->render();
        $this->expectException(\LogicException::class);
        $subject->render();
    }

    public function test_it_renders_valid_sitemap_with_simple_entry(): void
    {
        $subject = $this->newSubject();
        $subject->addUrl('https://foo.bar/baz');
        $result = $subject->render();
        $this->assertSame(
            [
                'https://foo.bar/baz' => [
                    'lastmod' => null,
                    'changefreq' => null,
                    'priority' => null,
                ],
            ],
            SitemapParser::parse($result)
        );
    }

    public function test_it_renders_valid_sitemap_with_optional_parameters(): void
    {
        $subject = $this->newSubject();
        $subject->addUrl(
            'https://foo.bar/baz',
            lastmod: new \DateTimeImmutable('2022-02-03 10:30:20'),
            changefreq: 'monthly',
            priority: 0.3
        );
        $result = $subject->render();
        $this->assertSame(
            [
                'https://foo.bar/baz' => [
                    'lastmod' => '2022-02-03',
                    'changefreq' => 'monthly',
                    'priority' => '0.3',
                ],
            ],
            SitemapParser::parse($result)
        );
    }

    public function test_it_escapes_special_characters_in_urls(): void
    {
        $subject = $this->newSubject();
        $subject->addUrl('https://foo.bar/baz?any=thing&other=thing');
        $result = $subject->render();
        $this->assertSame(
            [
                'https://foo.bar/baz?any=thing&other=thing' => [
                    'lastmod' => null,
                    'changefreq' => null,
                    'priority' => null,
                ],
            ],
            SitemapParser::parse($result)
        );
    }

    public function test_it_includes_multiple_urls(): void
    {
        $subject = $this->newSubject();
        $subject->addUrl('http://foo.bar/home', new \DateTimeImmutable('2022-01-03'), 'monthly', 0.5);
        $subject->addUrl('http://foo.bar/news', new \DateTimeImmutable('2022-05-04'), 'daily', 0.7);
        $subject->addUrl('http://foo.bar/misc');
        $this->assertSame(
            [
                'http://foo.bar/home' => [
                    'lastmod' => '2022-01-03',
                    'changefreq' => 'monthly',
                    'priority' => '0.5',
                ],
                'http://foo.bar/news' => [
                    'lastmod' => '2022-05-04',
                    'changefreq' => 'daily',
                    'priority' => '0.7',
                ],
                'http://foo.bar/misc' => [
                    'lastmod' => null,
                    'changefreq' => null,
                    'priority' => null,
                ],
            ],
            SitemapParser::parse($subject->render())
        );
    }

    private function newSubject(): SitemapRenderer
    {
        return new SitemapRenderer();
    }
}
