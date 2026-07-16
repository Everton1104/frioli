@extends('layouts.app')

@section('title', 'Pagamento')

@section('main')
<div class="container mb-5">
    <div class="row justify-content-center mt-3">
        <div class="col-lg-7">
            <div class="card shadow">
                <div class="card-body text-center">
                    <div id="retorno-icon" class="display-6 mb-2">⏳</div>
                    <h5 id="retorno-titulo" class="mb-2 text-warning">Aguardando confirmação do pagamento</h5>
                    <p id="retorno-msg" class="text-muted mb-3">Estamos confirmando seu pagamento com a operadora — em geral leva menos de 1 minuto. Avisaremos por WhatsApp assim que for confirmado.</p>

                    <div id="retorno-ok" class="d-none">
                        <p class="text-success fw-semibold mb-3">Pagamento confirmado com sucesso!</p>
                        <a href="{{ route('dashboard') }}" class="btn btn-primary">Voltar ao painel</a>
                    </div>

                    <div id="retorno-wait" class="mt-2">
                        <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                        <span class="text-muted small">verificando...</span>
                    </div>

                    <div class="mt-3">
                        <a href="{{ route('dashboard') }}" class="btn btn-link">Voltar ao painel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scriptEnd')
<script>
(function () {
    const STATUS_URL = @json($statusUrl);
    const icon   = document.getElementById('retorno-icon');
    const titulo = document.getElementById('retorno-titulo');
    const msg    = document.getElementById('retorno-msg');
    const ok     = document.getElementById('retorno-ok');
    const wait   = document.getElementById('retorno-wait');

    function confirmado() {
        icon.textContent = '✅';
        titulo.textContent = 'Pagamento confirmado!';
        titulo.className = 'mb-2 text-success';
        msg.textContent = 'Seu pagamento foi confirmado.';
        wait.classList.add('d-none');
        ok.classList.remove('d-none');
    }

    @if($ordem->status === 'approved')
        confirmado();
    @else
        let tentativas = 0;
        const MAX = 22; // ~90s a cada 4s
        const timer = setInterval(function () {
            tentativas++;
            fetch(STATUS_URL, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.paid) {
                        clearInterval(timer);
                        confirmado();
                    }
                })
                .catch(function () { /* ignora — tenta de novo */ });

            if (tentativas >= MAX) {
                clearInterval(timer);
                icon.textContent = '⏳';
                titulo.textContent = 'Ainda aguardando confirmação';
                titulo.className = 'mb-2 text-warning';
                msg.textContent = 'Seu pagamento ainda está sendo processado. Assim que for confirmado avisaremos por WhatsApp. Você pode fechar esta página.';
                wait.classList.add('d-none');
            }
        }, 4000);
    @endif
})();
</script>
@endsection
