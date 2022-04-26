<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class AbstractModel
{
    function __construct(string $jsonPayload)
    {
        $data = json_decode($jsonPayload, true);
        foreach ($data as $key => $value) $this->{$key} = $value;
    }


    protected function renderBlock(string $html)
    {
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../twig');
        $twig = new \Twig\Environment($loader);
        return $twig->render('block.twig', compact('html'));
    }

    protected function renderMarkdownBlock($block, string $language): ?string
    {
        $rawContent = data_get($block, 'payload_' . $language, data_get($block, 'payload'));
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $content = $converter->convert($rawContent);
        return $content ? $this->renderBlock($content) : null;
    }

    protected function renderHtmlBlock($block, string $language): ?string
    {
        $rawContent = data_get($block, 'payload_' . $language, data_get($block, 'payload'));
        return $rawContent ? $this->renderBlock($rawContent) : null;
    }

    protected function renderableBlocks(): Collection
    {
        return collect($this->blocks ?: [])->whereIn('type_major', ['html', 'markdown']);
    }

    public function hasRenderableBlocks(): bool
    {
        return $this->renderableBlocks()->count() ? true : false;
    }

    public function renderBlocks(string $language)
    {

        $model = $this;
        return $this->renderableBlocks()->map(function ($block) use ($model, $language) {
            $functionName = "render" . Str::studly(data_get($block, "type_major") . "Block");
            return $model->$functionName($block, $language);
        })->filter()->implode("\n");
    }

    public function getFiles(string $language): Collection
    {
        $translator = new OpboTranslator();
        return collect($this->files)->map(function ($file) use ($language, $translator) {
            $strings = $translator->getTranslations($language);
            return [
                "name" => data_get($strings, "files_" . data_get($file, "document_type"), "Document"),
                "description" =>  data_get($file, "description_" . $language),
                "url" => data_get($file, "urls." . $language . ".public", data_get($file, "urls.public")),
                "extension" => data_get($file, "extension", "📄")
            ];
        });
    }
}
