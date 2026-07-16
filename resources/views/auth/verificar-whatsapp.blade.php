@extends("layouts.app")
@section("title", "Verificar WhatsApp")
@section("main")
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header fw-bold">Verificar WhatsApp</div>
                <div class="card-body">
                    @if(session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <p class="text-muted mb-4">
                        @if($codigoEnviado)
                            Enviamos um código de 6 dígitos para <strong>{{ auth()->user()->whatsapp }}</strong>.<br>
                            Digite-o abaixo para ativar sua conta.
                        @else
                            Precisamos verificar o WhatsApp <strong>{{ auth()->user()->whatsapp }}</strong>.<br>
                            Clique em <strong>Enviar código agora</strong> para receber o código de ativação.
                        @endif
                    </p>

                    @if($codigoEnviado)
                        <form method="POST" action="{{ route('verificar.whatsapp.confirmar') }}">
                            @csrf
                            <x-app.input
                                label="Código de verificação"
                                type="text"
                                name="codigo"
                                id="codigo"
                                placeholder="000000"
                                maxlength="6"
                                required="true"
                            />
                            @error('codigo')
                                <div class="text-danger small mb-2">{{ $message }}</div>
                            @enderror

                            <div class="d-grid mt-3 mb-2">
                                <button type="submit" class="btn btn-primary">Confirmar</button>
                            </div>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('verificar.whatsapp.reenviar') }}">
                        @csrf
                        <div class="d-grid {{ $codigoEnviado ? 'mt-1' : 'mt-3' }}">
                            <button id="btn-reenviar" type="submit"
                                class="btn {{ $codigoEnviado ? 'btn-outline-secondary btn-sm' : 'btn-primary' }}">
                                {{ $codigoEnviado ? 'Reenviar código' : 'Enviar código agora' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@section('scriptEnd')
<script>
    (function () {
        const btn    = document.getElementById('btn-reenviar');
        const label  = btn.textContent.trim();
        let restam   = {{ (int) ($aguardar ?? 0) }};
        if (restam <= 0) return;
        btn.disabled = true;
        function tick() {
            if (restam <= 0) { btn.disabled = false; btn.textContent = label; return; }
            btn.textContent = label + ' (' + restam + 's)';
            restam--;
            setTimeout(tick, 1000);
        }
        tick();
    })();
</script>
@endsection
@endsection
