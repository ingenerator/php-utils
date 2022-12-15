<?php

namespace Ingenerator\PHPUtils\Sitemap;

class SitemapParser
{

    public static function parse(string $sitemap): array
    {
        $doc = static::validateSitemapXML($sitemap);

        $xml  = \simplexml_import_dom($doc);
        $urls = [];
        foreach ($xml->url as $x_url) {
            $url = (string) $x_url->loc;
            if (isset($urls[$url])) {
                throw new \InvalidArgumentException('Duplicate sitemap entry for '.$url);
            }
            $urls[$url] = [
                'lastmod'    => $x_url->lastmod ? (string) $x_url->lastmod : NULL,
                'changefreq' => $x_url->changefreq ? (string) $x_url->changefreq : NULL,
                'priority'   => $x_url->priority ? (string) $x_url->priority : NULL,
            ];
        }

        return $urls;
    }

    private static function validateSitemapXML(string $sitemap): \DOMDocument
    {
        $old_use_errors = \libxml_use_internal_errors(TRUE);
        try {
            $doc = new \DOMDocument;
            $doc->loadXML($sitemap);
            $valid = $doc->schemaValidate(__DIR__.'/sitemap.xsd');
            if ( ! $valid) {
                $errors = \array_map(fn($e) => static::formatError($e), \libxml_get_errors());
                throw new \InvalidArgumentException("Invalid sitemap XML:\n".\implode("\n", $errors));
            }
        } finally {
            \libxml_clear_errors();
            \libxml_use_internal_errors($old_use_errors);
        }

        return $doc;
    }

    private static function formatError(\LibXMLError $error): string
    {
        $level = match ($error->level) {
            LIBXML_ERR_WARNING => 'warning',
            LIBXML_ERR_ERROR => 'error',
            LIBXML_ERR_FATAL => 'fatal',
            default => "unknown (".$error->level.")"
        };

        return \sprintf(
            ' - [%s] %s at %s:%s (code %d)',
            $level,
            trim($error->message),
            $error->line,
            $error->column,
            $error->code,
        );
    }

}
