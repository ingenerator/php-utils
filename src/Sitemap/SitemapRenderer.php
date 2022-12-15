<?php

namespace Ingenerator\PHPUtils\Sitemap;

class SitemapRenderer
{
    private array $urls = [];

    private bool $is_rendered = FALSE;

    public function addUrl(
        string $url,
        ?\DateTimeImmutable $lastmod = NULL,
        ?string $changefreq = NULL,
        ?float $priority = NULL
    ): void {
        if ($this->is_rendered) {
            throw new \LogicException('Cannot modify a sitemap after it has been rendered');
        }

        $xml = "<loc>".\htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8')."</loc>";
        if ($lastmod !== NULL) {
            $xml .= '<lastmod>'.$lastmod->format('Y-m-d').'</lastmod>';
        }
        if ($changefreq !== NULL) {
            $xml .= '<changefreq>'.$changefreq.'</changefreq>';
        }
        if ($priority !== NULL) {
            $xml .= '<priority>'.$priority.'</priority>';
        }

        $this->urls[] = "<url>$xml</url>";
    }

    public function render(): string
    {
        if ($this->is_rendered) {
            throw new \LogicException('Cannot render a sitemap more than once');
        }

        if ($this->urls === []) {
            throw new \UnderflowException('Cannot render a sitemap containing no <url> entries');
        }


        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .\implode('', $this->urls)
            .'</urlset>';

        // Mark rendered to prevent reuse and clear the urls collection to release the memory
        $this->is_rendered = TRUE;
        $this->urls        = [];

        return $xml;
    }
}
