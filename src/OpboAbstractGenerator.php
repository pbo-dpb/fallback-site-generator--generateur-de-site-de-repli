<?php

class OpboAbstractGenerator
{
    protected $twig, $strings;

    function __construct(public \Aws\S3\S3Client $s3Client)
    {
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../twig');
        $this->twig = new \Twig\Environment($loader);
        $this->translator = new OpboTranslator();
    }

    protected function saveFile(string $path, string $payload, string $contentType)
    {
        $this->s3Client->putObject([
            'Bucket' => $_ENV['OUTPUT_S3_BUCKET'],
            'Key' => $path,
            'Body' => $payload,
            'ContentType' => $contentType
        ]);
    }

    protected function saveStaticHtmlFile(string $path, string $payload)
    {
        $this->saveFile($path, $payload, "text/html");
    }


    public function run()
    {
    }
}
