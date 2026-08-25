@extends("layouts.app")
@section("title", "Cadastrar WhatsApp")
@php use App\Models\PageContent; @endphp
@section("style")
    <link rel="stylesheet" href="{{ asset('css/frioli-guest.css') }}?v={{ time() }}">
@endsection
@section("main")
<div class="container fp py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header fw-bold">{!! PageContent::def('whatsapp', 'cadastrar_titulo') !!}</div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        {!! PageContent::def('whatsapp', 'cadastrar_texto') !!}
                    </p>

                    <form method="POST" action="{{ route('cadastrar.whatsapp.salvar') }}">
                        @csrf
                        <x-app.input
                            label="Número de WhatsApp"
                            type="tel"
                            name="whatsapp"
                            id="whatsapp"
                            placeholder="Ex: 11987654321 (sem código do país)"
                            :value="old('whatsapp')"
                            required="true"
                        />
                        @error('whatsapp')
                            <div class="text-danger small mb-2">{{ $message }}</div>
                        @enderror

                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-primary">Enviar código</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
