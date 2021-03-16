<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */


namespace Ingenerator\PHPUtils\Monitoring;


class MetricId
{
    protected ?string $name;

    protected ?string $source;

    public function __construct(?string $name, ?string $source)
    {
        $this->name   = $name;
        $this->source = $source;
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
