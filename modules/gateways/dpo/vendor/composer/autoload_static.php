<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit443d22a8c5b1651c8598899cde92e3f0
{
    public static $prefixLengthsPsr4 = array (
        'D' => 
        array (
            'Dpo\\Common\\' => 11,
            'DPOGroup\\Services\\' => 18,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Dpo\\Common\\' => 
        array (
            0 => __DIR__ . '/..' . '/dpo/dpo-pay-common/src',
        ),
        'DPOGroup\\Services\\' => 
        array (
            0 => __DIR__ . '/../..' . '/lib/Services',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit443d22a8c5b1651c8598899cde92e3f0::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit443d22a8c5b1651c8598899cde92e3f0::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit443d22a8c5b1651c8598899cde92e3f0::$classMap;

        }, null, ClassLoader::class);
    }
}
