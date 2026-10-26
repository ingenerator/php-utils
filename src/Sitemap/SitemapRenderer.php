<?php

declare(strict_types=1);

namespace Ingenerator\PHPUtils\Sitemap;

use DateTimeImmutable;
use LogicException;
use UnderflowException;

use function htmlspecialchars;
use function implode;

class SitemapRenderer
{
    /**
     * @var list<string>
     */
    private array $urls = [];

    private bool $is_rendered = false;

    public function addUrl(
        string $url,
        ?DateTimeImmutable $lastmod = null,
        ?string $changefreq = null,
        ?float $priority = null,
    ): void {
        if ($this->is_rendered) {
            throw new LogicException('Cannot modify a sitemap after it has been rendered');
        }

        $xml = '<loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>';
        if ($lastmod instanceof DateTimeImmutable) {
            $xml .= '<lastmod>'.$lastmod->format('Y-m-d').'</lastmod>';
        }
        if ($changefreq !== null) {
            $xml .= '<changefreq>'.$changefreq.'</changefreq>';
        }
        if ($priority !== null) {
            $xml .= '<priority>'.$priority.'</priority>';
        }

        $this->urls[] = "<url>$xml</url>";
    }

    public function render(): string
    {
        if ($this->is_rendered) {
            throw new LogicException('Cannot render a sitemap more than once');
        }

        if ($this->urls === []) {
            throw new UnderflowException('Cannot render a sitemap containing no <url> entries');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .implode('', $this->urls)
            .'</urlset>';

        // Mark rendered to prevent reuse and clear the urls collection to release the memory
        $this->is_rendered = true;
        $this->urls = [];

        return $xml;
    }
}
