<?php

declare(strict_types=1);

namespace Noem\Container\Attribute;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class Description
{

    public function __construct(public string $text)
    {
    }
}
