<?php

declare(strict_types=1);

namespace Noem\Container\Tests;

use Noem\Container\Attribute\Tag;
use Noem\Container\Container;
use Noem\Container\ServiceProvider;
use PHPUnit\Framework\TestCase;

class ContainerTagTest extends TestCase
{

    public function testSimpleTags()
    {
        $sut = new Container(
            new ServiceProvider([

                                    Foo::class =>
                                        #[Tag('tagged')]
                                        #[Tag('also_tagged')]
                                        fn() => new Foo(),
                                    Bar::class =>
                                        #[Tag('tagged')]
                                        fn(Foo $foo) => new Bar(),
                                    Baz::class => function (Bar $bar, Container $c) {
                                    },
                                ])
        );
        $tagged = $sut->getIdsWithTag('tagged');
        $alsoTagged = $sut->getIdsWithTag('also_tagged');
        $this->assertEqualsCanonicalizing($tagged, [Foo::class, Bar::class]);
        $this->assertEqualsCanonicalizing($alsoTagged, [Foo::class]);
    }
}
