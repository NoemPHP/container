<?php

namespace Noem\Container;

use Noem\Container\Exception\ServiceInvokationException;
use Noem\TinyProxy\TinyProxy;
use Psr\Container\ContainerInterface;

class CircularDependencyResolvingContainer implements ContainerInterface
{
    private array $currentDepChain = [];

    public function __construct(private FactoryProvidingContainer $inner, ?ContainerInterface $outerContainer = null)
    {
        $this->outerContainer = $outerContainer ?? $this->inner;
    }

    public function setContainer(ContainerInterface $container)
    {
        $this->outerContainer = $container;
    }

    public function get($id)
    {
        if ($this->isCircularDependency($id)) {
            return $this->createProxy($id);
        }
        $this->currentDepChain[] = $id;
        $result = $this->inner->get($id);
//        $this->currentDepChain = [];
        return $result;
    }

    public function has($id): bool
    {
        return $this->inner->has($id);
    }

    private function isCircularDependency(string $id)
    {
        return in_array($id, $this->currentDepChain);
    }

    private function isClass(string $id, callable $factory): bool
    {
        try {
            TinyProxy::subjectClassName($id, $factory);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @throws ServiceInvokationException
     */
    public function createProxy(string $id)
    {
        $factory = $this->inner->getFactory($id);

        try {
            $serviceFQCN = TinyProxy::subjectClassName($id, $factory);
            $proxyFQCN = TinyProxy::proxyClassName($serviceFQCN);
            if (!class_exists($proxyFQCN)) {
                $phpCode = TinyProxy::generateCode($serviceFQCN);
                eval($phpCode);
            }
            return new $proxyFQCN(fn() => $this->outerContainer->get($id));
        } catch (\Throwable $e) {
            throw new ServiceInvokationException(
                sprintf(
                    <<<'OH_NO'
Recursive dependency chain detected at service "%s". Breaking the chain with a proxy has failed.
To solve this Problem, please either

1. Refactor your service configuration to get rid of the recursion
2. Declare a concrete return type one of the factory signatures
3. Use a concrete FQCN as the service ID
OH_NO,
                    $id
                )

                . PHP_EOL .
                print_r($this->currentDepChain, true),
                0,
                $e
            );
        }
    }
}
