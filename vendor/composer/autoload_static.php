<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInita53afc6b3cd4c92217e9468c9af1b3e0
{
    public static $classMap = array (
        'Premia\\Github_API' => __DIR__ . '/../..' . '/classes/class-github-api.php',
        'Premia\\REST_Endpoints' => __DIR__ . '/../..' . '/classes/class-rest-endpoints.php',
        'Premia\\Woocommerce' => __DIR__ . '/../..' . '/classes/class-woocommerce.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->classMap = ComposerStaticInita53afc6b3cd4c92217e9468c9af1b3e0::$classMap;

        }, null, ClassLoader::class);
    }
}
