<div x-data="flexibleLayouts(
    `{{ $addRoute }}`,
    `{{ $column }}`,
    `{{ $asTabs ? 'tabs' : 'accordion' }}`,
    {{ Illuminate\Support\Js::from($blockTitles ?? []) }}
)"
    {{ $attributes->class('space-y-2') }}
    data-top-level="true"
>
    @if($asTabs)
        {{-- Tab bar (sortable) --}}
        <div class="fl-tabs flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700">
            @foreach($blocks as $index => $block)
                <button type="button"
                        @click="switchTab({{ $loop->index }})"
                        :class="activeTab === {{ $loop->index }} ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="fl-tab px-3 py-2 text-sm font-medium cursor-grab border-b-2 transition-colors whitespace-nowrap flex items-center gap-1"
                >
                    <span class="fl-tab-grip opacity-30 hover:opacity-60 cursor-grab-active">⠿</span>
                    <span>{{ $block->title() }}</span>
                </button>
            @endforeach
        </div>

        {{-- Tab content --}}
        <div class="_flexible-blocks space-y-4">
            @foreach($blocks as $index => $block)
                <div class="_flexible-block {{ $loop->first ? '' : 'hidden' }}">
                    {!! $block->renderContent() !!}
                </div>
            @endforeach
        </div>

        {{-- Actions --}}
        <div class="flex gap-2 items-center pt-2">
            @if(!$disableRemove ?? true)
                <button type="button"
                        @click="removeActive()"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                    {{ __('Удалить') }}
                </button>
            @endif
            {!! $dropdown !!}
        </div>
    @else
        {{-- Accordion mode --}}
        <div class="_flexible-blocks space-y-2">
            @foreach($blocks as $block)
                {!! $block !!}
            @endforeach
        </div>

        <div>
            {!! $dropdown !!}
        </div>
    @endif
</div>
