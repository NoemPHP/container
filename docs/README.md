# Noem Container

## Installation

Install this package via composer:

`composer require noem/container`

## Usage

## Attributes

### Service-level Attributes

#### Alias

Example: `#[Alias( 'my-other-service-id' )]`

Use this to advertise your service under a number of IDs without repeating the definition. One use-case is to enforce
interface segregation in consumers while supplying them with a class that implements multiple interfaces:

```php

class MyContainer implements ContainerInterface, WritableContainerInterface, FlushableContainerinterface {
   // ...
}

```

The service can be defined as follows:

```php
#[Alias(ContainerInterface::class)]
#[Alias(WritableContainerInterface::class)]
#[Alias(FlushableContainerinterface::class)]
MyContainer::class => fn() => new MyContainer()
```

The container is now able to resolve any of the interface FQCNs to the instance of `MyContainer`:

```php
// These all return the same instance:
$container->get(MyContainer::class);
$container->get(ContainerInterface::class);
$container->get(WritableContainerInterface::class);
$container->get(FlushableContainerinterface::class);
```

### Parameter-level Attributes

#### Id

Example: `#[Id( 'service-id' )]`

Can be used on parameters of factories/extension functions. It instructs the Container to resolve the parameter by
fetching the specified entry. Takes precedence over other means of parameter resolution

[embed]:# "path: ../tests/Integration/ContainerAutoWiringTest.php, match: 'public function testCanProcessIdAttribute.*?}'"
```php
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
```

#### WithAttr

Example: `#[WithAttr( MyCustomAttr::class )]`

Resolves to all services that have been annotated with the specified Attribute.

Example: `#[WithAttr( MyCustomAttr::class, [ 'name' => 'foo' ] )]`

#### TaggedWith

Example: `#[TaggedWith( 'foo' )]`

This is is a shorthand for `WithAttr( Tag::class, [ 'name' => $MY_TAG ] )` to fetch all services with the specified tag.
