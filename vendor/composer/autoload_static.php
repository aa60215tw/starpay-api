<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit4161056f35ef90a6b1ae1e631b277c44
{
    public static $prefixLengthsPsr4 = array (
        'i' => 
        array (
            'ipip\\db\\' => 8,
        ),
        'L' => 
        array (
            'Libern\\QRCodeReader\\' => 20,
        ),
        'D' => 
        array (
            'Dotenv\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'ipip\\db\\' => 
        array (
            0 => __DIR__ . '/..' . '/ipip/db/src/ipip/db',
        ),
        'Libern\\QRCodeReader\\' => 
        array (
            0 => __DIR__ . '/..' . '/libern/qr-code-reader/src',
        ),
        'Dotenv\\' => 
        array (
            0 => __DIR__ . '/..' . '/vlucas/phpdotenv/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit4161056f35ef90a6b1ae1e631b277c44::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit4161056f35ef90a6b1ae1e631b277c44::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
