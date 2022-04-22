<?php

use League\CommonMark\GithubFlavoredMarkdownConverter;

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

                $converter = new GithubFlavoredMarkdownConverter([
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]);

                $strings = $staticGenerator->translator->getTranslations($language);
                $type = $strings[data_get($publication, 'type')];
                $title = data_get($publication, $language === 'fr' ? 'title_fr' : 'title_en', '');
                $abstract = $converter->convert(data_get($publication, 'metadata.abstract_' . $language, ''));
                $breadcrumbs = [
                    $strings['publications'] => "/" . $language . "/publications/",
                    $title => "/" . $language . "/publications/" . data_get($publication, 'slug')
                ];
                $payload = $this->twig->render('publication.twig', compact('title', 'abstract', 'publication', 'language', 'strings', 'type', 'breadcrumbs'));
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
