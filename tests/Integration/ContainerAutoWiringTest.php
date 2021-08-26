<?php

declare(strict_types=1);

namespace Noem\Container\Tests;

use Noem\Container\Attribute\Id;
use Noem\Container\Attribute\Tag;
use Noem\Container\Attribute\Tagged;
use Noem\Container\Container;
use Noem\Container\ServiceProvider;
use PHPUnit\Framework\TestCase;

class ContainerAutoWiringTest extends TestCase
{
    public function testCanCreateUndefinedServiceWithoutConstructor()
    {
        $sut = new Container(
            new ServiceProvider([])
        );
        $result = $sut->get(Bar::class);
        $this->assertInstanceOf(Bar::class, $result);
    }

    public function testCanCreateUndefinedServiceWithConstructor()
    {
        $sut = new Container(
            new ServiceProvider([])
        );
        $result = $sut->get(Foo::class);
        $this->assertInstanceOf(Foo::class, $result);
    }

    public function testCanAutowireMissingDefinitionWithoutParams()
    {
        $sut = new Container(
            new ServiceProvider([
                                    Bar::class =>
                                        fn(Foo $foo) => new Bar(),
                                ])
        );
        $result = $sut->get(Bar::class);
        $this->assertInstanceOf(Bar::class, $result);
    }

    public function testCanAutowireExtensions()
    {
        $factories = [
            Bar::class => fn() => new Bar(),
        ];
        $extensions = [
            Bar::class => function (Bar $bar, Foo $foo) {
                $bar->value = get_class($foo);

                return $bar;
            },
        ];
        $sut = new Container(
            new ServiceProvider($factories, $extensions)
        );
        $result = $sut->get(Bar::class);
        $this->assertInstanceOf(Bar::class, $result);
        $this->assertSame($result->value, Foo::class);
    }

    public function testCanAutowireMultipleDependencies()
    {
        $sut = new Container(
            new ServiceProvider([

                                    Bar::class =>
                                        fn(Foo $foo) => new Bar(),
                                    Baz::class => function (Bar $bar, Container $c) {
                                        return new Baz();
                                    },
                                ])
        );
        $result = $sut->get(Baz::class);
        $this->assertInstanceOf(Baz::class, $result);
    }

    public function testCanAutowireInterface()
    {
        $services = [
            FooImpl::class => fn() => new FooImpl(),
            BarImpl::class => fn() => new BarImpl(),
            NeedsInterface::class =>
                fn(BarImpl $foo) => new NeedsInterface($foo),
        ];

        $sut = new Container(new ServiceProvider($services));
        $result = $sut->get(NeedsInterface::class);

        $this->assertInstanceOf(NeedsInterface::class, $result);
        $this->assertInstanceOf(BarImpl::class, $result->impl);
    }

    public function testCanProcessIdAttribute()
    {
        $services = [
            'my-string' => fn() => 'hello-world',
            NeedsString::class =>
                fn(#[Id('my-string')] string $string) => new NeedsString($string),
        ];

        $sut = new Container(new ServiceProvider($services));
        $result = $sut->get(NeedsString::class);

        $this->assertInstanceOf(NeedsString::class, $result);
        $this->assertSame('hello-world', $result->value);
    }

    public function testCanProcessIdAttributeWithVariadicParam()
    {
        $strings = [
            'hello',
            'world',
        ];
        $services = [
            'my-strings' => fn() => $strings,
            NeedsManyStrings::class =>
                fn(#[Id('my-strings')] string ...$manyStrings) => new NeedsManyStrings(...$manyStrings),
        ];

        $sut = new Container(new ServiceProvider($services));
        $result = $sut->get(NeedsManyStrings::class);

        $this->assertInstanceOf(NeedsManyStrings::class, $result);
        $this->assertEquals($strings, $result->strings);
    }

    public function testCanAutowireTaggedServices()
    {
        $expected = [
            'foo',
            'bar',
        ];
        $services = [

            'string-1' => #[Tag('tagged')] fn() => 'foo',
            'string-2' => #[Tag('tagged')] fn() => 'bar',
            'string-3' => fn() => 'baz',
            NeedsManyStrings::class =>
                fn(#[Tagged('tagged')] string ...$foo) => new NeedsManyStrings(...$foo),
        ];

        $sut = new Container(new ServiceProvider($services));
        $result = $sut->get(NeedsManyStrings::class);

        $this->assertInstanceOf(NeedsManyStrings::class, $result);
        $this->assertEquals($expected, $result->strings);
    }
}
