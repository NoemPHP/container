<?php

namespace Noem\Container;

use Psr\Container\ContainerInterface;

interface TaggableContainer extends ContainerInterface
{

    public function getIdsWithTag(string $tag): array;
}
