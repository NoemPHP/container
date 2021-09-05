<?php

namespace Noem\Container;

use Invoker\InvokerInterface;
use Noem\Container;
use Noem\Container\Provider;

class AggregateProvider implements Provider
{
    /**
     * @var array|callable[]
     */
    private array $factories;
    /**
     * @var array|callable[]
     */
    private array $extensions;
    private array $moduleMap;

    public function __construct(Provider ...$providers)
    {
        $factories = [];
        $extensions = [];
        $moduleIdMap = [];
        foreach ($providers as $moduleKey => $provider) {
            $moduleFactories = $provider->getFactories();
            $moduleIdMap = array_merge($moduleIdMap, array_fill_keys(array_keys($moduleFactories), $moduleKey));
            $factories = array_merge($factories, $moduleFactories);
            $extensions = $this->mergeExtensions($extensions, $provider->getExtensions());
        }

        $this->factories = $factories;
        $this->extensions = $extensions;
        $this->moduleMap = $moduleIdMap;
    }

    /**
     * Merged service extensions.
     *
     * @param callable[] $defaults
     * @param callable[] $extensions
     *
     * @return callable[] The merged extensions.
     */
    private function mergeExtensions(array $defaults, array $extensions): array
    {
        $merged = [];

        foreach ($extensions as $key => $extension) {
            assert(is_callable($extension));

            if (!isset($defaults[$key])) {
                $merged[$key] = $extension;

                continue;
            }
            $default = $defaults[$key];
            $merged[$key] = function ($previous, InvokerInterface $invoker) use ($default, $extension) {
                assert(is_callable($default));

                $result = $invoker->call($default, [$previous]);

                return $invoker->call($extension, [$result]);
            };

            unset($defaults[$key]);
        }

        return array_merge($defaults, $merged);
    }

    public function getProviderKey(string $id): string
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
