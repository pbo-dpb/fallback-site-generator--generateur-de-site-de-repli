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

    protected function saveStaticHtmlFile(string $path, string $payload)
    {
        $this->s3Client->putObject([
            'Bucket' => $_ENV['OUTPUT_S3_BUCKET'],
            'Key' => $path,
            'Body' => $payload,
            'ContentType' => "text/html"
        ]);
    }


    public function run()
    {
    }
}
