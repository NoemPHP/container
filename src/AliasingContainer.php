<?php

namespace Noem\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class AliasingContainer implements ContainerInterface
{
    public function __construct(private array $aliases, private ContainerInterface $inner)
    {
    }

    public function get(string $id)
    {
        return $this->inner->get($this->resolveIdAlias($id));
    }

    private function resolveIdAlias(string $maybeAliased): string
    {
        if (!isset($this->aliases[$maybeAliased])) {
            return $maybeAliased;
        }
        return $this->aliases[$maybeAliased];
    }

    public function has(string $id): bool
    {
        return $this->inner->has($this->resolveIdAlias($id));
    }
}
