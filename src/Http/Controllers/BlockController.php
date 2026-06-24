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
     * Handle AJAX "add block" request.
     *
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

        return JsonResponse::make()->html((string) $block);
    }

    /**
     * Find the FlexibleLayouts field on the current page/resource.
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

        return $fields
            ->onlyFields()
            ->findByColumn($request->get('field'));
    }
}
