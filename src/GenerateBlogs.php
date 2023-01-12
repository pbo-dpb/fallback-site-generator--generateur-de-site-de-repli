<?php

use Illuminate\Support\Collection;

class GenerateBlogs  extends OpboAbstractGenerator
{

    protected function generateBlog(Blog $blog)
    {
        $staticGenerator = $this;

        collect(['en', 'fr'])->each(function ($language) use ($blog, $staticGenerator) {

            $strings = $staticGenerator->translator->getTranslations($language);

            $title = data_get($blog, 'title_' . $language);
            $breadcrumbs = [
                $strings['blogs'] => "/" . $language . "/additional-analyses--analyses-complementaires/",
                $title => null
            ];


            $adan  = [
                'title' => data_get($blog, 'title_' . $language),
                'request_date' => $blog->release_date,
                'abstract' => data_get($blog, 'abstract_' . $language),
                'slug' =>  $blog->slug,
                "files" => $blog->getFiles($language)->toArray(),
                "blocks" => $blog->renderBlocks($language)
            ];

            $payload = $this->twig->render('blog.twig', compact('title', 'language', 'strings', 'breadcrumbs', 'adan'));
            $staticGenerator->saveStaticHtmlFile($language . '/additional-analyses--analyses-complementaires/' . $blog->slug, $payload);
        });
    }


    protected function generateBlogIndex(Collection $blogs)
    {
        $staticGenerator = $this;

        return $blogs->sortByDesc("release_date")->pipe(function ($blogs) use ($staticGenerator) {
            collect(['en', 'fr'])->each(function ($language) use ($blogs, $staticGenerator) {

                $strings = $staticGenerator->translator->getTranslations($language);

                $title = $strings['blogs'];
                $breadcrumbs = [
                    $title => "/" . $language . "/additional-analyses--analyses-complementaires/",
                ];

                $adan = $blogs->map(function ($blog) use ($strings, $language) {

                    return [
                        'title' => data_get($blog, 'title_' . $language),
                        'request_date' => $blog->release_date,
                        'abstract' => data_get($blog, 'abstract_' . $language),
                        'link' => "/" . $language . "/additional-analyses--analyses-complementaires/" . $blog->slug,
                    ];
                })->toArray();

                $payload = $this->twig->render('blogs.twig', compact('title', 'language', 'strings', 'breadcrumbs', 'adan'));
                $staticGenerator->saveStaticHtmlFile($language . '/additional-analyses--analyses-complementaires/' . "index.html", $payload);
            });
        });
    }

    public function run()
    {

        parent::run();
        $job = $this;
        collect($this->s3Client->listObjectsV2([
            'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
            'Prefix' => 'Blogs/',
        ])['Contents'])->map(function ($storageObject) use ($job) {
            $payload = $job->s3Client->getObject([
                'Bucket' => $_ENV['SOURCE_S3_BUCKET'],
                'Key' => $storageObject['Key']
            ]);
            return (string)$payload['Body'];
        })->filter()->map(function ($rawString) {
            return new Blog($rawString);
        })->pipe(function ($blogs) use ($job) {
            $job->generateBlogIndex($blogs);
            return $blogs;
        })->each(function ($blog) use ($job) {
            $job->generateBlog($blog);
        });
    }
}
