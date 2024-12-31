# UNREPRESS_BLOCK_WPORG

`UNREPRESS_BLOCK_WPORG` allows to block requests to wp.org. It is disabled by default.

## How to use

Add the following line to your wp-config.php file:

```php
define('UNREPRESS_BLOCK_WPORG', true);
```

Doing so will block every request, from WordPress, to the wp.org repository.

## What happens when it's disabled

The plugin will not block any requests to wp.org. But, UnrePress will privilege his own index usage, over the wp.org repository.

So, for example, if there's a new WordPress core update available, UnrePress will try to update it from the index. If that fails (after 15 seconds), UnrePress will let WordPress fall back to his native behavior and use the wp.org repository.

Same for plugins and themes. UnrePress first will try to update them from the index. If that fails, UnrePress will let WordPress fall back to his native behavior and use the wp.org repository.
