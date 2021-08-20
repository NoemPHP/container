<?php

declare(strict_types=1);

namespace Noem\Container;

class ServiceProvider implements Provider
{

    public function __construct(private array $factories = [], private array $extensions = [])
    {
    }

    public function getFactories(): array
    {
        return $this->factories;
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }
}
