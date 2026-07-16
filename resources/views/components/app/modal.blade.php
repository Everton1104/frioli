@props([
    'id' => 'modal',
    'title' => 'Modal Sem Título',
    'color' => 'primary',
    'btn_close' => true,
    'btn' => [],
    'footerDiv' => ''
])

<div class="modal fade modal-lg" data-bs-backdrop="static" id="{{ $id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header alert alert-{{ $color }}">
                <h5 class="modal-title fs-3">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{ $slot }}
            </div>
            <div class="modal-footer {{ $footerDiv}}">
                @if ($btn_close)
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                @endif
                @foreach ($btn as $b)
                    <button type="button" id="{{ $b['id'] ?? '' }}" class="btn btn-{{ $b['color'] ?? 'primary' }}" onclick="{{ $b['onclick'] ?? '' }}">{{ $b['lbl'] ?? 'Sem rotulo' }}</button>
                @endforeach
            </div>
        </div>
    </div>
</div>

@php
    // Captura todos os names dos inputs dentro do modal
    preg_match_all('/name="([^"]+)"/', $slot, $matches);
    $inputNames = $matches[1] ?? [];

    // Verifica se algum desses names tem erro vindo do controller
    $shouldOpen = false;
    foreach ($inputNames as $name) {
        if ($errors->has($name)) {
            $shouldOpen = true;
            break;
        }
    }
@endphp

<script>
    // Abre o modal automaticamente se houver erros
    document.addEventListener("DOMContentLoaded", function () {
        @if ($shouldOpen)
            var modal = new bootstrap.Modal(document.getElementById("{{ $id }}"));
            modal.show();
        @endif
    });
</script>
