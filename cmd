<?php

require 'vendor/autoload.php';
define('BASE_PATH', dirname(__FILE__));

set_error_handler(function($errno, $errstr) {
    throw new ErrorException($errstr, $errno);
});


$app = new \Lightning\Console\Application('App');
$app->run();