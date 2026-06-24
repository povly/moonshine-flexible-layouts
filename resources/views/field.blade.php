<div x-data="flexibleLayouts(
    `{{ $addRoute }}`,
    `{{ $column }}`
)"
    {{ $attributes->class('space-y-2') }}
    data-top-level="true"
>
    <div class="_flexible-blocks space-y-2">
        @foreach($blocks as $block)
            {!! $block !!}
        @endforeach
    </div>

    <div>
        {!! $dropdown !!}
    </div>
</div>
