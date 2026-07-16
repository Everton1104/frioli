@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'options' => [],
    'value' => null,
    'required' => false,
    'divClass' => 'mb-3',
    'placeholder' => 'Selecione...',
])

@php
    $id = $id ?? $name;
    $selectedValue = old($name, $value);
@endphp

<div class="{{ $divClass }}">

    @if($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }}
        </label>
    @endif

    <select
        name="{{ $name }}"
        id="{{ $id }}"
        {{ $required ? 'required' : '' }}
        {{ $attributes->class([
            'form-select',
            'is-invalid' => $errors->has($name),
            'is-valid' => !$errors->has($name) && ($selectedValue !== null && $selectedValue !== ''),
        ]) }}
    >
        <option value="">{{ $placeholder }}</option>

        @foreach($options as $key => $text)
            <option value="{{ $key }}" @selected((string) $selectedValue === (string) $key)>
                {{ $text }}
            </option>
        @endforeach
    </select>

    @if(trim($slot))
        <div class="form-text">
            {{ $slot }}
        </div>
    @endif

    @error($name)
        <div class="invalid-feedback">
            {{ $message }}
        </div>
    @enderror

</div>
