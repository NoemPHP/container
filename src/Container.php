<?php

declare(strict_types=1);

namespace Noem\Container;

use Invoker\Exception\InvocationException;
use Invoker\Exception\NotCallableException;
use Invoker\Invoker;
use Invoker\InvokerInterface;
use Invoker\ParameterResolver\Container\TypeHintContainerResolver;
use Invoker\ParameterResolver\DefaultValueResolver;
use Invoker\ParameterResolver\NumericArrayResolver;
use Invoker\ParameterResolver\ResolverChain;
use Invoker\ParameterResolver\TypeHintResolver;
use Invoker\Reflection\CallableReflection;
use Noem\Container\Attribute\Description;
use Noem\Container\Attribute\Tag;
use Noem\Container\Exception\NotFoundException;
use Noem\Container\Exception\ServiceInvokationException;
use Noem\Container\ParameterResolver\IdAttributeResolver;
use Noem\Container\ParameterResolver\NoDependantsResolver;
use Noem\Container\ParameterResolver\TaggedAttributeResolver;
use Psr\Container\ContainerInterface;
use ReflectionParameter;

class Container implements TaggableContainer
{

    private array $factories;

    private array $extensions;

    private array $attributeMap;

    private array $cache = [];

    private Invoker $invoker;

    private ResolverChain $resolver;

    public function __construct(Provider ...$providers)
    {
        $this->resolver = new ResolverChain([
                                                new NumericArrayResolver(),
                                                new TypeHintResolver(),
                                                new IdAttributeResolver($this),
                                                new TaggedAttributeResolver($this),
                                                new TypeHintContainerResolver($this),
                                                new DefaultValueResolver(),
                                                new NoDependantsResolver(),
                                            ]);
        $this->invoker = new Invoker($this->resolver, $this);

        $factories = [
            ContainerInterface::class =>
                #[Description('The Noem Application Container')]
                fn() => $this,
            self::class =>
                #[Description('The Noem Application Container')]
                fn() => $this,
            InvokerInterface::class =>
                #[Description('The Noem autowiring helper')]
                fn() => $this->invoker
        ];
        $extensions = [];

        foreach ($providers as $provider) {
            $factories = array_merge(
                $factories,
                $provider->getFactories(),
            );
            $extensions = $this->mergeExtensions($extensions, $provider->getExtensions());
        }

        $this->factories = $factories;
        $this->extensions = $extensions;
        $this->attributeMap = $this->processAttributes($factories);
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
            $merged[$key] = function ($previous) use ($default, $extension) {
                assert(is_callable($default));

                $result = $this->invoker->call($default, [$previous]);

                return $this->invoker->call($extension, [$result]);
            };

            unset($defaults[$key]);
        }

        return array_merge($defaults, $merged);
    }

    private function processAttributes(array $factories): array
    {
        $result = [];
        foreach ($factories as $id => $factory) {
            $ref = new \ReflectionFunction($factory);
            $attributes = $ref->getAttributes();
            foreach ($attributes as $attribute) {
                $result[$attribute->getName()][$id][] = $attribute->newInstance();
            }
        }

        return $result;
    }

    /**
     * @throws NotFoundException|ServiceInvokationException
     */
    public function get($id)
    {
        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }

        try {
            return $this->cache[$id] = $this->createService($id);
        } catch (InvocationException $e) {
            throw new ServiceInvokationException("Service id '{$id}' not found in container", 0, $e);
        }
    }

    /**
     * Creates a service by invoking its factory as well as its extensions
     *
     * @param string $id
     *
     * @return mixed
     * @throws NotFoundException
     * @throws InvocationException
     */
    private function createService(string $id): mixed
    {
        if (!$this->has($id)) {
            if (class_exists($id)) {
                return $this->resolveInstance($id);
            }
            throw new NotFoundException("Service id '{$id}' not found in container");
        }

        $service = $this->invoker->call($this->factories[$id]);

        if (!array_key_exists($id, $this->extensions)) {
            return $service;
        }

        return $this->invoker->call($this->extensions[$id], [$service]);
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function has($id)
    {
        return array_key_exists($id, $this->factories); //TODO Check if autowiring is possible?
    }

    /**
     * @param string $type
     *
     * @return mixed|void
     * @throws InvocationException
     * @throws NotCallableException
     */
    private function resolveInstance(string $type)
    {
        try {
            if (!(new \ReflectionClass($type))->getConstructor()) {
                return new $type();
            }
            $reflection = CallableReflection::create([$type, '__construct']);
        } catch (\ReflectionException $e) {
            throw new InvocationException("Service id '{$type}' could not be autowired");
        }
        $args = $this->resolver->getParameters($reflection, [], []);

        // Sort by array key because call_user_func_array ignores numeric keys
        ksort($args);

        // Check all parameters are resolved
        $diff = array_diff_key($reflection->getParameters(), $args);
        $parameter = reset($diff);
        if ($parameter && \assert($parameter instanceof ReflectionParameter) && !$parameter->isVariadic()) {
            throw new InvocationException("Service id '{$type}' could not be autowired");
        }

        return new $type(...$args);
    }

    /**
     * @param string $tag
     *
     * @return string[]
     */
    public function getIdsWithTag(string $tag): array
    {
        return $this->getIdsWithAttributes(Tag::class, fn(Tag $attr) => $attr->name === $tag);
    }

    /**
     * @param string $attribute
     * @param callable|null $matching
     * @return string[]
     */
    public function getIdsWithAttributes(string $attribute, ?callable $matching = null): array
    {
        $matching = $matching ?? fn() => true;
        $result = [];

        if (!isset($this->attributeMap[Tag::class])) {
            return [];
        }
        foreach ($this->attributeMap[$attribute] as $id => $attrInstances) {
            if (!$this->matchesAny($matching, $attrInstances)) {
                continue;
            }
            $result[] = $id;
        }
        return $result;
    }

    private function matchesAny(callable $f, array $array): bool
    {
        foreach ($array as $x) {
            if (call_user_func($f, $x) === true) {
                return true;
            }
        }
        return false;
    }

    public function report(): array
    {
        $table = [
            ['ID', 'returnType', 'tags', 'description'],
        ];
        foreach ($this->factories as $id => $factory) {
            $ref = new \ReflectionFunction($factory);
            $refReturnType = $ref->getReturnType();
            $returnType = $refReturnType
                ? $refReturnType->getName()
                : (class_exists($id)
                    ? $id
                    : '');
            $meta = $this->meta[$id];
            $tags = implode(' | ', $meta->tags());
            $table[] = [$id, $returnType, $tags, $meta->description()];
        }

        return $table;
    }
}
