<?php

namespace Noem\Container;

use Psr\Container\ContainerInterface;

interface FactoryProvidingContainer extends ContainerInterface
{
    public function getFactory(string $id): callable;
}
