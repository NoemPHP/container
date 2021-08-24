<?php

declare(strict_types=1);

namespace Noem\Container\Tests;

use Noem\Container\Attribute\Description;
use Noem\Container\Attribute\Tag;
use Noem\Container\Container;
use Noem\Container\ServiceProvider;
use PHPUnit\Framework\TestCase;

class ContainerReportTest extends TestCase
{

    public function testSimpleReport()
    {
        $services = [
            FooImpl::class =>
                #[Tag('foo')]
                #[Tag('bar')]
                #[Description('Lorem ipsum')]
                fn() => new FooImpl(),
            BarImpl::class => fn() => new BarImpl(),
            NeedsInterface::class =>
                fn(BarImpl $foo) => new NeedsInterface($foo),
        ];

        $sut = new Container(new ServiceProvider($services));
        $result = $sut->report();
        //print_r($result);
        // 4 larger than the actual no. of definitions since there is a header row, and the auto-appended container/invoker entries
        $this->assertCount(6, $result);
    }
}
