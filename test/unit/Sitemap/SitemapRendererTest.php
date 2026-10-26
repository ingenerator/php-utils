<?php

namespace test\unit\Ingenerator\PHPUtils\Sitemap;

use DateTimeImmutable;
use Ingenerator\PHPUtils\Sitemap\SitemapParser;
use Ingenerator\PHPUtils\Sitemap\SitemapRenderer;
use LogicException;
use PHPUnit\Framework\TestCase;
use UnderflowException;

class SitemapRendererTest extends TestCase
{
    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(SitemapRenderer::class, $this->newSubject());
    }

    public function testItThrowsOnAttemptToRenderEmptyMap(): void
    {
        // Sitemaps aren't allowed to contain no <url> elements
        $subject = $this->newSubject();
        $this->expectException(UnderflowException::class);
        $subject->render();
    }

    public function testItThrowsOnAttemptToModifyAfterRender(): void
    {
        $subject = $this->newSubject();
        $subject->addUrl('http://any.thing/wherever');
        $subject->render();
        $this->expectException(LogicException::class);
        $subject->addUrl('http://any.thing/other');
    }

    public function testItThrowsOnAttemptToRenderMultipleTimes(): void
    {
        $subject = $this->newSubject();
        $subject->addUrl('http://any.thing/wherever');
        $subject->render();
        $this->expectException(LogicException::class);
        $subject->render();
    }

    public function testItRendersValidSitemapWithSimpleEntry(): void
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

    public function testItRendersValidSitemapWithOptionalParameters(): void
    {
        $subject = $this->newSubject();
        $subject->addUrl(
            'https://foo.bar/baz',
            lastmod: new DateTimeImmutable('2022-02-03 10:30:20'),
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

    public function testItEscapesSpecialCharactersInUrls(): void
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

    public function testItIncludesMultipleUrls(): void
    {
        $subject = $this->newSubject();
        $subject->addUrl('http://foo.bar/home', new DateTimeImmutable('2022-01-03'), 'monthly', 0.5);
        $subject->addUrl('http://foo.bar/news', new DateTimeImmutable('2022-05-04'), 'daily', 0.7);
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
