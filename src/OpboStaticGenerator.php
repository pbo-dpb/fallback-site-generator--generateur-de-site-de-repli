<?php

class OpboStaticGenerator
{
    protected $twig;

    function __construct()
    {
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../twig');
        $this->twig = new \Twig\Environment($loader);
    }

    public function run()
    {
        echo $this->twig->render('publication.twig', ['name' => 'Rémy']);
    }
}
