#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Test runner that checks for test dependencies and installs them if missing.
 *
 * This script ensures test dependencies are available before running tests,
 * automatically installing them via composer update if needed.
 */

echo "🔍 Checking test dependencies...\n";

$vendorDir = __DIR__ . '/../vendor';
$pestBinary = $vendorDir . '/bin/pest';
$dependenciesInstalled = file_exists($pestBinary);

if (!$dependenciesInstalled) {
    echo "📦 Test dependencies not found. Installing...\n";
    echo "Running: composer update\n";

    $composerUpdate = exec('composer update --no-interaction 2>&1', $output, $returnCode);

    if ($returnCode !== 0) {
        echo "❌ Failed to install test dependencies:\n";
        echo implode("\n", $output) . "\n";
        exit(1);
    }

    echo "✅ Test dependencies installed successfully.\n";
} else {
    echo "✅ Test dependencies found.\n";
}

echo "🧪 Running tests...\n";

// Run the actual tests
$testCommand = escapeshellcmd($vendorDir . '/bin/pest');
$testArgs = array_slice($_SERVER['argv'], 1);
$testArgsString = implode(' ', array_map('escapeshellarg', $testArgs));

$fullCommand = $testCommand . ' ' . $testArgsString;

passthru($fullCommand, $returnCode);

exit($returnCode);
