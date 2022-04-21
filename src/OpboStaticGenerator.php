<?php


class OpboStaticGenerator
{
    protected $twig, $strings;

    function __construct(public \Aws\S3\S3Client $sourceClient)
    {
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../twig');
        $this->twig = new \Twig\Environment($loader);
        $this->translator = new OpboTranslator();
    }

    protected function saveStaticHtmlFile(string $path, string $payload)
    {
        dd($payload);
    }

    protected function generatePublicationPages(string $type)
    {
        $staticGenerator = $this;

        collect($this->sourceClient->listObjectsV2([
            'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
            'Prefix' => 'Publications/' . $type . "-",
        ])['Contents'])->map(function ($storageObject) use ($staticGenerator) {
            $payload = $staticGenerator->sourceClient->getObject([
                'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
                'Key' => $storageObject['Key']
            ]);
            return json_decode((string)$payload['Body']);
        })->whereNotNull('slug')->each(function ($publication) use ($staticGenerator) {
            collect(['en', 'fr'])->each(function ($language) use ($publication, $staticGenerator) {
                $payload = $this->twig->render('publication.twig', [
                    'publication' => $publication,
                    'language' => $language,
                    'strings' => $staticGenerator->translator->getTranslations($language)
                ]);
                $staticGenerator->saveStaticHtmlFile($language . '/publications/' . $publication->slug, $payload);
            });
        });
    }

    public function run()
    {


        $staticGenerator = $this;
        collect(["RP", "LEG", "ADM", "LIBARC"])->each(function ($type) use ($staticGenerator) {
            $this->generatePublicationPages($type);
        });
    }
}
