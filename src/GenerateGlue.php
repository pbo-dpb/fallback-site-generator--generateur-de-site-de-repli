<?php


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

            $payload = $this->twig->render('home.twig', compact("strings", "language", "title"));
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
