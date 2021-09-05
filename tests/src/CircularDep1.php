<?php

namespace Noem\Container\Tests;

class CircularDep1
{
    public function __construct(public CircularDep2 $dep2)
    {

    }
}
