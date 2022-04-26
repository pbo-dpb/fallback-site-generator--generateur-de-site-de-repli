<?php


use Illuminate\Support\Str;

class GenerateGlue extends OpboAbstractGenerator
{

    public function generateLanguageSelectorSplashPage()
    {
        $payload = $this->twig->render('language-splash.twig');
        $this->saveStaticHtmlFile('index.html', $payload);
    }

    public function generateHome()
    {
        $job = $this;
        collect(["en", "fr"])->each(function ($language) use ($job) {
            $strings = $job->translator->getTranslations($language);
            $title = $strings['title'];

            $sections = collect($job->previousJobs['GenerateCmsPages'])->whereNotNull('cms_section.title_' . $language)->groupBy('cms_section.title_' . $language)
                ->map(function ($pages, $sectionkey) use ($language) {
                    return [
                        "title" => data_get(collect($pages)->first(), "cms_section.title_" . $language),
                        "pages" => collect($pages)->filter(function ($page) use ($language) {
                            return $page->hasRenderableBlocks() || $page->getFiles($language)->count();
                        })->sortBy(function ($page) use ($language) {
                            return Str::padLeft(data_get($page, 'order', '0'), 3, '0') . data_get($page, 'title_' . $language);
                        })->map(function ($page) use ($language) {
                            return [
                                "title" => data_get($page, "title_" . $language),
                                "link" => '/' . $language . "/" . data_get($page, 'slug')
                            ];
                        })
                    ];
                });

            $payload = $this->twig->render('home.twig', compact("strings", "language", "title", "sections"));
            $job->saveStaticHtmlFile($language . '/index.html', $payload);
        });
    }




    public function run()
    {
        parent::run();
        $this->generateLanguageSelectorSplashPage();
        $this->generateHome();
    }
}
