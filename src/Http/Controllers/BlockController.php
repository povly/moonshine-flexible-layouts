<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Http\Controllers;

use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Collections\Fields;
use MoonShine\Laravel\Http\Controllers\MoonShineController;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\Enums\ToastType;
use Povly\FlexibleLayouts\Fields\Block;
use Povly\FlexibleLayouts\Fields\FlexibleLayouts;
use Throwable;

final class BlockController extends MoonShineController
{
    /**
     * @throws Throwable
     */
    public function store(CrudRequestContract $request): JsonResponse
    {
        $field = $this->getField($request);

        if (is_null($field)) {
            return JsonResponse::make()
                ->toast('Field not found', ToastType::ERROR);
        }

        $blockName = (string) $request->get('name');

        /** @var Block|null $block */
        $block = $field
            ->setValue([['_type' => $blockName]])
            ->getFilledBlocks()
            ->findByName($blockName)
            ?->removeButton($field->getRemoveButton());

        if (is_null($block)) {
            return JsonResponse::make()
                ->toast('Block not found', ToastType::ERROR);
        }

        $blockCount = (int) $request
            ->collect('counts')
            ->get($block->name(), 0);

        if ($block->hasLimit() && $block->limit() <= $blockCount) {
            return JsonResponse::make()
                ->toast("Limit count {$block->limit()}", ToastType::ERROR);
        }

        $mode = (string) $request->get('mode', 'accordion');

        if ($mode === 'tabs') {
            return JsonResponse::make()->merge([
                'blockHtml' => $block->renderTabContent(),
                'blockTitle' => $field->getBlockTitles()[$blockName] ?? $blockName,
            ]);
        }

        return JsonResponse::make()->merge([
            'blockHtml' => (string) $block,
        ]);
    }

    /**
     * Find the FlexibleLayouts field on the current page/resource.
     * Supports nested fields via dot-path (e.g. "blocks.page-top.content").
     *
     * @throws Throwable
     */
    private function getField(CrudRequestContract $request): ?FlexibleLayouts
    {
        $page = $request->getPage();

        if (! $resource = $request->getResource()) {
            $fields = Fields::make(is_null($page->getPageType()) ? $page->getComponents() : $page->getFields());
        } else {
            $fields = match ($page->getPageType()) {
                PageType::INDEX => $resource->getIndexFields(),
                PageType::DETAIL => $resource->getDetailFields(),
                PageType::FORM => $resource->getFormFields(),
                default => $page->getComponents(),
            };
        }

        $path = (string) $request->get('path', $request->get('field'));
        $segments = explode('.', $path);

        $topColumn = array_shift($segments);
        $field = $fields->onlyFields()->findByColumn($topColumn);

        if (! $field instanceof FlexibleLayouts) {
            return null;
        }

        if (empty($segments)) {
            return $field;
        }

        $current = $field;

        while (! empty($segments)) {
            $blockName = array_shift($segments);
            $fieldColumn = array_shift($segments);

            if ($fieldColumn === null) {
                break;
            }

            $block = $current->blocks()->findByName($blockName);

            if (! $block) {
                return null;
            }

            $current = $block->fields()->onlyFields()->findByColumn($fieldColumn);

            if (! $current instanceof FlexibleLayouts) {
                return null;
            }
        }

        $current->setNameAttribute($this->buildNameFromPath($path));
        $current->setFlPath($path);

        return $current;
    }

    /**
     * Build the name attribute template from a dot-separated path.
     *
     * Examples:
     *   "blocks"                    → "blocks"
     *   "blocks.page-top.content"   → "blocks[${index0}][content]"
     */
    private function buildNameFromPath(string $path): string
    {
        $segments = explode('.', $path);

        if (count($segments) <= 1) {
            return $segments[0];
        }

        $name = $segments[0];
        $level = 0;

        for ($i = 2; $i < count($segments); $i += 2) {
            $name .= '[${index'.$level.'}]['.$segments[$i].']';
            $level++;
        }

        return $name;
    }
}
