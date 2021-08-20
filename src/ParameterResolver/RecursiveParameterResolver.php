<?php

declare(strict_types=1);

namespace Noem\Container\ParameterResolver;

use Invoker\ParameterResolver\ParameterResolver;
use Invoker\Reflection\CallableReflection;
use ReflectionFunctionAbstract;
use ReflectionParameter;

class RecursiveParameterResolver implements ParameterResolver
{

    public function __construct(private ParameterResolver $parameterResolver)
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
            $type = $parameter->getType()->getName();
            if (!class_exists($type)) {
                continue;
            }
            if (!method_exists($type, '__construct')) {
                continue;
            }
            try {
                $reflection = CallableReflection::create([$type, '__construct']);
            } catch (\Throwable $e) {
                continue;
            }
            $args = $this->parameterResolver->getParameters($reflection, [], []);

            // Sort by array key because call_user_func_array ignores numeric keys
            ksort($args);

            // Check all parameters are resolved
            $diff = array_diff_key($reflection->getParameters(), $args);
            $parameter = reset($diff);
            if ($parameter && \assert($parameter instanceof ReflectionParameter) && !$parameter->isVariadic()) {
                continue;
            }
            $resolvedParameters[$index] = new $type(...$args);
        }

        return $resolvedParameters;
        //return $this->parameterResolver->getParameters($reflection, $providedParameters, $resolvedParameters);
    }
}
