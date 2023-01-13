<?php

use Illuminate\Support\Collection;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class GenerateResearchTools  extends OpboAbstractGenerator
{

    protected $tools = [];
    protected $assets = [];

    protected function fetchTools() {
        $staticGenerator = $this;

        $this->tools = collect($this->s3Client->listObjectsV2([
            'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
            'Prefix' => 'ResearchTools/',
        ])['Contents'])->map(function ($storageObject) use ($staticGenerator) {
            $payload = $staticGenerator->s3Client->getObject([
                'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
                'Key' => $storageObject['Key']
            ]);
            return (string)$payload['Body'];
        })->map(function($toolPayload) {
            return new ResearchTool($toolPayload);
        });
    }
   
    protected function generateToolsList()
    {

        $staticGenerator = $this;

        collect(['en', 'fr'])->each(function ($language) use ($staticGenerator) {
            $strings = $staticGenerator->translator->getTranslations($language);

            $title = $strings['research_tools'];
            $breadcrumbs = [
                $title => "/" . $language . "/research--recherches/tools--outils/",
            ];
            $tools = $staticGenerator->tools;
            $payload = $this->twig->render('research-tools.twig', compact('title', 'language', 'strings', 'breadcrumbs', 'tools'));
            $staticGenerator->saveStaticHtmlFile($language . '/research--recherches/tools--outils/index.html', $payload);

        });
    }

    public function extractToolAssets(string $source, string $prefix) {

        if (!data_get($this->assets, $source)) {
            // Download zip file
            $tmpZipPath = tempnam(sys_get_temp_dir(), 'PBO');
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $source, [
                'sink' => $tmpZipPath,
            ]);

            // Prepare temporary folder to receive zip content
            $tmpDir = sprintf('%s%sPBO-%s', sys_get_temp_dir(), DIRECTORY_SEPARATOR, Str::random(32));
            mkdir($tmpDir);

            // Unzip file
            $zip = new \ZipArchive;
            $res = $zip->open($tmpZipPath);
            if ($res === true) {
                $zip->extractTo($tmpDir);
                $zip->close();
            } else {
                throw new \Exception('Zip file appears to be unreadable.', 1);
            }
            unlink($tmpZipPath);
            $this->assets[$source] = $tmpDir;
        }

        $assetsDir = $this->assets[$source];
        

        // Search for the manifest.json
        $temporaryFolderRecursiveDirectoryIterator = new \RecursiveDirectoryIterator($assetsDir);
        $manifestSplFile = null;
        foreach (new \RecursiveIteratorIterator($temporaryFolderRecursiveDirectoryIterator) as $file) {
            if ($file->getFilename() === 'manifest.json') {
                $manifestSplFile = $file;
                break;
            }
        }

        if (! $manifestSplFile) {
            throw new \Exception('A `manifest.json` file must be provided as part of the zip file.', 1);
        }

        $basePath = $manifestSplFile->getPath();

        // Establish the entry point file
        $entryFileInfo = collect(json_decode(file_get_contents($manifestSplFile->getPathName()), true))->first(function ($file) {
            return Arr::get($file, 'isEntry', false);
        });

        if ((! $entryFileInfo || ! Arr::has($entryFileInfo, 'file')) && ! $this->researchTool->entry_script_path) {
            /**
             * The expected manifest file should specify an entry point with the following format:
             * {
             *  "file.js": {
             *      "file": "compiled/path/to/file.js",
             *      "isEntry": true
             *  }
             * }
             */
            throw new \Exception("Manifest doesn't specify an entry file (isEntry).", 1);
        }

        $this->s3Client->uploadDirectory($assetsDir, $_ENV['OUTPUT_S3_BUCKET'], $prefix);

        return data_get($entryFileInfo, 'file');

    }

    protected function generateToolPage(ResearchTool $tool)
    {

        $staticGenerator = $this;

        collect(['en', 'fr'])->each(function ($language) use ($staticGenerator, $tool) {
            $strings = $staticGenerator->translator->getTranslations($language);

            $title = data_get($tool, 'name_' . $language);
            $path = $language . "/research--recherches/tools--outils/" . $tool->slug . "/";
            $filepath =  $path . "index";
            $breadcrumbs = [
                $strings['research_tools'] => "/" . $language . "/research--recherches/tools--outils/",
                $title => "/" . $filepath,
            ];

            if ($source = data_get($tool, 'latest_zip_url')) {
                try {
                    $entry = $staticGenerator->extractToolAssets($source, $path);
                } catch (\Throwable $th) {
                    $entry = data_get($tool, 'entry_script_path');
                }
                
            } else {
                $entry = data_get($tool, 'entry_script_path');
            }

            if ($entry && !str_starts_with('http', $entry)) {
                $entry = "/" . $path . 'dist/' . $entry;
            }


            $payload = $this->twig->render('research-tool.twig', compact('title', 'language', 'strings', 'breadcrumbs', 'tool', 'entry'));
            $staticGenerator->saveStaticHtmlFile($filepath, $payload);

        });
    }


    

    public function run()
    {

        parent::run();
        $staticGenerator = $this;
        $this->fetchTools();
        $this->generateToolsList();
        $this->tools->each(function($tool) use ($staticGenerator) {
            $staticGenerator->generateToolPage($tool);
        });

    }
}
