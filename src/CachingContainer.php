<?php

namespace Noem\Container;

use Noem\Container\Exception\ServiceInvokationException;
use Psr\Container\ContainerInterface;

class CachingContainer implements ContainerInterface
{
    private array $cache = [];
    /**
     * @var callable|\Closure
     */
    private $onAccessDuringInstantiation;

    public function __construct(private ContainerInterface $source, ?callable $onAccessDuringInstantiation = null)
    {
        $this->onAccessDuringInstantiation = $onAccessDuringInstantiation ?? function ($id) {
                throw new ServiceInvokationException(sprintf(
                    <<<'WHOOPS'
The service "%s" was requested again before it was first instantiated. 
WHOOPS,
                    $id
                ));
        };
    }

    public function get($id)
    {
        if (!array_key_exists($id, $this->cache)) {
            $this->cache[$id] = function () use ($id) {
                static $started;
                static $result;
                static $finished;

                if ($started === null) {
                    $started = true;
                    $result = $this->source->get($id);
                    $finished = true;
                }
                /**
                 * The cache might be accessed WHILE it is being built.
                 * Check if we have finished creating the actual object.
                 * If not, kick off our handler for potential recovery
                 * (Or explode if no outside handler was passed)
                 */
                if ($finished === null) {
                    return ($this->onAccessDuringInstantiation)($id);
                }
                return $result;
            };
        }
        return $this->cache[$id]();
    }

    public function has($id): bool
    {
        return $this->source->has($id);
    }
}
