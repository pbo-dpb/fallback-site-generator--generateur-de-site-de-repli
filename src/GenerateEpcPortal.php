<?php

use \Illuminate\Support\Str;

class GenerateEpcPortal extends OpboAbstractGenerator
{

    public function generateCosting($costing)
    {
        $staticGenerator = $this;

        collect(["en", "fr"])->each(function ($language) use ($staticGenerator, $costing) {
            $strings = $staticGenerator->translator->getTranslations($language);
            $title = data_get($costing, "title_" . $language);

            $occurence = $costing->electionOccurence;

            $breadcrumbs = [
                $strings['epc_estimates'] => "/" . $language . "/epc-estimates--estimations-cpe/",
                Str::replaceArray('?', [$occurence->id, date('Y-m-d', strtotime(data_get($occurence, 'election_date')))], $strings["epc_election_occurence"]) => "/" . $language . "/epc-estimates--estimations-cpe/" . $occurence->id,
                $costing->internal_id => null
            ];


            $epc = [
                "title" => data_get($costing, 'title_' . $language),
                "internal_id" => $costing->internal_id,
                'date' => strtotime($costing->release_date),
                'requester' => data_get($costing, "electionRequester.title_" . $language),
                'pdf' => data_get($costing, 'artifacts.main.' . $language . ".public"),
            ];

            $pboml = data_get($costing, "pboml_document.yaml");
                if ($pboml)
                    $pboml = "data:text/yaml;base64," . base64_encode($pboml);

            $payload = $this->twig->render('epccosting.twig', compact('title', 'language', 'strings', 'breadcrumbs', 'epc', 'pboml'));
            $staticGenerator->saveStaticHtmlFile($language . '/epc-estimates--estimations-cpe/' . $occurence->id . "/" . $costing->internal_id, $payload);
        });
    }

    public function generateOccurenceList($occurence)
    {
        $staticGenerator = $this;

        collect(["en", "fr"])->each(function ($language) use ($staticGenerator, $occurence) {
            $strings = $staticGenerator->translator->getTranslations($language);
            $title = Str::replaceArray('?', [$occurence->id, date('Y-m-d', strtotime(data_get($occurence, 'election_date')))], $strings["epc_election_occurence"]);

            $breadcrumbs = [
                $strings['epc_estimates'] => "/" . $language . "/epc-estimates--estimations-cpe/",
                $title => "/" . $language . "/epc-estimates--estimations-cpe/" . $occurence->id,
            ];

            $costings = collect($occurence->electionCostings)->map(function ($costing) use ($language, $occurence) {
                return [
                    "title" => data_get($costing, 'title_' . $language),
                    'link' => "/" . $language . "/epc-estimates--estimations-cpe/" . $occurence->id . "/" . $costing->internal_id,
                    'date' => strtotime($costing->release_date),
                    'requester' => data_get($costing, "electionRequester.title_" . $language),
                ];
            })->sortByDesc('date');


            $payload = $this->twig->render('epcoccurence.twig', compact('title', 'language', 'strings', 'breadcrumbs', 'occurence', 'costings'));
            $staticGenerator->saveStaticHtmlFile($language . '/epc-estimates--estimations-cpe/' . $occurence->id, $payload);
        });
    }

    public function generateOccurencesIndex($occurences)
    {
        $staticGenerator = $this;

        collect(["en", "fr"])->each(function ($language) use ($staticGenerator, $occurences) {
            $strings = $staticGenerator->translator->getTranslations($language);

            $title = $strings['epc_estimates'];
            $breadcrumbs = [
                $title => "/" . $language . "/epc-estimates--estimations-cpe/",
            ];

            $elections = $occurences->map(function ($occurence) use ($strings, $staticGenerator, $language) {
                return [
                    "title" => Str::replaceArray('?', [$occurence->id, date('Y-m-d', strtotime(data_get($occurence, 'election_date')))], $strings["epc_election_occurence"]),
                    'link' => ("/" . $language . "/epc-estimates--estimations-cpe/" . $occurence->id)
                ];
            });


            $payload = $this->twig->render('epcindex.twig', compact('title', 'language', 'strings', 'breadcrumbs', 'elections'));
            $staticGenerator->saveStaticHtmlFile($language . '/epc-estimates--estimations-cpe/index.html', $payload);
        });
    }


    public function run()
    {
        parent::run();
        $staticGenerator = $this;

        collect($this->s3Client->listObjectsV2([
            'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
            'Prefix' => 'ElectionOccurences/',
        ])['Contents'])->map(function ($storageObject) use ($staticGenerator) {
            $payload = $staticGenerator->s3Client->getObject([
                'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
                'Key' => $storageObject['Key']
            ]);
            return json_decode((string)$payload['Body']);
        })->pipe(function ($occurences) use ($staticGenerator) {
            $staticGenerator->generateOccurencesIndex($occurences);
            return $occurences;
        })->each(function ($occurence) use ($staticGenerator) {
            $staticGenerator->generateOccurenceList($occurence);
        });

        return collect($this->s3Client->listObjectsV2([
            'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
            'Prefix' => 'ElectionCostings/',
        ])['Contents'])->map(function ($storageObject) use ($staticGenerator) {
            $payload = $staticGenerator->s3Client->getObject([
                'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
                'Key' => $storageObject['Key']
            ]);
            return json_decode((string)$payload['Body']);
        })->each(function ($costing) use ($staticGenerator) {
            $staticGenerator->generateCosting($costing);
        });
    }
}
