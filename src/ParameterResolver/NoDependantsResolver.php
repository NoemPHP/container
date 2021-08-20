<?php

declare(strict_types=1);

namespace Noem\Container\ParameterResolver;

use Invoker\ParameterResolver\ParameterResolver;
use ReflectionFunctionAbstract;

class NoDependantsResolver implements ParameterResolver
{

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
            $type = $parameter->getType()->getName();
            if (!class_exists($type)) {
                continue;
            }
            $refClass = new \ReflectionClass($type);
            $constructor = $refClass->getConstructor();
            if (!$constructor) {
                goto success;
            }
            $constructorParams = $constructor->getParameters();
            if (!count($constructorParams)) {
                goto success;
            }
            foreach ($constructorParams as $constructorParam) {
                if (
                    !($constructorParam->allowsNull()
                        || $constructorParam->isDefaultValueAvailable()
                        || $constructorParam->isVariadic()
                    )
                ) {
                    continue 2;
                }
            }
            success:
            $resolvedParameters[$index] = new $type();
        }

        return $resolvedParameters;
    }
}
