<?php

namespace Noem\Container;

interface AttributeAwareContainer
{
    public function getIdsWithAttribute(string $attribute, ?callable $matching = null): array;
}
