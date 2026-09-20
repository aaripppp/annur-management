@props(['messages' => []])

@if($messages)
    <div {{ $attributes->merge(['class' => 'mt-1']) }}>
        @foreach((array) $messages as $message)
            <p class="text-body-sm text-error">{{ $message }}</p>
        @endforeach
    </div>
@endif
