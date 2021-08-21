# Noem Container

## Installation

## Usage

## Attributes

### `#[Id( 'service-id' )]`

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
