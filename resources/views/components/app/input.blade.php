@props([
    'label' => null, 
    'type' => 'text', 
    'name' => null, 
    'id' => null, 
    'value' => null, 
    'required' => false, 
    'divClass' => 'mb-3', 
    'slot' => null
])
<div class="{{ $divClass }}">
    @if($label)
        <label for="{{ $id ?? $name }}" class="form-label">
            {{ $label }}
        </label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $id ?? $name }}"
        required="{{ $required }}"
        value="{{ is_array(old($name, $value)) ? '' : old($name, $value) }}"
        {{ $attributes->class([
            'form-control',
            'is-invalid' => $errors->has($name),
            'is-valid'   => !$errors->has($name) && old($name),
        ]) }}
    >

    {{-- Se tiver slot coloca no form-text --}}
    @if(trim($slot))
        <div class="form-text">
            {{ $slot }}
        </div>
    @endif
    
    {{-- Mensagem de erro --}}
    @error($name)
        <div class="invalid-feedback">
            {{ $message }}
        </div>
    @enderror
</div>
