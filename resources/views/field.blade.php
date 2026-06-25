<div x-data="flexibleLayouts(
        `{{ $addRoute }}`,
        `{{ $column }}`,
        `{{ $asTabs ? 'tabs' : 'accordion' }}`,
        {{ Illuminate\Support\Js::from($blockTitles ?? []) }},
        `{{ $flPath ?? $column }}`)"
     {{ $attributes->class('_fl-field') }}>

    @if($asTabs)
        <div class="_fl-tabs">
            @foreach($blocks as $block)
                <button type="button"
                        class="_fl-tab {{ $loop->first ? '_fl-tab--active' : '' }}"
                        @click="switchTab({{ $loop->index }})">
                    @if(!$disableSort)<span class="_fl-tab-grip">⠿</span>@endif
                    <span class="_fl-tab-label">{{ $block->title() }}</span>
                </button>
            @endforeach
        </div>

        <div class="_fl-blocks">
            @foreach($blocks as $block)
                <div class="_fl-block {{ $loop->first ? '' : 'hidden' }}"
                     data-row-key="{{ $loop->index }}">
                    {!! $block->renderTabContent() !!}
                </div>
            @endforeach
        </div>
    @else
        <div class="_fl-blocks">
            @foreach($blocks as $block)
                <div class="_fl-block" data-row-key="{{ $loop->index }}">
                    {!! $block !!}
                </div>
            @endforeach
        </div>
    @endif

    @if(!$disableAdd)
        <div class="_fl-add">
            {!! $dropdown !!}
        </div>
    @endif
</div>
