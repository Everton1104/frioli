@extends("layouts.app")
@section("title", "Agendar")
@section("main")
<div class="container py-4" style="max-width: 720px">
    <h2 class="mb-1" style="color: var(--marrom)">Agendar horário</h2>
    <p class="text-secondary mb-4">Escolha o barbeiro, o serviço, o dia e um horário livre.</p>

    @if (session("status"))
        <div class="alert alert-info py-2">{{ session("status") }}</div>
    @endif
    @if (session("msg"))
        <div class="alert alert-success py-2">{{ session("msg") }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger py-2">{{ implode(" · ", $errors->all()) }}</div>
    @endif

    @if ($penalizado)
        {{-- Penalizado: não pode agendar online até adm/func remover a pendência --}}
        <div class="alert alert-warning">
            Você está com uma pendência e não pode agendar online no momento.
            Entre em contato com a barbearia para regularizar:
            <a class="btn btn-sm btn-agendar ms-2" target="_blank" rel="noopener"
               href="https://wa.me/{{ $whatsappAdmin }}?text={{ urlencode('Olá! Preciso regularizar minha pendência para voltar a agendar.') }}">Falar no WhatsApp</a>
        </div>
    @elseif ($servicos->isEmpty())
        <div class="alert alert-warning">Nenhum serviço disponível para agendamento online no momento. Volte em breve.</div>
    @elseif ($barbeiros->isEmpty())
        <div class="alert alert-warning">Nenhum barbeiro disponível para agendamento no momento. Volte em breve.</div>
    @else
        <p class="fw-semibold mb-2">1. Barbeiro</p>
        <div class="row g-2 mb-4">
            @foreach($barbeiros as $b)
                <div class="col-md-6">
                    <label class="d-block">
                        <input type="radio" name="op_barbeiro" value="{{ $b->id }}" class="op-barbeiro d-none">
                        <div class="card barbeiro-opt h-100">
                            <div class="card-body"><div class="fw-semibold">{{ $b->name }}</div></div>
                        </div>
                    </label>
                </div>
            @endforeach
        </div>

        <p class="fw-semibold mb-2">2. Serviço</p>
        <div class="row g-2 mb-4">
            @foreach($servicos as $s)
                <div class="col-md-6">
                    <label class="d-block">
                        <input type="radio" name="op_servico" value="{{ $s->id }}" class="op-servico d-none">
                        <div class="card servico-opt h-100">
                            <div class="card-body">
                                <div class="fw-semibold">{{ $s->descricao }}</div>
                                <div class="small text-secondary">{{ substr($s->duracao, 0, 5) }} · R$ {{ number_format($s->valor, 2, ",", ".") }}</div>
                            </div>
                        </div>
                    </label>
                </div>
            @endforeach
        </div>

        <p class="fw-semibold mb-2">3. Dia</p>
        <input type="date" id="data" class="form-control mb-4" min="{{ date("Y-m-d") }}">

        <p class="fw-semibold mb-2">4. Horário</p>
        <div id="slots" class="d-flex flex-wrap gap-2 mb-4">
            <span class="text-secondary small">Selecione um barbeiro, um serviço e um dia.</span>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="pagar_no_local" value="1">
            <label class="form-check-label" for="pagar_no_local">Pagar no local (na barbearia, no dia)</label>
        </div>

        @if($recaptchaSiteKey)
        <div class="mb-3">
            <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        </div>
        @endif

        <form method="POST" action="{{ route("agendar.reservar") }}" id="form-reservar">
            @csrf
            <input type="hidden" name="funcionario_id" id="f-barbeiro">
            <input type="hidden" name="servico_id" id="f-servico">
            <input type="hidden" name="data_inicio" id="f-data">
            <input type="hidden" name="pagar_no_local" id="f-pagar-local" value="0">
            <button type="submit" class="btn btn-primary px-4" id="btn-reservar" disabled>Reservar e pagar</button>
            <a href="{{ url("/") }}" class="btn btn-link">Voltar</a>
        </form>
    @endif
</div>

<style>
.barbeiro-opt, .servico-opt { transition: .15s; border: 2px solid transparent; cursor: pointer; }
.op-barbeiro:checked + .barbeiro-opt, .op-servico:checked + .servico-opt { border-color: var(--marrom); background: var(--branco); }
.slot.active { background-color: var(--marrom); border-color: var(--marrom); color: #fff; }
</style>

<script>
let barbeiroId = null, servicoId = null, dataSel = null, slotSel = null;

document.querySelectorAll(".op-barbeiro").forEach(r => r.addEventListener("change", e => {
    barbeiroId = e.target.value;
    document.getElementById("f-barbeiro").value = barbeiroId;
    carregarSlots();
}));
document.querySelectorAll(".op-servico").forEach(r => r.addEventListener("change", e => {
    servicoId = e.target.value;
    document.getElementById("f-servico").value = servicoId;
    carregarSlots();
}));

const elData = document.getElementById("data");
elData.addEventListener("change", e => { dataSel = e.target.value; carregarSlots(); });

const chkLocal = document.getElementById("pagar_no_local");
chkLocal.addEventListener("change", () => {
    document.getElementById("f-pagar-local").value = chkLocal.checked ? 1 : 0;
    document.getElementById("btn-reservar").textContent = chkLocal.checked ? "Reservar (pagar no local)" : "Reservar e pagar";
});

async function carregarSlots() {
    const box = document.getElementById("slots");
    if (!barbeiroId || !servicoId || !dataSel) return;
    box.innerHTML = '<span class="text-secondary small">Carregando...</span>';
    slotSel = null; atualizarBtn();
    document.getElementById("f-data").value = "";
    try {
        const res = await fetch(`/api/horarios/${dataSel}?servico_id=${servicoId}&funcionario_id=${barbeiroId}`, { headers: { "Accept": "application/json" } });
        const json = await res.json();
        if (!Array.isArray(json)) {
            box.innerHTML = `<span class="text-secondary small">${json.error || "Indisponível"}</span>`;
            return;
        }
        const livres = json.filter(h => !h.ocupado);
        if (!livres.length) {
            box.innerHTML = '<span class="text-secondary small">Nenhum horário livre neste dia.</span>';
            return;
        }
        box.innerHTML = livres.map(h =>
            `<button type="button" class="btn btn-outline-secondary btn-sm slot" data-hora="${h.hora}">${h.hora}</button>`
        ).join("");
        box.querySelectorAll(".slot").forEach(b => b.addEventListener("click", () => {
            box.querySelectorAll(".slot").forEach(x => x.classList.remove("active"));
            b.classList.add("active");
            slotSel = b.dataset.hora;
            document.getElementById("f-data").value = `${dataSel} ${slotSel}`;
            atualizarBtn();
        }));
    } catch {
        box.innerHTML = '<span class="text-danger small">Erro ao carregar horários.</span>';
    }
}

function atualizarBtn() {
    document.getElementById("btn-reservar").disabled = !(barbeiroId && servicoId && slotSel);
}
</script>
@endsection
