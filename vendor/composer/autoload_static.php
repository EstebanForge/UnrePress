<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitad1a62003a49bd753605ec3636ac5d78
{
    public static $prefixLengthsPsr4 = array (
        'U' => 
        array (
            'UnrePress\\' => 10,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'UnrePress\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'UnrePress\\Admin\\Hider' => __DIR__ . '/../..' . '/src/Admin/Hider.php',
        'UnrePress\\Admin\\UpdaterPages' => __DIR__ . '/../..' . '/src/Admin/UpdaterPages.php',
        'UnrePress\\Debugger' => __DIR__ . '/../..' . '/src/Debugger.php',
        'UnrePress\\EgoBlocker' => __DIR__ . '/../..' . '/src/EgoBlocker.php',
        'UnrePress\\Helpers' => __DIR__ . '/../..' . '/src/Helpers.php',
        'UnrePress\\Index\\Index' => __DIR__ . '/../..' . '/src/Index/Index.php',
        'UnrePress\\Index\\PluginsIndex' => __DIR__ . '/../..' . '/src/Index/PluginsIndex.php',
        'UnrePress\\Index\\ThemesIndex' => __DIR__ . '/../..' . '/src/Index/ThemesIndex.php',
        'UnrePress\\UnrePress' => __DIR__ . '/../..' . '/src/UnrePress.php',
        'UnrePress\\UpdaterProvider\\GitHub' => __DIR__ . '/../..' . '/src/UpdaterProvider/GitHub.php',
        'UnrePress\\UpdaterProvider\\ProviderInterface' => __DIR__ . '/../..' . '/src/UpdaterProvider/ProviderInterface.php',
        'UnrePress\\Updater\\UpdateCore' => __DIR__ . '/../..' . '/src/Updater/UpdateCore.php',
        'UnrePress\\Updater\\UpdateLock' => __DIR__ . '/../..' . '/src/Updater/UpdateLock.php',
        'UnrePress\\Updater\\UpdatePlugins' => __DIR__ . '/../..' . '/src/Updater/UpdatePlugins.php',
        'UnrePress\\Updater\\UpdateThemes' => __DIR__ . '/../..' . '/src/Updater/UpdateThemes.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitad1a62003a49bd753605ec3636ac5d78::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitad1a62003a49bd753605ec3636ac5d78::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitad1a62003a49bd753605ec3636ac5d78::$classMap;

        }, null, ClassLoader::class);
    }
}
