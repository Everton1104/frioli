@extends('layouts.app')
@section('title', 'Agendar')
@section('main')
<div class="container py-4" style="max-width: 720px">
    <h2 class="mb-1" style="color: var(--marrom)">Agendar horário</h2>
    <p class="text-secondary mb-4">Escolha o serviço, o dia e um horário livre. Depois você paga e a barbearia confirma.</p>

    @if (session('status'))
        <div class="alert alert-info py-2">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger py-2">{{ implode(' · ', $errors->all()) }}</div>
    @endif

    @if ($servicos->isEmpty())
        <div class="alert alert-warning">Nenhum serviço disponível para agendamento online no momento. Volte em breve.</div>
    @else
        <p class="fw-semibold mb-2">1. Serviço</p>
        <div class="row g-2 mb-4">
            @foreach($servicos as $s)
                <div class="col-md-6">
                    <label class="d-block">
                        <input type="radio" name="op_servico" value="{{ $s->id }}" class="op-servico d-none">
                        <div class="card servico-opt h-100">
                            <div class="card-body">
                                <div class="fw-semibold">{{ $s->descricao }}</div>
                                <div class="small text-secondary">{{ substr($s->duracao, 0, 5) }} · R$ {{ number_format($s->valor, 2, ',', '.') }}</div>
                            </div>
                        </div>
                    </label>
                </div>
            @endforeach
        </div>

        <p class="fw-semibold mb-2">2. Dia</p>
        <input type="date" id="data" class="form-control mb-4" min="{{ date('Y-m-d') }}">

        <p class="fw-semibold mb-2">3. Horário</p>
        <div id="slots" class="d-flex flex-wrap gap-2 mb-4">
            <span class="text-secondary small">Selecione um serviço e um dia.</span>
        </div>

        <form method="POST" action="{{ route('agendar.reservar') }}" id="form-reservar">
            @csrf
            <input type="hidden" name="servico_id" id="f-servico">
            <input type="hidden" name="data_inicio" id="f-data">
            <button type="submit" class="btn btn-primary px-4" id="btn-reservar" disabled>Reservar e pagar</button>
            <a href="{{ url('/') }}" class="btn btn-link">Voltar</a>
        </form>
    @endif
</div>

<style>
.servico-opt { transition: .15s; border: 2px solid transparent; cursor: pointer; }
.op-servico:checked + .servico-opt { border-color: var(--marrom); background: var(--branco); }
.slot.active { background-color: var(--marrom); border-color: var(--marrom); color: #fff; }
</style>

<script>
let servicoId = null, dataSel = null, slotSel = null;

document.querySelectorAll('.op-servico').forEach(r => r.addEventListener('change', e => {
    servicoId = e.target.value;
    document.getElementById('f-servico').value = servicoId;
    carregarSlots();
}));

const elData = document.getElementById('data');
elData.addEventListener('change', e => { dataSel = e.target.value; carregarSlots(); });

async function carregarSlots() {
    const box = document.getElementById('slots');
    if (!servicoId || !dataSel) return;
    box.innerHTML = '<span class="text-secondary small">Carregando...</span>';
    slotSel = null; atualizarBtn();
    document.getElementById('f-data').value = '';
    try {
        const res = await fetch(`/api/horarios/${dataSel}?servico_id=${servicoId}`, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();
        if (!Array.isArray(json)) {
            box.innerHTML = `<span class="text-secondary small">${json.error || 'Indisponível'}</span>`;
            return;
        }
        const livres = json.filter(h => !h.ocupado);
        if (!livres.length) {
            box.innerHTML = '<span class="text-secondary small">Nenhum horário livre neste dia.</span>';
            return;
        }
        box.innerHTML = livres.map(h =>
            `<button type="button" class="btn btn-outline-secondary btn-sm slot" data-hora="${h.hora}">${h.hora}</button>`
        ).join('');
        box.querySelectorAll('.slot').forEach(b => b.addEventListener('click', () => {
            box.querySelectorAll('.slot').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
            slotSel = b.dataset.hora;
            document.getElementById('f-data').value = `${dataSel} ${slotSel}`;
            atualizarBtn();
        }));
    } catch {
        box.innerHTML = '<span class="text-danger small">Erro ao carregar horários.</span>';
    }
}

function atualizarBtn() {
    document.getElementById('btn-reservar').disabled = !(servicoId && slotSel);
}
</script>
@endsection
