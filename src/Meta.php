<?php

declare(strict_types=1);

namespace Noem\Container;

use JetBrains\PhpStorm\Pure;

class Meta
{

    private string $description = '';

    private array $tags = [];

    #[Pure] public function withTags(string ...$tags): self
    {
        $clone = clone $this;
        $clone->tags = $tags;

        return $clone;
    }

    #[Pure] public function withDescription(string $description): self
    {
        $clone = clone $this;
        $clone->description = $description;

        return $clone;
    }

    /**
     * @return string[]
     */
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * @return string
     */
    public function description(): string
    {
        return $this->description;
    }
}
