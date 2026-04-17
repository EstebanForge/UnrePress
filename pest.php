<?php

declare(strict_types=1);

use Pest\TestSuite;

$tests = new TestSuite();
$tests
    ->in(__DIR__ . '/tests')
    ->src(__DIR__ . '/src');

// WordPress test environment setup
$tests->beforeEach(function () {
    // WordPress will be loaded in tests/bootstrap.php
});

return $tests;