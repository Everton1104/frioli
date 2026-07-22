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

    @if ($servicos->isEmpty())
        <div class="alert alert-warning">Nenhum serviço disponível para agendamento online no momento. Volte em breve.</div>
    @elseif ($barbeiros->isEmpty())
        <div class="alert alert-warning">Nenhum barbeiro disponível para agendamento no momento. Volte em breve.</div>
    @else
        @if ($penalizado)
        {{-- Penalizado: pagamento online liberado; pagar no local vira intenção (aprovação do barbeiro) --}}
        <div class="alert alert-info">
            Você está com uma pendência. O <strong>pagamento online</strong> está liberado normalmente.
            Se marcar <strong>pagar no local</strong>, seu pedido fica <strong>aguardando aprovação do barbeiro</strong> — você será avisado quando ele responder.
        </div>
        @endif
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

        <form method="POST" action="{{ route("agendar.reservar") }}" id="form-reservar">
            @csrf
            <input type="hidden" name="funcionario_id" id="f-barbeiro" value="{{ old('funcionario_id') }}">
            <input type="hidden" name="servico_id" id="f-servico" value="{{ old('servico_id') }}">
            <input type="hidden" name="data_inicio" id="f-data" value="{{ old('data_inicio') }}">
            <input type="hidden" name="pagar_no_local" id="f-pagar-local" value="{{ old('pagar_no_local', 0) }}">
            @if($recaptchaSiteKey)
            <div class="mb-3">
                <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            </div>
            @endif
            <button type="submit" class="btn btn-primary px-4" id="btn-reservar" disabled>Reservar e pagar</button>
            <a href="{{ url("/") }}" class="btn btn-link">Voltar</a>
        </form>
    @endif

    @php
        $podeMensal = $indicado && $servicosMensais->isNotEmpty() && $barbeiros->isNotEmpty();
    @endphp
    @if ($podeMensal)
    <div class="card shadow-sm mt-4" style="border-color: var(--marrom)">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="fw-semibold" style="color: var(--marrom)">Plano mensal (horário fixo)</div>
                <div class="small text-secondary">Garanta o mesmo horário toda semana. Clique para comprar ou renovar o seu plano.</div>
            </div>
            <button class="btn btn-sm btn-agendar" data-bs-toggle="modal" data-bs-target="#modal-mensal">Comprar / renovar plano</button>
        </div>
    </div>

    <div class="modal fade" id="modal-mensal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route("agendar.mensal") }}" id="form-mensal">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Plano mensal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Serviço</label>
                            <select name="servico_id" id="m_servico" class="form-select" required>
                                <option value="">Selecione...</option>
                                @foreach ($servicosMensais as $s)
                                    <option value="{{ $s->id }}">{{ $s->descricao }} — R$ {{ number_format($s->valor, 2, ",", ".") }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Barbeiro</label>
                            <select name="funcionario_id" id="m_barbeiro" class="form-select" required>
                                <option value="">Selecione...</option>
                                @foreach ($barbeiros as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Dia da semana</label>
                                <select name="dia_semana" id="m_dia" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    @foreach (["Domingo","Segunda","Terça","Quarta","Quinta","Sexta","Sábado"] as $i => $d)
                                        <option value="{{ $i }}">{{ $d }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Horário</label>
                                <input type="time" name="hora" id="m_hora" class="form-control" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Mês</label>
                            <input type="month" name="mes" id="m_mes" class="form-control" value="{{ now()->copy()->startOfMonth()->addMonth()->format("Y-m") }}" required>
                        </div>
                        <div class="form-text mt-2">
                            Valor total: <strong id="m_valor">—</strong>
                            <span id="m_unidades" class="text-muted"></span>
                        </div>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" value="1" name="recorrente" id="m_recorrente">
                            <label class="form-check-label small" for="m_recorrente">
                                Renovar todo mês automaticamente (o sistema gera a cobrança do próximo mês e te avisa pagar). Não é cartão salvo.
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Ir para o pagamento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    (function () {
        const servico = document.getElementById('m_servico');
        const dia = document.getElementById('m_dia');
        const mes = document.getElementById('m_mes');
        const valor = document.getElementById('m_valor');
        const unidades = document.getElementById('m_unidades');
        if (!servico) return;
        function recalc() {
            if (!servico.value || !dia.value || !mes.value) { valor.textContent = '—'; unidades.textContent = ''; return; }
            fetch(`/api/mensal/calcular?servico_id=${servico.value}&dia_semana=${dia.value}&mes=${mes.value}`)
                .then(r => r.json()).then(d => {
                    if (d.error) { valor.textContent = '—'; unidades.textContent = ''; return; }
                    valor.textContent = 'R$ ' + Number(d.valor_total).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                    unidades.textContent = '· ' + d.unidades + 'x no mês';
                }).catch(() => { valor.textContent = '—'; unidades.textContent = ''; });
        }
        [servico, dia, mes].forEach(el => el.addEventListener('change', recalc));
    })();
    </script>
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

// Valida o reCAPTCHA no cliente antes de submeter (evita recarregar e perder os campos).
document.getElementById('form-reservar').addEventListener('submit', function (e) {
    @if($recaptchaSiteKey)
    if (typeof grecaptcha !== 'undefined' && !grecaptcha.getResponse()) {
        e.preventDefault();
        alert('Por favor, confirme o reCAPTCHA ("Não sou um robô").');
        return false;
    }
    @endif
});

// Restaura a seleção após erro de validação (withInput) — não deixa os campos em branco.
(function restaurar() {
    const oldBarbeiro = "{{ old('funcionario_id') }}";
    const oldServico = "{{ old('servico_id') }}";
    const oldData = "{{ old('data_inicio') }}";
    let oldSlot = null;
    if (oldBarbeiro) {
        barbeiroId = oldBarbeiro;
        document.getElementById('f-barbeiro').value = oldBarbeiro;
        const rb = document.querySelector('.op-barbeiro[value="' + oldBarbeiro + '"]');
        if (rb) rb.checked = true;
    }
    if (oldServico) {
        servicoId = oldServico;
        document.getElementById('f-servico').value = oldServico;
        const rs = document.querySelector('.op-servico[value="' + oldServico + '"]');
        if (rs) rs.checked = true;
    }
    if (oldData) {
        const parts = oldData.split(' ');
        if (parts[0]) { dataSel = parts[0]; elData.value = parts[0]; }
        if (parts[1]) oldSlot = parts[1].substring(0, 5);
    }
    if (barbeiroId && servicoId && dataSel) {
        carregarSlots().then(() => {
            if (oldSlot) {
                const slot = document.querySelector('.slot[data-hora="' + oldSlot + '"]');
                if (slot) {
                    slot.classList.add('active');
                    slotSel = oldSlot;
                    document.getElementById('f-data').value = dataSel + ' ' + slotSel;
                }
            }
            atualizarBtn();
        });
    } else {
        atualizarBtn();
    }
})();
</script>
@endsection
