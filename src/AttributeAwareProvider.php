<?php

declare(strict_types=1);

namespace Noem\Container;

use Invoker\InvokerInterface;
use Noem\Container\Attribute\Description;
use Noem\Container\Attribute\Tag;
use Noem\Container\Attribute\Tagged;
use Psr\Container\ContainerInterface as C;

/**
 * @internal
 */
class AttributeAwareProvider implements Provider
{
    public const TAG_ID = '_tags';
    public const DESC_ID = '_descriptions';
    private array $factories;
    private array $extensions;
    private array $tags = [];
    private array $descriptions = [];

    public function __construct(array $factories = [], array $extensions = [])
    {
        foreach ($factories as $id => $factory) {
            $this->factories[$id] = $this->wrapFactory($id, $factory);
        }
        $this->factories[self::TAG_ID] = fn() => [];
        $this->factories[self::DESC_ID] = fn() => [];
        foreach ($extensions as $id => $extension) {
            $this->extensions[$id] = $this->wrapExtension($id, $extension);
        }
        $this->extensions[self::TAG_ID] = fn(C $c, array $p) => $p + $this->tags;
        $this->extensions[self::DESC_ID] = fn(C $c, array $p) => $p + $this->descriptions;
    }

    private function wrapFactory(string $id, callable $function): callable
    {
        $ref = new \ReflectionFunction($function);
        $attributes = $ref->getAttributes();
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            switch (true) {
                case $instance instanceof Tag:
                    $this->tags[$instance->name][] = $id;
                    break;
                case $instance instanceof Description:
                    $this->descriptions[$id][] = $instance->text;
            }
        }
        return function (C $c) use ($function) {
            $params = [];
            $ref = new \ReflectionFunction($function);
            $refParams = $ref->getParameters();
            foreach ($refParams as $index => $refParam) {
                $attributes = $refParam->getAttributes();
                foreach ($attributes as $attribute) {
                    $instance = $attribute->newInstance();
                    switch (true) {
                        case $instance instanceof Tagged:
                            $tagged = $c->get(self::TAG_ID)[$instance->name] ?? [];
                            $resolved = array_map(fn(string $s) => $c->get($s), $tagged);
                            if (!$refParam->isVariadic()) {
                                $params[$index] = $resolved;
                                break;
                            }
                            for ($i = 0; $i < count($resolved); $i++) {
                                $params[$index + $i] = $resolved[$i];
                            }
                            break;
                    }
                }
            }
            $invoker = $c->get(InvokerInterface::class);
            assert($invoker instanceof InvokerInterface);
            return $invoker->call($function, $params);
        };
    }

    private function wrapExtension(string $id, callable $function): callable
    {
        return function (C $c, $previous) use ($function) {
            $invoker = $c->get(InvokerInterface::class);
            assert($invoker instanceof InvokerInterface);
            return $invoker->call($function, [$previous]);
        };
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
