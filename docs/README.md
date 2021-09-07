# Noem Container

A modern auto-wiring service container that leverages PHP8 Attributes to tag and interlink services with their
dependencies.

It features:

* Aggregating services from multiple modules using a service provider pattern
* Comprehensive support for auto-wiring strategies leveraging [php-di/invoker](https://github.com/PHP-DI/Invoker)
* Resolution of circular dependencies by automatically injecting lightweight proxy objects

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
     * The $previous parameter MUST be the first one. Additional parameters will be resolved by the Container.
     
     * @psalm-return array<string,callable(mixed, mixed...):mixed>
     * @return callable[]
     */
    public function getExtensions(): array;
}
```

## Attributes

PHP 8 introduced [a new feature called Attributes](https://stitcher.io/blog/attributes-in-php-8) that allows adding arbitrary metadata to classes, functions, methods and parameters. This provides us with a lot of flexibility when writing service definitions.

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

> Example: `#[Alias( 'my-other-service-id' )]`

Use this to advertise your service under a number of IDs without repeating the definition. One use-case is to encourage
interface segregation in consumers while supplying them with a class that implements multiple interfaces:

```php

class MyContainer implements ContainerInterface, WritableContainerInterface, FlushableContainerinterface {
   // ...
}

```

The service can be defined as follows:

```php

MyContainer::class => 
    #[Alias(ContainerInterface::class)]
    #[Alias(WritableContainerInterface::class)]
    #[Alias(FlushableContainerinterface::class)]
    fn() => new MyContainer()
```

The container is now able to resolve any of the interface FQCNs to the instance of `MyContainer`:

```php
// These all return the same instance:
$container->get(MyContainer::class);
$container->get(ContainerInterface::class);
$container->get(WritableContainerInterface::class);
$container->get(FlushableContainerinterface::class);
```

#### Tag

> Example: `Tag( 'event-listener' )`

You can use this attribute as a low-coupling way to implement extensible "lists of things". A natural application for
this would be to wire up event subscribers to a PSR-14 ListenerProvider

```php
$services = [
    'listener.all' =>
        #[Tag('event-listener')]
        fn( LoggerInterface $logger ) => function( object $event ) use ($logger){
            $logger->info( 'Event triggered: ' . print_r( $event, true ) );
        },
];
```

The Tag attribute also supports specifying a priority which is used to sort services before they are passed to
consumers. The default priority is `50`

> Example: `Tag( 'event-listener', 10 )`

### Parameter-level Attributes

#### Id

> Example: `#[Id( 'service-id' )]`

Can be used on parameters of factories/extension functions. It instructs the Container to resolve the parameter by
fetching the specified entry. Takes precedence over other means of parameter resolution

```php
$services = [
    'my-string' => fn() => 'hello',
    'greeting' =>
        fn(#[Id('my-string')] string $string) => "{$string} world",
];

$container = new Container(new ServiceProvider($services));
$greeting = $container->get('greeting'); // 'hello world'
```

#### WithAttr

> Example: `#[WithAttr( MyCustomAttr::class )]`

Resolves to all services that have been annotated with the specified Attribute.

> Example: `#[WithAttr( MyCustomAttr::class, [ 'name' => 'foo' ] )]`

#### TaggedWith

> Example: `#[TaggedWith( 'foo' )]`

This is is a shorthand for `WithAttr( Tag::class, [ 'name' => $MY_TAG ] )` to fetch all services with the specified tag.

```php
$services = [
    ListenerProviderInterface:: =>
        fn(#[TaggedWith('event-listener')] callable ...$listeners) => new ListenerProvider(...$listeners),
];

```

