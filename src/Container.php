<?php

declare(strict_types=1);

namespace Noem\Container;

use Invoker\Exception\InvocationException;
use Invoker\Exception\NotCallableException;
use Invoker\Exception\NotEnoughParametersException;
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
use Noem\Container\ParameterResolver\CircularDependencyResolver;
use Noem\Container\ParameterResolver\IdAttributeResolver;
use Noem\Container\ParameterResolver\NoDependantsResolver;
use Noem\Container\ParameterResolver\TaggedAttributeResolver;
use Noem\TinyProxy\TinyProxy;
use Psr\Container\ContainerInterface;
use ReflectionParameter;

class Container implements TaggableContainer, AttributeAwareContainer
{


    private array $attributeMap;

    private Invoker $invoker;

    private ResolverChain $resolver;
    private AggregateProvider $aggregateProvider;
    private ContainerInterface $baseContainer;

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
        $providers['_internal'] = $this->createInternalProvider();

        $this->aggregateProvider = new AggregateProvider(...$providers);
        try {
            $this->attributeMap = $this->processAttributes($this->aggregateProvider->getFactories());
        } catch (\Throwable $e) {
            throw new ContainerBootstrapException('', 0, $e);
        }
        $autowiringContainer = new AutowiringContainer($this->invoker, $this->aggregateProvider);
        $resolvingContainer = new CircularDependencyResolvingContainer($autowiringContainer);
        $resolvingContainer->setContainer($this);
        $autowiringContainer->setContainer($this);

        $this->baseContainer = new AliasingContainer(
            $this->processAliases(),
            new CachingContainer($resolvingContainer, fn($id)=>$resolvingContainer->createProxy($id))
        );
    }

    private function createInternalProvider(): Provider
    {
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
        return new ServiceProvider($factories);
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
    private function processAliases(): array
    {
        $result = [];
        if (!isset($this->attributeMap[Alias::class])) {
            return [];
        }
        foreach ($this->attributeMap[Alias::class] as $id => $aliases) {
            foreach ($aliases as $alias) {
                assert($alias instanceof Alias);
                $alias = $alias->name;
                if (isset($this->factories[$alias])) {
                    throw new ContainerBootstrapException(
                        sprintf(
                            'Cannot create alias %s of service %s ' .
                            'because a service of the same name is already defined',
                            $alias,
                            $id
                        ),
                    );
                }
                $result[$alias] = $id;
            }
        }
        return $result;
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
        return $this->baseContainer->get($id);
    }

    public function has($id): bool
    {
        return $this->baseContainer->has($id);
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
            ['ID', 'Module', 'returnType', 'Tags', 'Description', 'Attributes'],
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
                $this->moduleMap[$id],
                $this->getReturnType($factory, $id),
                $tags,
                $this->getDescription($id),
                array_map(fn($a) => get_class($a), $this->getAttributesOfId($id))
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
        $descriptionAttrs = $this->getAttributesOfId($id, Description::class);
        if (empty($descriptionAttrs)) {
            return '';
        }
        return $descriptionAttrs[0]->text;
    }
}
