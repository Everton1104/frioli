@extends('layouts.app')

@section('title', 'Pagar ordem')

@section('main')
<div class="container mb-5">
    <div class="my-3">
        <p class="fs-4">Pagamento</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            {{-- Resumo da ordem --}}
            <div class="card shadow mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-2">{{ $ordem->descricao }}</h5>
                    <p class="fs-5 mb-1">Valor: <strong>R$ {{ number_format($ordem->valor, 2, ',', '.') }}</strong></p>
                    @php $semJuros = min((int) $ordem->max_parcelas, \App\Models\OrdemPagamento::MAX_SEM_JUROS); @endphp
                    <p class="text-muted small mb-0">
                        @if((int) $ordem->max_parcelas > 1)
                            Em até <strong>{{ $ordem->max_parcelas }}x</strong>, sendo {{ $semJuros }}x sem juros (você escolhe no pagamento).
                        @else
                            À vista.
                        @endif
                    </p>
                </div>
            </div>

            {{-- Ação: gerar o link e ir ao checkout da InfinitePay --}}
            <div class="card shadow">
                <div class="card-body text-center">
                    <p class="mb-3 text-muted">Você será direcionado ao ambiente seguro de pagamento para finalizar com cartão (à vista ou parcelado) ou Pix.</p>
                    <button id="btn-pagar" class="btn btn-primary btn-lg w-100">Ir para o pagamento</button>
                    <div id="pagar-erro" class="alert alert-danger mt-3 d-none" role="alert"></div>
                </div>
            </div>

            <div class="text-center mt-3">
                <a href="{{ route('dashboard') }}" class="btn btn-link">&larr; Voltar</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scriptEnd')
<script>
(function () {
    const LINK_URL = @json(route('pagamentos.link', $ordem));
    const CSRF     = @json(csrf_token());
    const btn      = document.getElementById('btn-pagar');
    const erro     = document.getElementById('pagar-erro');

    btn.addEventListener('click', function () {
        btn.disabled = true;
        const original = btn.textContent;
        btn.textContent = 'Preparando pagamento...';
        erro.classList.add('d-none');

        fetch(LINK_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({}),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.erro) {
                throw new Error(data.msg || 'Não foi possível gerar o link de pagamento.');
            }
            if (data && data.url) {
                window.location.href = data.url;
                return;
            }
            throw new Error('Resposta inválida do servidor.');
        })
        .catch(function (e) {
            btn.disabled = false;
            btn.textContent = original;
            erro.textContent = e.message || 'Falha de comunicação. Tente novamente.';
            erro.classList.remove('d-none');
        });
    });
})();
</script>
@endsection
