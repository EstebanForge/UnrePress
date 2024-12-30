<?php

declare(strict_types=1);

// Check if running through Composer
if (!getenv('COMPOSER_BINARY')) {
    die("This script can only be run through Composer. Use: composer run-script version-bump\n");
}

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line' . PHP_EOL);
}

$pluginFile = __DIR__ . '/../unrepress.php';
$composerFile = __DIR__ . '/../composer.json';

// Get current version from composer.json
$composerContent = file_get_contents($composerFile);
if ($composerContent === false) {
    die("Error: Could not read composer.json\n");
}

$composerJson = json_decode($composerContent, true);
if ($composerJson === null) {
    die("Error: Invalid composer.json format\n");
}

$currentVersion = $composerJson['version'];
echo "Current version: {$currentVersion}\n";

// Get new version from command line argument or prompt
if (isset($argv[1])) {
    $newVersion = $argv[1];
} else {
    echo "Enter new version: ";
    $newVersion = trim(fgets(STDIN));
}

// Validate version format (semantic versioning)
if (!preg_match('/^\d+\.\d+\.\d+(?:-[\da-z-]+(?:\.[\da-z-]+)*)?(?:\+[\da-z-]+(?:\.[\da-z-]+)*)?$/i', $newVersion)) {
    die("Error: Version must follow semantic versioning format (e.g., 1.0.0)\n");
}

// Update plugin file
$pluginContent = file_get_contents($pluginFile);
if ($pluginContent === false) {
    die("Error: Could not read plugin file\n");
}

$pluginContent = preg_replace(
    '/\* Version:.*\n/',
    '* Version: ' . $newVersion . "\n",
    $pluginContent
);

if ($pluginContent === null) {
    die("Error: Version replacement failed\n");
}

if (file_put_contents($pluginFile, $pluginContent) === false) {
    die("Error: Could not update plugin file\n");
}

// Update composer.json
$composerJson['version'] = $newVersion;
$newComposerContent = json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if (file_put_contents($composerFile, $newComposerContent . "\n") === false) {
    die("Error: Could not update composer.json\n");
}

echo "Successfully updated version from {$currentVersion} to {$newVersion} in both files.\n";
