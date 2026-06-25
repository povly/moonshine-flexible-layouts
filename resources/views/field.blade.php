<div x-data="flexibleLayouts(
        `{{ $addRoute }}`,
        `{{ $column }}`,
        {{ Illuminate\Support\Js::from($blockMeta ?? []) }},
        `{{ $flPath ?? $column }}`)"
     {{ $attributes->class('_fl-field') }}>

    <div class="_fl-tabs">
        @foreach($blocks as $block)
            <button type="button"
                    class="_fl-tab {{ $loop->first ? '_fl-tab--active' : '' }}"
                    data-orig-idx="{{ $loop->index }}"
                    @click="switchTab({{ $loop->index }})">
                @if(!$disableSort)<span class="_fl-tab-grip">⠿</span>@endif
                @if($blockMeta[$block->name()]['icon'] ?? null)<span class="_fl-tab-icon">{!! $blockMeta[$block->name()]['icon'] !!}</span>@endif
                <span class="_fl-tab-label">{{ $block->title() }}</span>
            </button>
        @endforeach
    </div>

    <div class="_fl-blocks">
        @foreach($blocks as $block)
            <div class="_fl-block {{ $loop->first ? '' : 'hidden' }}"
                 data-row-key="{{ $loop->index }}"
                 data-correct-type="{{ $block->name() }}">
                {!! $block->renderTabContent() !!}
            </div>
        @endforeach
    </div>

    @if(!$disableAdd)
        <div class="_fl-add">
            <button type="button" class="_fl-add-btn" @click="openPicker()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="_fl-add-icon">
                    <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/>
                </svg>
                {{ $labels['add_block'] }}
            </button>
        </div>

        <template x-teleport="body">
            <div class="_fl-picker-overlay"
                 x-show="pickerOpen"
                 x-transition.opacity
                 @keydown.escape.window="closePicker()">
                <div class="_fl-picker"
                     @click.outside="closePicker()"
                     x-transition.scale.origin.center>

                    <div class="_fl-picker-header">
                        <input type="text"
                               class="_fl-picker-search"
                               x-model="pickerSearch"
                               placeholder="{{ $labels['search_blocks'] }}"
                               @keydown.escape.stop="closePicker()"
                               x-ref="searchInput">
                        <button type="button" class="_fl-picker-close" @click="closePicker()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/>
                            </svg>
                        </button>
                    </div>

                    <div class="_fl-picker-cats" x-show="pickerCategories.length > 1">
                        <button type="button"
                                class="_fl-picker-cat"
                                :class="{ '_fl-picker-cat--active': pickerCategory === null }"
                                @click="pickerCategory = null">
                            {{ $labels['all_categories'] }}
                        </button>
                        <template x-for="cat in pickerCategories" :key="cat">
                            <button type="button"
                                    class="_fl-picker-cat"
                                    :class="{ '_fl-picker-cat--active': pickerCategory === cat }"
                                    @click="pickerCategory = cat"
                                    x-text="cat"></button>
                        </template>
                    </div>

                    <div class="_fl-picker-grid">
                        <template x-for="(meta, name) in pickerFiltered" :key="name">
                            <button type="button"
                                    class="_fl-picker-card"
                                    @click="add(name); closePicker()">
                                <span class="_fl-picker-card-icon" x-html="meta.icon || '+'"></span>
                                <div class="_fl-picker-card-body">
                                    <span class="_fl-picker-card-title" x-text="meta.title"></span>
                                    <span class="_fl-picker-card-desc" x-show="meta.description" x-text="meta.description"></span>
                                </div>
                            </button>
                        </template>
                    </div>

                    <div class="_fl-picker-empty" x-show="Object.keys(pickerFiltered).length === 0">
                        {{ $labels['no_blocks_found'] }}
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>
