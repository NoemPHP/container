<?php

namespace Noem\Container\Tests;

use Noem\Container\Container;
use Noem\Container\ServiceProvider;
use Noem\TinyProxy\TinyProxy;
use PHPUnit\Framework\TestCase;

class CircularDependencyTest extends TestCase
{
    public function testDirectDependency()
    {
        $sut = new Container(new ServiceProvider([
            CircularDep2::class => fn(CircularDep1 $dep1) => new CircularDep2($dep1),
            CircularDep1::class => fn(CircularDep2 $dep2) => new CircularDep1($dep2),
        ]));

        $service1=$sut->get(CircularDep1::class);
        $service2=$sut->get(CircularDep2::class);

        $this->assertInstanceOf(CircularDep1::class,$service1);
        $this->assertInstanceOf(CircularDep2::class,$service2);
        $this->assertInstanceOf(TinyProxy::proxyClassName(CircularDep1::class),$service2->dep1);
    }
}
