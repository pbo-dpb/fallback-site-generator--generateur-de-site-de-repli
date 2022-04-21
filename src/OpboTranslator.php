<?php


class OpboTranslator
{
    protected array $sourceTranslations;
    function __construct()
    {
        $this->sourceTranslations = require_once(__DIR__ . "/../strings.php");
    }

    public function getTranslations(string $language): array
    {
        return collect($this->sourceTranslations)->mapWithKeys(function ($values, $key) use ($language) {
            return [$key => data_get($values, $language)];
        })->toArray();
    }
}
