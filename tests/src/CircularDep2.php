<?php

namespace Noem\Container\Tests;

class CircularDep2
{
    public function __construct(public CircularDep1 $dep1)
    {

    }
}
