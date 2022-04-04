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

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->reflectionAttributes[] = $value;
            return;
        }
        unset($this->instances[$offset]);
        $this->reflectionAttributes[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->instances[$offset]);
        unset($this->reflectionAttributes[$offset]);
    }

    public function current(): mixed
    {
        return $this->offsetGet($this->key());
    }

    public function offsetGet($offset): mixed
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

    public function next(): void
    {
        next($this->reflectionAttributes);
    }

    public function valid(): bool
    {
        return current($this->reflectionAttributes) !== false;
    }

    public function rewind(): void
    {
        reset($this->reflectionAttributes);
    }
}
