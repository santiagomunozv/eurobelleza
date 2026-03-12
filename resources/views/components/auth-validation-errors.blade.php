@props(['errors'])

@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-red-200 bg-red-50 px-3 py-2']) }}>
        <div class="ui-error-title">
            {{ __('Whoops! Something went wrong.') }}
        </div>

        <ul class="ui-error-list">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
