<?php
$loader = require __DIR__ . '/vendor/autoload.php';
function loadSrc($class)
{
    include 'src/' . $class . '.php';
}

spl_autoload_register('loadSrc');
$sgen = new OpboStaticGenerator;
$sgen->run();
