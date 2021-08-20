<?php

declare(strict_types=1);

namespace Noem\Container\ParameterResolver;

use Invoker\ParameterResolver\ParameterResolver;
use Noem\Container\Attribute\Id;
use Noem\Container\Attribute\Tagged;
use Noem\Container\Container;
use Noem\Container\TaggableContainer;
use Psr\Container\ContainerInterface;
use ReflectionFunctionAbstract;

class TaggedAttributeResolver implements ParameterResolver
{

    public function __construct(private TaggableContainer $container)
    {
    }

    public function getParameters(
        ReflectionFunctionAbstract $reflection,
        array $providedParameters,
        array $resolvedParameters
    ) {
        $parameters = $reflection->getParameters();
        // Skip parameters already resolved
        if (!empty($resolvedParameters)) {
            $parameters = array_diff_key($parameters, $resolvedParameters);
        }
        foreach ($parameters as $index => $parameter) {
            $attributes = $parameter->getAttributes(Tagged::class);
            $id = reset($attributes);
            if (!$id) {
                continue;
            }
            $id = $id->newInstance();
            assert($id instanceof Tagged);
            $services = $this->container->getIdsWithTag($id->name);
            $resolved = array_map(fn(string $s) => $this->container->get($s), $services);
            for ($i = 0; $i < count($resolved); $i++) {
                $resolvedParameters[$index + $i] = $resolved[$i];
            }
        }

        return $resolvedParameters;
    }
}
