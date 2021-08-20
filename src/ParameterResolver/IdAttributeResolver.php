<?php

declare(strict_types=1);

namespace Noem\Container\ParameterResolver;

use Invoker\ParameterResolver\ParameterResolver;
use Noem\Container\Attribute\Id;
use Psr\Container\ContainerInterface;
use ReflectionFunctionAbstract;

class IdAttributeResolver implements ParameterResolver
{

    public function __construct(private ContainerInterface $container)
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
            $attributes = $parameter->getAttributes(Id::class);
            $id = reset($attributes);
            if (!$id) {
                continue;
            }
            $id = $id->newInstance();
            assert($id instanceof Id);
            $resolved = $this->container->get($id->name);

            if (!$parameter->isVariadic()) {
                $resolvedParameters[$index] = $resolved;
                continue;
            }

            $resolved = (array)$resolved;
            for ($i = 0; $i < count($resolved); $i++) {
                $resolvedParameters[$index + $i] = $resolved[$i];
            }
        }

        return $resolvedParameters;
    }
}
