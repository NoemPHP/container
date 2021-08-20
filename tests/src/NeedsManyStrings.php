<?php

declare(strict_types=1);

namespace Noem\Container\Tests;

class NeedsManyStrings
{

    public array $strings;

    public function __construct(string ...$strings)
    {
        $this->strings = $strings;
    }
}
