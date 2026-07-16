@extends("layouts.app")
@section("title", "Cadastrar WhatsApp")
@section("main")
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header fw-bold">Confirmar número de WhatsApp</div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Para continuar, precisamos verificar o seu número de WhatsApp.<br>
                        Informe abaixo e enviaremos um código de confirmação.
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
