@extends('layouts.app')
@section('title', 'Painel Admin')
@section('main')
<div class="container py-4">
    <h2 class="mb-1" style="color: var(--marrom);">Painel do Administrador</h2>
    <p class="text-secondary mb-4">Edite os textos do site e gerencie as fotos da galeria.</p>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2">
            {{ session('success') }}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header fw-bold">✍️ Conteúdo do site</div>
                <div class="card-body">
                    @foreach($sections as $section => $items)
                        <p class="text-uppercase small text-secondary mb-1 mt-2">{{ $section }}</p>
                        <ul class="list-group list-group-flush mb-2">
                            @foreach($items as $item)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span class="me-2">
                                        {{ $item->label }}
                                        @if($item->type === 'image')<span class="badge text-bg-secondary ms-1">imagem</span>@endif
                                    </span>
                                    <a href="{{ route('admin.edit', [$section, $item->key]) }}" class="btn btn-sm" style="background-color: var(--marrom); color:#1a1410">Editar</a>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header fw-bold">🖼️ Galeria de fotos</div>
                <div class="card-body d-flex flex-column">
                    <p class="text-secondary">Adicione, remova ou reordene as fotos que aparecem no <strong>carrossel do topo</strong> e na seção <strong>"Nossa casa"</strong>.</p>
                    <p class="text-secondary small mb-3">Cada foto aceita título e descrição, e pode ser arrastada para mudar a ordem.</p>
                    <a href="{{ route('admin.fotos') }}" class="btn mt-auto" style="background-color: var(--marrom); color:#1a1410">Gerenciar fotos</a>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <a href="{{ url('/') }}" class="btn btn-outline-secondary">← Ver site</a>
    </div>
</div>
@endsection
