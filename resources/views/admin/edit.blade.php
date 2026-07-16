@extends('layouts.app')
@section('title', 'Editar conteúdo')
@section('main')
<div class="container py-4" style="max-width:820px">
    <nav class="mb-2">
        <small class="text-secondary">
            <a href="{{ route('admin.index') }}" class="text-secondary">Painel</a> /
            <span class="text-capitalize">{{ $content->section }}</span> /
            {{ $content->label }}
        </small>
    </nav>
    <h3 class="mb-3" style="color: var(--marrom);">Editar: {{ $content->label }}</h3>

    <div class="card">
        <div class="card-body">
            @if($content->type === 'image')
                <p class="text-secondary small mb-3">Envie uma imagem para substituir a padrão do site. Fica em <code>storage/app/public/conteudo/</code>. JPG, PNG ou WebP até 5 MB.</p>

                @php $atual = $content->value ? Storage::url('conteudo/' . $content->value) : null; @endphp
                <div class="mb-3">
                    <label class="form-label fw-semibold">Imagem atual</label>
                    @if($atual)
                        <div class="mb-2"><img src="{{ $atual }}?v={{ time() }}" alt="imagem atual" style="max-height:240px; border-radius:8px; box-shadow:0 4px 14px rgba(0,0,0,.15)"></div>
                    @else
                        <p class="text-secondary small mb-0">Nenhuma imagem enviada — o site está usando a imagem padrão.</p>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.update', [$content->section, $content->key]) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nova imagem</label>
                        <input type="file" name="value" accept="image/*" class="form-control @error('value') is-invalid @enderror">
                        @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn" style="background-color: var(--marrom); color:#1a1410">Enviar imagem</button>
                    <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </form>

                @if($content->value)
                <form method="POST" action="{{ route('admin.update', [$content->section, $content->key]) }}" class="mt-3" onsubmit="return confirm('Remover esta imagem e voltar à padrão?')">
                    @csrf @method('PUT')
                    <input type="hidden" name="remover" value="1">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Remover imagem (voltar ao padrão)</button>
                </form>
                @endif
            @else
                <form method="POST" action="{{ route('admin.update', [$content->section, $content->key]) }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Conteúdo</label>
                        <textarea name="value" class="form-control @error('value') is-invalid @enderror" rows="10">{{ old('value', $content->value) }}</textarea>
                        <small class="text-secondary">Você pode usar HTML básico: <code>&lt;strong&gt;</code>, <code>&lt;br&gt;</code>, <code>&lt;a href="..."&gt;</code>, <code>&lt;span style="color:..."&gt;</code>.</small>
                        @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn" style="background-color: var(--marrom); color:#1a1410">Salvar alterações</button>
                        <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
