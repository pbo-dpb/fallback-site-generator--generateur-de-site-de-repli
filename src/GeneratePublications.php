<?php


class GeneratePublications  extends OpboAbstractGenerator
{


    protected function generatePublicationPages(string $type)
    {
        $staticGenerator = $this;

        collect($this->s3Client->listObjectsV2([
            'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
            'Prefix' => 'Publications/' . $type . "-",
        ])['Contents'])->map(function ($storageObject) use ($staticGenerator) {
            $payload = $staticGenerator->s3Client->getObject([
                'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
                'Key' => $storageObject['Key']
            ]);
            return json_decode((string)$payload['Body']);
        })->whereNotNull('slug')->each(function ($publication) use ($staticGenerator) {
            collect(['en', 'fr'])->each(function ($language) use ($publication, $staticGenerator) {
                $payload = $this->twig->render('publication.twig', [
                    'title' => data_get($publication, $language === 'fr' ? 'title_fr' : 'title_en', ''),
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

        parent::run();

        $staticGenerator = $this;
        collect(["RP", "LEG", "ADM", "LIBARC"])->each(function ($type) use ($staticGenerator) {
            $this->generatePublicationPages($type);
        });
    }
}
