<?php

namespace Noem\Container;

use Invoker\Exception\InvocationException;
use Invoker\Exception\NotCallableException;
use Invoker\Invoker;
use Invoker\InvokerInterface;
use Invoker\ParameterResolver\ParameterResolver;
use Noem\Container\Exception\NotFoundException;
use Noem\Container\Exception\ServiceInvokationException;
use Noem\TinyProxy\TinyProxy;
use Psr\Container\ContainerInterface;
use ReflectionParameter;

class AutowiringContainer implements FactoryProvidingContainer
{
    private $extensions;

    public function __construct(
        private Invoker $invoker,
        private Provider $provider,
        ?ContainerInterface $baseContainer = null
    ) {
        $this->extensions = $this->provider->getExtensions();
        $this->baseContainer = $baseContainer ?? $this;
    }

    public function setContainer(ContainerInterface $container)
    {
        $this->baseContainer = $container;
    }

    public function get($id)
    {
//        if ($this->has($id)) {
//            return $this->baseContainer->get($id);
//        }
        try {
            return $this->createService($id);
        } catch (InvocationException $e) {
            throw new ServiceInvokationException(
                sprintf(
                    "Service '%s' could not be auto-wired",
                    $id
                ),
                0,
                $e
            );
        }
    }

    private function createService(string $id): mixed
    {
        if (!$this->has($id)) {
            if (class_exists($id)) {
                return $this->resolveInstance($id);
            }
            throw new NotFoundException("Service id '{$id}' not found in container");
        }
        $service = $this->invoker->call($this->provider->getFactories()[$id]);

        if (!array_key_exists($id, $this->extensions)) {
            return $service;
        }

        return $this->invoker->call($this->extensions[$id], [$service]);
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
        $args = $this->invoker->getParameterResolver()->getParameters($reflection, [], []);

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

    public function has($id): bool
    {
        return isset($this->provider->getFactories()[$id]);
    }

    public function getFactory(string $id): callable
    {
        return $this->provider->getFactories()[$id];
    }
}
