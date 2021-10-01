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
        $this->name   = $name;
        $this->source = $source;
    }

    public static function forHost(string $name): MetricId
    {
        return new MetricId($name, self::SOURCE_HOST_REPLACEMENT);
    }

    public static function nameOnly(string $name): MetricId
    {
        return new MetricId($name, NULL);
    }

    public static function nameAndSource(?string $name, ?string $source): MetricId
    {
        return new MetricId($name, $source);
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

    public function setSource(string $source): void
    {
        $this->source = $source;
    }
}
