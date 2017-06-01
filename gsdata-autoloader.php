<?php
/**
 * Created by PhpStorm.
 * User: phpboy
 * Date: 2017/6/1
 * Time: 16:48
 */
$mapping = [
    'GSDATA\SDK'=>__DIR__.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'SDK.php',
    'GSDATA\Signature' => __DIR__ . DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Signature.php',
];
spl_autoload_register(function ($class) use ($mapping) {
    if (isset($mapping[$class])) {
        require $mapping[$class];
    }
}, true);