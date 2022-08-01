<?php

spl_autoload_register(function($className){
    $filename = __DIR__ . "/" . $className . ".php";
    if (is_readable($filename)) {
        require $filename;
    }
});
