<?php

declare(strict_types=1);

namespace Noem\Container;

class AttributeProxyList implements \ArrayAccess, \Iterator
{

    private array $instances = [];
    /**
     * @var \ReflectionAttribute[]
     */
    private array $reflectionAttributes = [];

    public function offsetExists($offset): bool
    {
        return isset($this->reflectionAttributes[$offset]);
    }

    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            $this->reflectionAttributes[] = $value;
            return;
        }
        unset($this->instances[$offset]);
        $this->reflectionAttributes[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->instances[$offset]);
        unset($this->reflectionAttributes[$offset]);
    }

    public function current()
    {
        return $this->offsetGet($this->key());
    }

    public function offsetGet($offset)
    {
        if (!isset($this->instances[$offset])) {
            $this->instances[$offset] = $this->reflectionAttributes[$offset]->newInstance();
        }
        return $this->instances[$offset];
    }

    public function key(): float|bool|int|string|null
    {
        return key($this->reflectionAttributes);
    }

    public function next()
    {
        next($this->reflectionAttributes);
    }

    public function valid(): bool
    {
        return current($this->reflectionAttributes) !== false;
    }

    public function rewind()
    {
        reset($this->reflectionAttributes);
    }
}
