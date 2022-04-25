<?php


class GenerateGlue extends OpboAbstractGenerator
{

    public function generateLanguageSelectorSplashPage()
    {
        $payload = $this->twig->render('language-splash.twig');
        $this->saveStaticHtmlFile('index.html', $payload);
    }




    public function run()
    {
        parent::run();
        $this->generateLanguageSelectorSplashPage();
    }
}
