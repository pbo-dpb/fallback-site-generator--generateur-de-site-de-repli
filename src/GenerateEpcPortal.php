<?php


class GenerateEpcPortal extends OpboAbstractGenerator
{


    public function generateGuardPage()
    {
    }


    public function run()
    {

        parent::run();



        $this->generateGuardPage();
    }
}
