<?php
$loader = require __DIR__ . '/vendor/autoload.php';
function loadSrc($class)
{
    include 'src/' . $class . '.php';
}

spl_autoload_register('loadSrc');

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();



$sourceClient = new \Aws\S3\S3Client([
    'version' => 'latest',
    'region' => 'ca-central-1'
]);

$sgen = new OpboStaticGenerator($sourceClient);
$sgen->run();
