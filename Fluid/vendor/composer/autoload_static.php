<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInita437f327388059507a32066df0af48e9
{
    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'TYPO3Fluid\\Fluid\\' => 17,
        ),
        'H' => 
        array (
            'Htlw3r\\Fluidnew\\' => 16,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'TYPO3Fluid\\Fluid\\' => 
        array (
            0 => __DIR__ . '/..' . '/typo3fluid/fluid/src',
        ),
        'Htlw3r\\Fluidnew\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInita437f327388059507a32066df0af48e9::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInita437f327388059507a32066df0af48e9::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInita437f327388059507a32066df0af48e9::$classMap;

        }, null, ClassLoader::class);
    }
}
