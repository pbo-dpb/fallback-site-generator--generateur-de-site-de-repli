<?php
$loader = require __DIR__ . '/vendor/autoload.php';
function loadSrc($class)
{
    include 'src/' . $class . '.php';
}

spl_autoload_register('loadSrc');

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();


$client = new \Aws\S3\S3Client([
    'version' => 'latest',
    'region' => 'ca-central-1',
    'credentials' => [
        'key'    => $_ENV['OUTPUT_S3_ID'],
        'secret' => $_ENV['OUTPUT_S3_KEY'],
    ],
]);

collect([
    "CopyStaticAssets",
    "GenerateCmsPages",
    "GeneratePublications",
    "GenerateBlogs",
    'GenerateIrs',
    "GenerateEpcPortal",
    "GenerateResearchTools",
    "GenerateGlue"
])->reduce(function ($carry, $className) use ($client) {
    $carry[$className] = (new $className($client, $carry))->run();
    return $carry;
}, []);
