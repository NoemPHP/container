# Noem Container

A modern auto-wiring service container that leverages PHP8 Attributes to tag and interlink services with their
dependencies

## Installation

Install this package via composer:

`composer require noem/container`

## Usage

The container works by assimilating service factory functions from one or more Service Providers. A `Provider` looks
like this:

[embed]:# "path: ../src/Provider.php, match: 'interface.*?}'"

```php
interface Provider
{

    /**
     * Returns a list of all container entries registered by this service provider.
     *
     * - the key is the entry name
     * - the value is a callable that will return the entry, aka the **factory**
     *
     * Factories have the following signature:
     *        callable( mixed... ):mixed
     * Factories can declare any number & type of parameters and can expect them to be resolved by the Container
     *
     * @psalm-return array<string,callable(mixed...):mixed>
     * @return callable[]
     */
    public function getFactories(): array;

    /**
     * Returns a list of all container entries extended by this service provider.
     *
     * - the key is the entry name
     * - the value is a callable that will return the modified entry
     *
     * Callables have the following signature:
     *        function( mixed $previous, mixed ...$params )
     *
     * About factories parameters:
     *
     * - the container (instance of `Psr\Container\ContainerInterface`)
     * - the entry to be extended. If the entry to be extended does not exist and the parameter is nullable, `null`
     * will be passed.
     *
     * @psalm-return array<string,callable(mixed, mixed...):mixed>
     * @return callable[]
     */
    public function getExtensions(): array;
}
```

## Attributes

During service compilation, the container will parse all function attributes and make them available for manual and
automatic resolving of dependencies by implementing `AttributeAwareContainer`:

[embed]:# "path: ../src/AttributeAwareContainer.php, match: 'interface.*?}'"

```php
interface AttributeAwareContainer extends ContainerInterface
{
    public function getIdsWithAttribute(string $attribute, ?callable $matching = null): array;

    public function getAttributesOfId(string $id, ?string $attributeFQCN = null): array;
}
```

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
