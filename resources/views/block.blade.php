<div class="_fl-card" x-data="{ open: true }">
    <div class="_fl-card-header" @click="open = !open">
        @if(!$block->isSortDisabled())
            <span class="fl-handle" @click.stop title="Drag to reorder">⠿</span>
        @endif
        <span class="_fl-card-title">{{ $block->title() }}</span>
        @if($button)
            <span class="_fl-card-actions" @click.stop>{!! $button !!}</span>
        @endif
    </div>
    <div class="_fl-card-body" x-show="open" x-transition.duration.200ms>
        {!! $fields !!}
    </div>
</div>
