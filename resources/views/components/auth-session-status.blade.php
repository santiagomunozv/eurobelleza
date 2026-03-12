@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm font-medium text-green-700']) }}>
        {{ $status }}
    </div>
@endif
