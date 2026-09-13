<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Tests\Unit\Fixtures;

use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Laravel\Resources\ModelResource;
use Povly\FlexibleLayouts\Fields\FlexibleLayouts;
use Throwable;

final class FaqModel extends Model
{
    protected $table = 'faq_models';
}

/**
 * @extends FormPage<FaqResource>
 */
final class FaqFormPage extends FormPage
{
    /**
     * @return list<FieldContract>
     * @throws Throwable
     */
    protected function fields(): iterable
    {
        return [
            FaqResource::contentField(),
        ];
    }
}

final class FaqResource extends ModelResource
{
    protected string $model = FaqModel::class;

    public static function contentField(): FlexibleLayouts
    {
        return FlexibleLayouts::make('Content', 'content')
            ->block('faq-items', 'FAQ', []);
    }

    public function getTitle(): string
    {
        return 'FaqResource';
    }

    protected function pages(): array
    {
        return [
            FaqFormPage::class,
        ];
    }

    public function findItem(bool $orFail = false): ?DataWrapperContract
    {
        return null;
    }

    public function getItems(): iterable
    {
        return [];
    }

    public function massDelete(array $ids): void {}

    public function delete(DataWrapperContract $item, ?FieldsContract $fields = null): bool
    {
        return true;
    }

    public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract
    {
        return $item;
    }
}
