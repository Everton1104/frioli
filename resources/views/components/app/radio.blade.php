@props([
    'name',
    'id' => null, 
    'options' => [],   // ['adm' => 'Administrador', 'func' => 'Funcionário']
    'value' => null,   // valor selecionado
    'required' => false, 
    'divClass' => 'mb-3', 
    'slot' => null,
    'inline' => false, // radios lado a lado
])

<div class="{{ $divClass }}">
    @foreach($options as $key => $label)
        @php
            $id = $name . '_' . $key;
            $checked = old($name, $value) == $key;
        @endphp

        <div class="form-check {{ $inline ? 'form-check-inline' : '' }}">
            <input
                class="form-check-input {{ $errors->has($name) ? 'is-invalid' : '' }}"
                type="radio"
                name="{{ $name }}"
                id="{{ $id }}"
                value="{{ $key }}"
                @checked($checked)
            >

            <label class="form-check-label" for="{{ $id }}">
                {{ $label }}
            </label>
        </div>
    @endforeach

    {{-- Mensagem de erro --}}
    @error($name)
        <div class="invalid-feedback d-block">
            {{ $message }}
        </div>
    @enderror

    {{-- Help text via slot --}}
    @if(trim($slot))
        <div class="form-text">
            {{ $slot }}
        </div>
    @endif

</div>