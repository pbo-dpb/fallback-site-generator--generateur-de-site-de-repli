<?php

use Illuminate\Support\Collection;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class GenerateIrs  extends OpboAbstractGenerator
{

    /**
     * This will generate a truly massive page with all IRs ever published as a single table.
     * AWS SDK limits to 1000
     */
    protected function generateIrsList()
    {
        $staticGenerator = $this;

        return collect($this->s3Client->listObjectsV2([
            'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
            'Prefix' => 'InformationRequests/',
        ])['Contents'])->map(function ($storageObject) use ($staticGenerator) {
            $payload = $staticGenerator->s3Client->getObject([
                'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
                'Key' => $storageObject['Key']
            ]);
            return json_decode((string)$payload['Body']);
        })->sortByDesc("internal_id")->pipe(function ($irs) use ($staticGenerator) {
            collect(['en', 'fr'])->each(function ($language) use ($irs, $staticGenerator) {

                $strings = $staticGenerator->translator->getTranslations($language);

                $title = $strings['irs'];
                $breadcrumbs = [
                    $title => "/" . $language . "/irs/",
                ];

                $informationRequests = collect($irs)->map(function ($ir) use ($strings, $language) {
                    $reqStatus = data_get($strings, "irs_request_status_" . $ir->request_status, "");
                    $dispStatus = data_get($strings, "irs_disposition_status_" . $ir->disposition_status, "");
                    $summary = data_get($ir, 'summary_' . $language, "");
                    $department =  data_get($ir, 'department.name_' . $language, "");

                    return [
                        'internal_id' => $ir->internal_id,
                        'request_date' => $ir->request_date,
                        'request_status' => $reqStatus,
                        'disposition_status' => $dispStatus,
                        'summary' => $summary,
                        "department" => $department,
                        "files" => collect($ir->files)->map(function ($file) {
                            return [
                                "url" => data_get($file, "urls.en.public", data_get($file, "urls.public")),
                                "extension" => $file->extension
                            ];
                        })->toArray()
                    ];
                })->toArray();

                $payload = $this->twig->render('irs.twig', compact('title', 'language', 'strings', 'breadcrumbs', 'informationRequests'));
                $staticGenerator->saveStaticHtmlFile($language . '/information-requests--demandes-information/' . "index.html", $payload);
            });
        });
    }

    public function run()
    {

        parent::run();
        $this->generateIrsList();
    }
}
