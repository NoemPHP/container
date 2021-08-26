<?php

declare(strict_types=1);

namespace Noem\Container\ParameterResolver;

use Invoker\ParameterResolver\ParameterResolver;
use Noem\Container\Attribute\Id;
use Noem\Container\Attribute\Tagged;
use Noem\Container\Attribute\WithAttr;
use Noem\Container\AttributeAwareContainer;
use Noem\Container\Container;
use Noem\Container\Exception\NotFoundException;
use Noem\Container\Exception\ServiceInvokationException;
use Noem\Container\TaggableContainer;
use Psr\Container\ContainerInterface;
use ReflectionFunctionAbstract;

class AttributeResolver implements ParameterResolver
{

    public function __construct(private AttributeAwareContainer $container)
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
            $attributes = $parameter->getAttributes(WithAttr::class);
            $withAttr = reset($attributes);
            if (!$withAttr) {
                continue;
            }
            $withAttr = $withAttr->newInstance();
            assert($withAttr instanceof WithAttr);
            $matchProps = $withAttr->matchProperties;
            $match = empty($matchProps) ? null : function ($attribute) use ($matchProps) {
                foreach ($matchProps as $prop => $value) {
                    if (!property_exists($attribute, $prop)) {
                        return false;
                    }
                    if ($attribute->$prop !== $value) {
                        return false;
                    }
                }
                return true;
            };
            $services = $this->container->getIdsWithAttribute($withAttr->name, $match);
            try {
                $resolved = array_map(fn(string $s) => $this->container->get($s), $services);
                for ($i = 0; $i < count($resolved); $i++) {
                    $resolvedParameters[$index + $i] = $resolved[$i];
                }
            } catch (\Throwable $e) {
            }
        }

        return $resolvedParameters;
    }
}
