<?php

namespace Noem\Container;

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
