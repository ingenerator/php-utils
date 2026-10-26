<?php

namespace Ingenerator\PHPUtils\Sitemap;

use DOMDocument;
use InvalidArgumentException;
use LibXMLError;

use function array_map;
use function implode;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function simplexml_import_dom;
use function sprintf;

class SitemapParser
{
    public static function parse(string $sitemap): array
    {
        $doc = static::validateSitemapXML($sitemap);

        $xml = simplexml_import_dom($doc);
        $urls = [];
        foreach ($xml->url as $x_url) {
            $url = (string) $x_url->loc;
            if (isset($urls[$url])) {
                throw new InvalidArgumentException('Duplicate sitemap entry for '.$url);
            }
            $urls[$url] = [
                'lastmod' => $x_url->lastmod ? (string) $x_url->lastmod : null,
                'changefreq' => $x_url->changefreq ? (string) $x_url->changefreq : null,
                'priority' => $x_url->priority ? (string) $x_url->priority : null,
            ];
        }

        return $urls;
    }

    private static function validateSitemapXML(string $sitemap): DOMDocument
    {
        $old_use_errors = libxml_use_internal_errors(true);
        try {
            $doc = new DOMDocument();
            $doc->loadXML($sitemap);
            $valid = $doc->schemaValidate(__DIR__.'/sitemap.xsd');
            if (!$valid) {
                $errors = array_map(fn ($e) => static::formatError($e), libxml_get_errors());
                throw new InvalidArgumentException("Invalid sitemap XML:\n".implode("\n", $errors));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old_use_errors);
        }

        return $doc;
    }

    private static function formatError(LibXMLError $error): string
    {
        $level = match ($error->level) {
            LIBXML_ERR_WARNING => 'warning',
            LIBXML_ERR_ERROR => 'error',
            LIBXML_ERR_FATAL => 'fatal',
            default => 'unknown ('.$error->level.')',
        };

        return sprintf(
            ' - [%s] %s at %s:%s (code %d)',
            $level,
            trim($error->message),
            $error->line,
            $error->column,
            $error->code,
        );
    }
}
