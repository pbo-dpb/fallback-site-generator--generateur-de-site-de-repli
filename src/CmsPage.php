<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class CmsPage extends AbstractModel
{

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
        return collect($this->blocks)->whereIn('type_major', ['html', 'markdown']);
    }

    public function hasRenderableBlocks(): bool
    {
        return $this->renderableBlocks()->count() ? true : false;
    }

    public function render(string $language)
    {

        $model = $this;
        return $this->renderableBlocks()->map(function ($block) use ($model, $language) {
            $functionName = "render" . Str::studly(data_get($block, "type_major") . "Block");
            return $model->$functionName($block, $language);
        })->filter()->implode("\n");
    }
}
