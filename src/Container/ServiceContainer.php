<?php

declare(strict_types=1);

namespace UnrePress\Container;

use ArrayAccess;
use InvalidArgumentException;

/**
 * Service Container
 *
 * Simple dependency injection container for managing service lifecycle
 * and dependencies. Supports singleton and transient services.
 */
class ServiceContainer implements ArrayAccess
{
    /**
     * Registered service factories.
     *
     * @var array<string, callable>
     */
    private array $factories = [];

    /**
     * Cached singleton instances.
     *
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Service singleton flags.
     *
     * @var array<string, bool>
     */
    private array $singletons = [];

    /**
     * Register a service with the container.
     *
     * @param string   $id      Service identifier.
     * @param callable $factory Factory function to create the service.
     * @param bool     $singleton Whether service should be singleton.
     *
     * @return void
     *
     * @throws InvalidArgumentException If ID is empty or factory is not callable.
     */
    public function register(string $id, callable $factory, bool $singleton = true): void
    {
        if (empty($id)) {
            throw new InvalidArgumentException('Service ID cannot be empty');
        }

        $this->factories[$id] = $factory;
        $this->singletons[$id] = $singleton;
    }

    /**
     * Register an existing instance as a singleton service.
     *
     * @param string $id       Service identifier.
     * @param mixed  $instance Instance to register.
     *
     * @return void
     */
    public function instance(string $id, $instance): void
    {
        $this->factories[$id] = function() use ($instance) {
            return $instance;
        };
        $this->instances[$id] = $instance;
        $this->singletons[$id] = true;
    }

    /**
     * Retrieve a service from the container.
     *
     * @param string $id Service identifier.
     *
     * @return mixed Service instance.
     *
     * @throws InvalidArgumentException If service not found.
     */
    public function get(string $id)
    {
        if (!$this->has($id)) {
            throw new InvalidArgumentException(sprintf('Service not found: %s', $id));
        }

        // Return cached singleton if available
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Create the service
        $service = $this->factories[$id]($this);

        // Cache if singleton
        if ($this->singletons[$id]) {
            $this->instances[$id] = $service;
        }

        return $service;
    }

    /**
     * Create a new service instance bypassing singleton cache.
     *
     * @param string $id Service identifier.
     * @param array  $parameters Parameters to pass to factory.
     *
     * @return mixed New service instance.
     *
     * @throws InvalidArgumentException If service not found.
     */
    public function make(string $id, array $parameters = [])
    {
        if (!$this->has($id)) {
            throw new InvalidArgumentException(sprintf('Service not found: %s', $id));
        }

        return $this->factories[$id]($this, ...$parameters);
    }

    /**
     * Check if a service is registered.
     *
     * @param string $id Service identifier.
     *
     * @return bool True if service exists, false otherwise.
     */
    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }

    /**
     * Remove a service from the container.
     *
     * @param string $id Service identifier.
     *
     * @return void
     */
    public function remove(string $id): void
    {
        unset($this->factories[$id], $this->instances[$id], $this->singletons[$id]);
    }

    /**
     * ArrayAccess: Check if service exists.
     *
     * @param mixed $offset Service identifier.
     *
     * @return bool True if service exists, false otherwise.
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * ArrayAccess: Get service.
     *
     * @param mixed $offset Service identifier.
     *
     * @return mixed Service instance.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * ArrayAccess: Register service.
     *
     * @param mixed $offset Service identifier.
     * @param mixed $value  Factory function or instance.
     *
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_callable($value)) {
            $this->register($offset, $value);
        } else {
            $this->instance($offset, $value);
        }
    }

    /**
     * ArrayAccess: Remove service.
     *
     * @param mixed $offset Service identifier.
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }
}
