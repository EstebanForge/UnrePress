<?php

declare(strict_types=1);

namespace UnrePress\Tests\Unit\Container;

use UnrePress\Container\ServiceContainer;
use UnrePress\Tests\Helpers\WordPressTestHelper;

/**
 * ServiceContainer Unit Tests.
 *
 * Tests for dependency injection container to manage service lifecycle
 * and dependencies between components.
 */
class ServiceContainerTest extends WordPressTestHelper
{
    private ServiceContainer $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Define required constants
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }
        if (!defined('WP_CONTENT_DIR')) {
            define('WP_CONTENT_DIR', '/tmp/wordpress/wp-content');
        }

        $this->container = new ServiceContainer();
    }

    public function test_can_register_simple_service(): void
    {
        $this->container->register('test.service', function () {
            return new \stdClass();
        });

        $result = $this->container->has('test.service');

        $this->assertTrue($result);
    }

    public function test_can_retrieve_registered_service(): void
    {
        $this->container->register('test.service', function () {
            $obj = new \stdClass();
            $obj->value = 'test';

            return $obj;
        });

        $result = $this->container->get('test.service');

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertEquals('test', $result->value);
    }

    public function test_returns_same_instance_for_singleton(): void
    {
        $counter = 0;
        $this->container->register('test.singleton', function () use (&$counter) {
            $counter++;
            $obj = new \stdClass();
            $obj->count = $counter;

            return $obj;
        }, true);

        $instance1 = $this->container->get('test.singleton');
        $instance2 = $this->container->get('test.singleton');

        $this->assertSame($instance1, $instance2);
        $this->assertEquals(1, $instance1->count);
        $this->assertEquals(1, $instance2->count);
    }

    public function test_returns_different_instance_for_transient(): void
    {
        $counter = 0;
        $this->container->register('test.transient', function () use (&$counter) {
            $counter++;
            $obj = new \stdClass();
            $obj->count = $counter;

            return $obj;
        }, false);

        $instance1 = $this->container->get('test.transient');
        $instance2 = $this->container->get('test.transient');

        $this->assertNotSame($instance1, $instance2);
        $this->assertEquals(1, $instance1->count);
        $this->assertEquals(2, $instance2->count);
    }

    public function test_throws_exception_for_non_existent_service(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Service not found: non.existent.service');

        $this->container->get('non.existent.service');
    }

    public function test_has_returns_false_for_non_existent_service(): void
    {
        $result = $this->container->has('non.existent.service');

        $this->assertFalse($result);
    }

    public function test_can_resolve_dependencies(): void
    {
        // Register dependency
        $this->container->register('dependency', function () {
            $obj = new \stdClass();
            $obj->name = 'dependency';

            return $obj;
        });

        // Register service that uses dependency
        $this->container->register('service.with.dependency', function ($c) {
            $service = new \stdClass();
            $service->dep = $c->get('dependency');

            return $service;
        });

        $result = $this->container->get('service.with.dependency');

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertInstanceOf(\stdClass::class, $result->dep);
        $this->assertEquals('dependency', $result->dep->name);
    }

    public function test_can_register_multiple_services(): void
    {
        $this->container->register('service1', function () {
            return new \stdClass();
        });
        $this->container->register('service2', function () {
            return new \ArrayObject();
        });
        $this->container->register('service3', function () {
            return new \Exception();
        });

        $this->assertTrue($this->container->has('service1'));
        $this->assertTrue($this->container->has('service2'));
        $this->assertTrue($this->container->has('service3'));
    }

    public function test_singleton_services_are_cached(): void
    {
        $callCount = 0;
        $this->container->register('cached.service', function () use (&$callCount) {
            $callCount++;

            return new \stdClass();
        }, true);

        // Call get multiple times
        $this->container->get('cached.service');
        $this->container->get('cached.service');
        $this->container->get('cached.service');

        // Factory should only be called once
        $this->assertEquals(1, $callCount);
    }

    public function test_transient_services_are_not_cached(): void
    {
        $callCount = 0;
        $this->container->register('uncached.service', function () use (&$callCount) {
            $callCount++;

            return new \stdClass();
        }, false);

        // Call get multiple times
        $this->container->get('uncached.service');
        $this->container->get('uncached.service');
        $this->container->get('uncached.service');

        // Factory should be called each time
        $this->assertEquals(3, $callCount);
    }

    public function test_can_register_instance_directly(): void
    {
        $instance = new \stdClass();
        $instance->value = 'direct';

        $this->container->instance('direct.instance', $instance);

        $result = $this->container->get('direct.instance');

        $this->assertSame($instance, $result);
        $this->assertEquals('direct', $result->value);
    }

    public function test_instance_registration_is_always_singleton(): void
    {
        $instance = new \stdClass();
        $this->container->instance('instance.singleton', $instance);

        $result1 = $this->container->get('instance.singleton');
        $result2 = $this->container->get('instance.singleton');

        $this->assertSame($instance, $result1);
        $this->assertSame($result1, $result2);
    }

    public function test_can_register_factory_with_parameters(): void
    {
        $this->container->register('factory.with.params', function ($c, $param1, $param2) {
            $obj = new \stdClass();
            $obj->param1 = $param1;
            $obj->param2 = $param2;

            return $obj;
        });

        $result = $this->container->make('factory.with.params', ['value1', 'value2']);

        $this->assertEquals('value1', $result->param1);
        $this->assertEquals('value2', $result->param2);
    }

    public function test_make_creates_new_instance_even_for_singleton(): void
    {
        $counter = 0;
        $this->container->register('make.test', function () use (&$counter) {
            $counter++;

            return $counter;
        }, true);

        $result1 = $this->container->make('make.test');
        $result2 = $this->container->make('make.test');

        $this->assertNotSame($result1, $result2);
        $this->assertEquals(1, $result1);
        $this->assertEquals(2, $result2);
    }

    public function test_can_check_if_service_registered(): void
    {
        $this->container->register('registered.service', function () {
            return new \stdClass();
        });

        $this->assertTrue($this->container->has('registered.service'));
        $this->assertFalse($this->container->has('unregistered.service'));
    }

    public function test_can_remove_registered_service(): void
    {
        $this->container->register('removable.service', function () {
            return new \stdClass();
        });

        $this->assertTrue($this->container->has('removable.service'));

        $this->container->remove('removable.service');

        $this->assertFalse($this->container->has('removable.service'));
    }

    public function test_remove_clears_singleton_cache(): void
    {
        $counter = 0;
        $this->container->register('cached.service', function () use (&$counter) {
            $counter++;

            return $counter;
        }, true);

        $this->container->get('cached.service');
        $this->assertEquals(1, $counter);

        $this->container->remove('cached.service');

        // Re-register and get again
        $this->container->register('cached.service', function () use (&$counter) {
            $counter++;

            return $counter;
        }, true);
        $this->container->get('cached.service');

        $this->assertEquals(2, $counter);
    }

    public function test_register_throws_exception_for_invalid_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->container->register('', function () {
            return new \stdClass();
        });
    }

    public function test_register_throws_exception_for_non_callable_factory(): void
    {
        $this->expectException(\TypeError::class);

        $this->container->register('invalid.factory', 'not-a-callable');
    }

    public function test_supports_array_access_interface(): void
    {
        $this->container['array.access'] = function () {
            return 'array access test';
        };

        $this->assertTrue(isset($this->container['array.access']));
        $this->assertEquals('array access test', $this->container['array.access']);
    }

    public function test_can_unset_via_array_access(): void
    {
        $this->container['unset.test'] = function () {
            return new \stdClass();
        };

        $this->assertTrue(isset($this->container['unset.test']));

        unset($this->container['unset.test']);

        $this->assertFalse(isset($this->container['unset.test']));
    }

    public function test_dependency_chain_is_resolved_correctly(): void
    {
        // Register chain: A -> B -> C
        $this->container->register('service.c', function () {
            return 'C';
        });

        $this->container->register('service.b', function ($c) {
            return 'B-' . $c->get('service.c');
        });

        $this->container->register('service.a', function ($c) {
            return 'A-' . $c->get('service.b');
        });

        $result = $this->container->get('service.a');

        $this->assertEquals('A-B-C', $result);
    }

    public function test_can_detect_too_deep_dependency_chain(): void
    {
        $maxDepth = 10;
        $depth = 0;

        // Register service that keeps calling itself
        $this->container->register('deep.chain', function ($c) use (&$depth, $maxDepth) {
            $depth++;
            if ($depth > $maxDepth) {
                throw new \Exception('Max depth reached');
            }

            return $c->get('deep.chain');
        });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Max depth reached');

        $this->container->get('deep.chain');
    }
}
