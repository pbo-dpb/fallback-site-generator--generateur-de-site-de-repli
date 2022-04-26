<?php


class CopyStaticAssets extends OpboAbstractGenerator
{
    public function run()
    {
        parent::run();

        $job = $this;
        collect(array_diff(scandir(__DIR__ . '/../assets'), ['..', '.']))->each(function ($filename) use ($job) {
            $job->s3Client->putObject([
                'Bucket' => $_ENV['OUTPUT_S3_BUCKET'],
                'Key' => $filename,
                'SourceFile' => __DIR__ . '/../assets/' . $filename,
            ]);
        });
    }
}
