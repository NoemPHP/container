<?php

declare(strict_types=1);

namespace Noem\Container\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Tagged
{

    public function __construct(public string $name)
    {
    }
}
