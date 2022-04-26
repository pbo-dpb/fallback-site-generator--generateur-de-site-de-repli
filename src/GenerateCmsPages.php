<?php

use Illuminate\Support\Collection;

class GenerateCmsPages extends OpboAbstractGenerator
{

    public function savePage(CmsPage $page)
    {

        $staticGenerator = $this;
        collect(['en', 'fr'])->each(function ($language) use ($staticGenerator, $page) {

            $strings = $staticGenerator->translator->getTranslations($language);

            $title = data_get($page, 'title_' . $language);

            $breadcrumbs = [];

            if (data_get($page, "cms_section")) {
                $breadcrumbs[data_get($page, "cms_section.title_" . $language)] = null;
            }

            $breadcrumbs[$title] = "/" . $language . "/" . data_get($page, 'slug');

            $blocks = $page->renderBlocks($language);
            $files =  $page->getFiles($language)->toArray();
            $payload = $this->twig->render('cmspage.twig', compact('title', 'language', 'strings', 'breadcrumbs', 'blocks', 'files'));
            $this->saveStaticHtmlFile($language . '/' . data_get($page, 'slug'), $payload);
        });
    }


    public function run(): Collection
    {

        parent::run();

        $staticGenerator = $this;

        return collect($this->s3Client->listObjectsV2([
            'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
            'Prefix' => 'CmsPages/',
        ])['Contents'])->map(function ($storageObject) use ($staticGenerator) {
            $payload = $staticGenerator->s3Client->getObject([
                'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
                'Key' => $storageObject['Key']
            ]);
            return (string)$payload['Body'];
        })->filter()->map(function ($rawCmsPage) {
            return new CmsPage($rawCmsPage);
        })->each(function ($page) use ($staticGenerator) {
            $staticGenerator->savePage($page);
        });
    }
}
