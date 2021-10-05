<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */


namespace Ingenerator\PHPUtils\Monitoring;


class MetricId
{
    const SOURCE_HOST_REPLACEMENT = '###HOST###';

    protected ?string $name;

    protected ?string $source;

    /**
     * @param string|null $name
     * @param string|null $source
     *
     * @deprecated use one of the named constructors
     */
    public function __construct(?string $name, ?string $source)
    {
        $this->name = $name;

        if ($source === NULL) {
            $this->source = self::SOURCE_HOST_REPLACEMENT;
        } else {
            $this->source = $source;
        }
    }

    public static function forHost(string $name): MetricId
    {
        return new MetricId($name, self::SOURCE_HOST_REPLACEMENT);
    }

    public static function nameOnly(string $name): MetricId
    {
        $m = new MetricId($name, NULL);
        $m->setSource(NULL);

        return $m;
    }

    public static function nameAndSource(?string $name, ?string $source): MetricId
    {
        $m = new MetricId($name, $source);
        $m->setSource($source);

        return $m;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): void
    {
        $this->source = $source;
    }
}
