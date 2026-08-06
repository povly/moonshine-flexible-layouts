<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Http\Controllers;

use Illuminate\Support\Facades\Log;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Collections\Fields;
use MoonShine\Laravel\Http\Controllers\MoonShineController;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\Enums\ToastType;
use Povly\FlexibleLayouts\Blocks\Block;
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

        // Set formName so AJAX-rendered fields get the correct x-id scope.
        // In the normal pipeline FormBuilder::viewData() sets this, but
        // AJAX requests bypass that pipeline — leaving formName null.
        $resource = $request->getResource();
        if (! is_null($resource)) {
            $field->formName($resource->getUriKey());
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

        // Recount block instances from the persisted model to enforce the
        // per-type limit authoritatively. Falls back to request-supplied
        // counts for unsaved records (no model yet) — see resolveBlockCount().
        $blockCount = $this->resolveBlockCount($request, $field, $block->name());

        if ($block->hasLimit() && $block->limit() <= $blockCount) {
            return JsonResponse::make()
                ->toast("Limit count {$block->limit()}", ToastType::ERROR);
        }

        $renderedHtml = $block->renderTabContent();

        return JsonResponse::make()->merge([
            'blockHtml' => $renderedHtml,
            'blockTitle' => $field->getBlockTitles()[$blockName] ?? $blockName,
        ]);
    }

    /**
     * Resolve the current instance count for a block type.
     *
     * Authoritative source: the persisted model attribute.
     * Fallback (new, unsaved records): request-supplied counts with a log warning.
     *
     * @return int<0, max>
     */
    private function resolveBlockCount(CrudRequestContract $request, FlexibleLayouts $field, string $blockName): int
    {
        $item = $request->getResource()?->getItem();

        if (! is_null($item)) {
            $stored = $item->{$field->getColumn()} ?? [];

            if (! is_iterable($stored)) {
                return 0;
            }

            $authoritative = 0;
            foreach ($stored as $entry) {
                if (is_array($entry) && ($entry['_type'] ?? null) === $blockName) {
                    $authoritative++;
                }
            }

            return $authoritative;
        }

        // Fallback for new records — no persisted model yet.
        // Diagnostic log — gated by config('flexible-layouts.logging').
        // Default: off in prod (APP_DEBUG=false), on in dev/staging.
        if (config('flexible-layouts.logging')) {
            Log::warning('[FlexibleLayouts] limit check used client-supplied counts (new record, no model)', [
                'block' => $blockName,
                'column' => $field->getColumn(),
            ]);
        }

        return (int) $request
            ->collect('counts')
            ->get($blockName, 0);
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
