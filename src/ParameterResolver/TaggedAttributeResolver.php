<?php

declare(strict_types=1);

namespace Noem\Container\ParameterResolver;

use Invoker\ParameterResolver\ParameterResolver;
use Noem\Container\Attribute\Id;
use Noem\Container\Attribute\Tag;
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
            $attribute = reset($attributes);
            if (!$attribute) {
                continue;
            }
            $attribute = $attribute->newInstance();
            assert($attribute instanceof Tagged);
            $services = $this->container->getIdsWithTag($attribute->name);

            $prioritized = [];
            foreach ($services as $serviceId) {
                $tag = $this->findMatchingTag($serviceId, $attribute->name);
                if (!$tag) {
                    continue;
                }
                $prioritized[$serviceId] = $tag->priority;
            }
            asort($prioritized, SORT_NUMERIC);

            $resolved = array_map(
                fn(string $s) => $this->container->get($s),
                array_keys($prioritized)
            );

            for ($i = 0; $i < count($resolved); $i++) {
                $resolvedParameters[$index + $i] = $resolved[$i];
            }
        }

        return $resolvedParameters;
    }

    private function findMatchingTag(string $serviceId, string $tagName): ?Tag
    {
        $attributes = $this->container->getAttributesOfId($serviceId, Tag::class);
        foreach ($attributes as $attribute) {
            assert($attribute instanceof Tag);
            if ($attribute->name === $tagName) {
                return $attribute;
            }
        }

        return null;
    }
}
