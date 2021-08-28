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
use Noem\Container\Attribute\Alias;
use Noem\Container\Attribute\Description;
use Noem\Container\Attribute\Tag;
use Noem\Container\Exception\ContainerBootstrapException;
use Noem\Container\Exception\NotFoundException;
use Noem\Container\Exception\ServiceInvokationException;
use Noem\Container\ParameterResolver\AttributeResolver;
use Noem\Container\ParameterResolver\IdAttributeResolver;
use Noem\Container\ParameterResolver\NoDependantsResolver;
use Noem\Container\ParameterResolver\TaggedAttributeResolver;
use Psr\Container\ContainerInterface;
use ReflectionParameter;

class Container implements TaggableContainer, AttributeAwareContainer
{

    private array $factories;

    private array $extensions;

    private array $attributeMap;

    private array $cache = [];

    private Invoker $invoker;

    private ResolverChain $resolver;

    /**
     * @throws ContainerBootstrapException
     */
    public function __construct(Provider ...$providers)
    {
        $this->resolver = new ResolverChain([
                                                new NumericArrayResolver(),
                                                new TypeHintResolver(),
                                                new IdAttributeResolver($this),
                                                new AttributeResolver($this),
                                                new TaggedAttributeResolver($this),
                                                new TypeHintContainerResolver($this),
                                                new DefaultValueResolver(),
                                                new NoDependantsResolver(),
                                            ]);
        $this->invoker = new Invoker($this->resolver, $this);

        $factories = [
            ContainerInterface::class =>
                #[Alias(AttributeAwareContainer::class)]
                #[Alias(TaggableContainer::class)]
                #[Alias(self::class)]
                #[Description('The Noem Application Container')]
                fn() => $this,
            InvokerInterface::class =>
                #[Alias(Invoker::class)]
                #[Description('The Noem auto-wiring helper')]
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
        try {
            $this->attributeMap = $this->processAttributes($factories);
            $this->appendAliases();
        } catch (\Throwable $e) {
            throw new ContainerBootstrapException('', 0, $e);
        }
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

    /**
     * @throws \ReflectionException
     */
    private function processAttributes(array $factories): array
    {
        $result = [];
        foreach ($factories as $id => $factory) {
            $ref = new \ReflectionFunction($factory);
            $attributes = $ref->getAttributes();
            foreach ($attributes as $attribute) {
                if (!isset($result[$attribute->getName()][$id])) {
                    $result[$attribute->getName()][$id] = new AttributeProxyList();
                }
                $result[$attribute->getName()][$id][] = $attribute;
            }
        }

        return $result;
    }

    /**
     * @throws \Exception
     */
    private function appendAliases()
    {
        if (!isset($this->attributeMap[Alias::class])) {
            return;
        }
        foreach ($this->attributeMap[Alias::class] as $id => $aliases) {
            foreach ($aliases as $alias) {
                assert($alias instanceof Alias);
                $alias = $alias->name;
                if (isset($this->factories[$alias])) {
                    throw new \Exception(
                        sprintf(
                            'Cannot create alias %s of service %s ' .
                            'because a service of the same name is already defined',
                            $alias,
                            $id
                        ),
                    );
                }
                $this->factories[$alias] = &$this->factories[$id];
            }
        }
    }

    /**
     * @template T
     * @param class-string<T> $id
     * @return mixed
     * @throws NotFoundException|ServiceInvokationException
     * @noinspection PhpMissingReturnTypeInspection
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

    public function has($id): bool
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
            $reflection = new \ReflectionMethod($type, '__construct');
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
        return $this->getIdsWithAttribute(Tag::class, fn(Tag $attr) => $attr->name === $tag);
    }

    /**
     * @param string $attribute
     * @param callable|null $matching
     * @return string[]
     */
    public function getIdsWithAttribute(string $attribute, ?callable $matching = null): array
    {
        $matching = $matching ?? fn() => true;
        $result = [];

        if (!isset($this->attributeMap[$attribute])) {
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

    private function matchesAny(callable $f, iterable $array): bool
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
            $tags = implode(
                ' | ',
                array_map(
                    fn(Tag $t) => $t->name,
                    $this->getAttributesOfId($id, Tag::class)
                )
            );
            $table[] = [
                $id,
                $this->getReturnType($factory, $id),
                $tags,
                $this->getDescription($id)
            ];
        }

        return $table;
    }

    public function getAttributesOfId(string $id, ?string $attributeFQCN = null): array
    {
        $result = [];

        if ($attributeFQCN && !isset($this->attributeMap[$attributeFQCN])) {
            return $result;
        }
        $search = $attributeFQCN ? [$attributeFQCN => &$this->attributeMap[$attributeFQCN]] : $this->attributeMap;
        foreach ($search as $attr => $idAttrs) {
            if (!isset($idAttrs[$id])) {
                continue;
            }
            $result = [...$result, ...$idAttrs[$id]];
        }
        return $result;
    }

    /**
     * @param callable $factory
     * @param string $id In case the service ID is a FQCN, it can be used as a fallback
     * @return string
     */
    private function getReturnType(callable $factory, $id = ''): string
    {
        try {
            $ref = new \ReflectionFunction($factory);
            $refReturnType = $ref->getReturnType();
            return $refReturnType
                ? $refReturnType->getName()
                : (class_exists($id)
                    ? $id
                    : '');
        } catch (\ReflectionException $e) {
            return '';
        }
    }

    private function getDescription(string $id): string
    {
        /**
         * @var $descriptionAttrs Description[]
         */
        $descriptionAttrs = $this->getAttributesOfId(Description::class);
        if (empty($descriptionAttrs)) {
            return '';
        }
        return $descriptionAttrs[0]->text;
    }
}
