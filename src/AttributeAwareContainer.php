<?php

namespace Noem\Container;

use Psr\Container\ContainerInterface;

interface AttributeAwareContainer extends ContainerInterface
{
    public function getIdsWithAttribute(string $attribute, ?callable $matching = null): array;

    public function getAttributesOfId(string $id, ?string $attributeFQCN = null): array;
}
