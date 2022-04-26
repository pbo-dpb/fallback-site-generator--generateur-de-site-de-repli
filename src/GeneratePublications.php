<?php

use Illuminate\Support\Collection;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class GeneratePublications  extends OpboAbstractGenerator
{

    protected function verboseFiscalYear(string $fiscalYear, string $language): string
    {
        $re = '/^(\d{2})(\d{2})$/m';
        preg_match_all($re, $fiscalYear, $matches, PREG_SET_ORDER, 0);
        return  $language == 'fr' ? ("20" . $matches[0][1] . "-20" . $matches[0][2]) : ("20" . $matches[0][1] . "-" . $matches[0][2]);
    }

    protected function titleForFiscalYearPage(string $type, string $fiscalYear, string $language): string
    {
        $strings = $this->translator->getTranslations($language);
        return $strings[$type . "_plural"] . " - " . $this->verboseFiscalYear($fiscalYear, $language);
    }

    protected function generateIndexPage(Collection $years)
    {
        $staticGenerator = $this;
        collect(["en", "fr"])->each(function ($language) use ($years, $staticGenerator) {
            $strings = $staticGenerator->translator->getTranslations($language);

            $title = $strings['publications'];
            $breadcrumbs = [
                $title => "/" . $language . "/publications/",
            ];

            $types = $years->map(function ($yr, $type) use ($strings, $staticGenerator, $language) {
                return [
                    "title" => $strings[$type . "_plural"],
                    'tp' => $type,
                    "years" => $yr->map(function ($publications, $fiscalYear) use ($staticGenerator, $language) {
                        return [
                            "title" => $staticGenerator->verboseFiscalYear($fiscalYear, $language),
                            "fy" => $fiscalYear
                        ];
                    })
                ];
            });


            $payload = $this->twig->render('pubindex.twig', compact('title', 'language', 'strings', 'breadcrumbs', 'types'));
            $staticGenerator->saveStaticHtmlFile($language . '/publications/index.html', $payload);
        });
    }

    protected function generateIndexPageForFiscalYear(string $type, string $fiscalYear, Collection $publications, ?bool $first = false)
    {
        $staticGenerator = $this;
        collect(["en", "fr"])->each(function ($language) use ($type, $fiscalYear, $publications, $first, $staticGenerator) {
            $strings = $staticGenerator->translator->getTranslations($language);

            $title = $staticGenerator->titleForFiscalYearPage($type, $fiscalYear, $language);
            $breadcrumbs = [
                $strings['publications'] => "/" . $language . "/publications/",
                $title => "/" . $language . "/publications/" . $type . "-" . $fiscalYear . ".html",
            ];
            $payload = $this->twig->render('publications.twig', compact('publications', 'title', 'language', 'strings', 'type', 'breadcrumbs', 'fiscalYear'));
            $staticGenerator->saveStaticHtmlFile($language . '/publications/' . $type . "-" . $fiscalYear . ".html", $payload);
        });
    }

    protected function generatePublicationPages(string $type)
    {
        $staticGenerator = $this;

        return collect($this->s3Client->listObjectsV2([
            'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
            'Prefix' => 'Publications/' . $type . "-",
        ])['Contents'])->map(function ($storageObject) use ($staticGenerator) {
            $payload = $staticGenerator->s3Client->getObject([
                'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
                'Key' => $storageObject['Key']
            ]);
            return new Publication((string)$payload['Body']);
        })->whereNotNull('slug')->each(function ($publication) use ($staticGenerator) {
            collect(['en', 'fr'])->each(function ($language) use ($publication, $staticGenerator) {

                $converter = new GithubFlavoredMarkdownConverter([
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]);

                $strings = $staticGenerator->translator->getTranslations($language);
                $type = $strings[data_get($publication, 'type')];
                $title = data_get($publication, $language === 'fr' ? 'title_fr' : 'title_en', '');
                if ($abs = data_get($publication, 'metadata.abstract_' . $language)) {
                    $abstract = $converter->convert($abs);
                } else {
                    $abstract = null;
                }


                $artifact = data_get($publication, "artifacts.main." . $language . ".public");

                $re = '/^[A-Z]+-(\d{4})-(\d{3})/m';
                preg_match_all($re, $publication->internal_id, $matches, PREG_SET_ORDER, 0);
                $fiscalYear = data_get($matches, "0.1", '9999');
                $fiscalYearTitle = $staticGenerator->titleForFiscalYearPage($publication->type, $fiscalYear, $language);

                $breadcrumbs = [
                    $strings['publications'] => "/" . $language . "/publications/",
                    $fiscalYearTitle => "/" . $language . "/publications/" . data_get($publication, 'type') . "-" . $fiscalYear . ".html",
                    $title => "/" . $language . "/publications/" . data_get($publication, 'slug')
                ];

                $files = $publication->getFiles($language);

                $payload = $this->twig->render('publication.twig', compact('title', 'abstract', 'publication', 'language', 'strings', 'type', 'breadcrumbs', "artifact", "files"));
                $staticGenerator->saveStaticHtmlFile($language . '/publications/' . $publication->slug, $payload);
            });
        })->sortByDesc("release_date")->groupBy(function ($publication) {
            $re = '/^[A-Z]+-(\d{4})-(\d{3})/m';

            preg_match_all($re, $publication->internal_id, $matches, PREG_SET_ORDER, 0);
            return $matches[0][1] ?? null;
        })->reject(function ($values, $key) {
            return !$key || $key == "";
        })->sortByDesc(function ($values, $key) {
            return $key;
        })->pipe(function ($collection) use ($staticGenerator, $type) {
            $collection->each(function ($publications, $fiscalYear) use ($staticGenerator, $collection, $type) {
                $staticGenerator->generateIndexPageForFiscalYear($type, $fiscalYear, $publications, $collection->keys()->first() == $fiscalYear);
            });
            return $collection;
        });
    }

    public function run()
    {

        parent::run();

        $staticGenerator = $this;
        return $publications = collect(["RP", "LEG", "ADM", "LIBARC"])->mapWithKeys(function ($type) use ($staticGenerator) {
            return [$type => $this->generatePublicationPages($type)];
        })->pipe(
            function ($years) use ($staticGenerator) {
                $staticGenerator->generateIndexPage($years);
                return $years;
            }
        );
    }
}
