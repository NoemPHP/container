<?php

declare(strict_types=1);

namespace Noem\Container\Tests;

class NeedsInterface
{

    public function __construct(public SomeInterface $impl)
    {
    }
}
