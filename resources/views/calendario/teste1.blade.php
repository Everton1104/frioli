@extends("layouts.app")
@section("title", "Calendário — dia")
@php
    // Espaçamento fixo no modo AMPLO (190px/hora — 160 virou 190 depois dos
    // testes no mobile: blocos de 30min ganharam ~15px para caber nome quebrado
    // + botões de comparecimento sem cortar).
    $pxHora  = 190;
    $pxMin   = $pxHora / 60;
    $altura  = ($horaFim - $horaIni) * $pxHora;
    // Visão única: o DIA de ?data= (ou hoje). Navegação pelas setas, dia a dia.
    $dia = $diaRef;
    // Monta a URL preservando o filtro de barbeiro.
    $q = function (array $params) use ($barbeiroFiltro) {
        if ($barbeiroFiltro) $params['barbeiro'] = $barbeiroFiltro;
        return route('calendario1') . (count($params) ? '?' . http_build_query($params) : '');
    };
    $urlAntes = $q(['data' => $dia->copy()->subDay()->format('Y-m-d')]);
    $urlDepois= $q(['data' => $dia->copy()->addDay()->format('Y-m-d')]);
    $urlHoje  = $q([]);
    $baseBarbeiro = route('calendario1') . '?' . http_build_query(array_filter(['data' => $dia->toDateString()]));
    $dataKey  = $dia->toDateString();
@endphp
@section('style')
<style>
    .cal1-topo { background: #14100b; border-bottom: 1px solid #3a3127; }
    /* Header fixo ao rolar (controlado por JS — mais robusto que sticky, que
       qualquer overflow em ancestral neutraliza). O spacer segura o layout. */
    .cal1-topo.fixo { position: fixed; top: 0; left: 0; right: 0; z-index: 1030; padding-inline: .5rem; box-shadow: 0 2px 10px rgba(0,0,0,.5); }
    .cal1-corpo { position: relative; height: {{ $altura }}px; }
    .cal1-linha { position: absolute; left: 0; right: 0; border-top: 1px solid rgba(58,49,39,.55); font-size: .68rem; color: #8a7c63; }
    .cal1-linha span { position: absolute; top: -9px; right: 4px; }
    .cal1-aberto { position: absolute; left: 0; right: 0; background: rgba(61,220,132,.05); border-left: 2px solid rgba(61,220,132,.25); }
    .ev { position: absolute; border-radius: 6px; padding: 4px 6px; font-size: .78rem; line-height: 1.2; overflow: hidden; border: 1px solid transparent; }
    .ev b { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    /* Nome do cliente em linha própria, abaixo do horário — no mobile ele QUEBRA
       para a linha seguinte em vez de virar "Carlos M..." */
    .ev .ev-nome { display: block; font-weight: 700; white-space: normal; overflow-wrap: anywhere; }
    .ev small { opacity: .85; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ev-confirmado { background: rgba(40,167,69,.18); border-color: #28a74555; color: #d6f5e0; }
    .ev-pago       { background: rgba(255,193,7,.15); border-color: #ffc10755; color: #f8e3a1; }
    .ev-reserva    { background: rgba(220,53,69,.14); border-color: #dc354555; color: #f3c6cb; }
    .ev-especial   { background: rgba(220,53,69,.20); border-color: #dc3545; color: #ffd9dc; }
    .ev-mensal     { box-shadow: inset 4px 0 0 #0d6efd; }
    /* Evento que começa/termina fora da janela da grade (ex.: 06:00 com grade
       07–22h): gruda no topo/fundo da coluna com aparência de "continuação". */
    .ev-antes { top: 0 !important; border-radius: 0 0 6px 6px; border-top: 2px dashed currentColor; opacity: .92; }
    .ev-depois { border-radius: 6px 6px 0 0; border-bottom: 2px dashed currentColor; opacity: .92; }
    /* Linha do "agora": desce conforme o tempo passa (só no dia de hoje).
       left negativo puxa a linha para a esquerda, atravessando a régua de
       horas (52px) até a borda da grade — a bolinha nasce na régua, estilo
       Google Agenda. Transição linear de 1s = movimento contínuo. */
    .cal1-agora { position: absolute; left: -52px; right: 0; height: 2px; background: #ff5252; z-index: 3; pointer-events: none; transition: top 1s linear; }
    .cal1-agora::before { content: ''; position: absolute; left: -4px; top: -3px; width: 8px; height: 8px; border-radius: 50%; background: #ff5252; }
    .cal1-legend { font-size: .72rem; }
    /* Comparecimento direto no bloco (mesma regra/rota do dashboard) */
    .ev-acoes { display: flex; gap: 4px; margin-top: 2px; }
    .ev-acoes .btn { --bs-btn-padding-y: 0; --bs-btn-padding-x: 8px; font-size: .68rem; line-height: 1.4; }
    .ev-badge { display: inline-block; margin-top: 2px; padding: 0 6px; border-radius: 4px; font-size: .68rem; }
</style>
@endsection
@section("main")
<div class="container-fluid mb-5 px-2 pb-5">
    <div class="cal1-topo py-2 d-flex flex-wrap align-items-center gap-2" id="cal1-topo">
        <h5 class="mb-0 me-2">🗓️ Calendário</h5>
        <div class="btn-group btn-group-sm ms-2" role="group" aria-label="Navegação">
            <a class="btn btn-outline-secondary" href="{{ $urlAntes }}">◀</a>
            <a class="btn btn-outline-secondary" href="{{ $urlHoje }}">Hoje</a>
            <a class="btn btn-outline-secondary" href="{{ $urlDepois }}">▶</a>
        </div>
        <strong class="ms-1">
            {{ ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'][$dia->dayOfWeek] }}, {{ $dia->format('d/m/Y') }}
        </strong>
        @if(!$ehFunc)
        <select class="form-select form-select-sm w-auto ms-2" onchange="location = '{{ $baseBarbeiro }}' + (this.value ? '&barbeiro=' + this.value : '')">
            <option value="">Todos os barbeiros</option>
            @foreach($barbeiros as $b)
                <option value="{{ $b->id }}" @selected((int) $barbeiroFiltro === $b->id)>{{ $b->name }}</option>
            @endforeach
        </select>
        @endif
        <div class="ms-auto d-flex gap-2 cal1-legend align-items-center">
            <span><span class="d-inline-block" style="width:10px;height:10px;background:#28a74588;border-radius:2px"></span> confirmado</span>
            <span><span class="d-inline-block" style="width:10px;height:10px;background:#ffc10788;border-radius:2px"></span> aguardando</span>
            <span><span class="d-inline-block" style="width:10px;height:10px;background:#0d6efd;border-radius:2px"></span> mensal</span>
            <span>✂️ avulso · 🧾 pacote</span>
            <span><span class="d-inline-block" style="width:10px;height:10px;background:#dc354588;border-radius:2px"></span> reserva/especial</span>
            <span class="text-muted">| verde claro = horário aberto</span>
        </div>
    </div>

    <div class="card shadow my-3">
        <div class="cal1-grid" style="display: grid; grid-template-columns: 52px minmax(0, 1fr);">
            <div class="position-relative">
                @for($h = $horaIni; $h <= $horaFim; $h++)
                    <div class="cal1-linha" style="top: {{ ($h * 60 - $horaIni * 60) * $pxMin }}px"><span>{{ sprintf('%02d', $h) }}h</span></div>
                @endfor
            </div>
            @php
                $evs = $eventos->where('data', $dataKey)->values()->toArray();
                // Colunas de sobreposição (encaixe/especial lado a lado)
                $colFim = []; $maxCol = 1;
                foreach ($evs as $i => $ev) {
                    $c = 0;
                    while (isset($colFim[$c]) && $colFim[$c] > $ev['iniMin']) $c++;
                    $evs[$i]['col'] = $c;
                    $colFim[$c] = $ev['fimMin'];
                    $maxCol = max($maxCol, $c + 1);
                }
            @endphp
            <div class="cal1-corpo">
                @for($h = $horaIni; $h <= $horaFim; $h++)
                    <div class="cal1-linha" style="top: {{ ($h * 60 - $horaIni * 60) * $pxMin }}px"></div>
                @endfor
                @foreach(collect($abertos)->where('data', $dataKey) as $ab)
                    <div class="cal1-aberto" style="top: {{ ($ab['iniMin'] - $horaIni * 60) * $pxMin }}px; height: {{ max(15, ($ab['fimMin'] - $ab['iniMin']) * $pxMin) }}px"></div>
                @endforeach
                @if($dia->isToday())
                    <div class="cal1-agora" id="cal1-agora" style="display:none"></div>
                @endif
                @foreach($evs as $ev)
                    @php
                        // Clamp na janela da grade: começa antes de HORA_INI → gruda
                        // no topo (continuação); termina depois de HORA_FIM → gruda no
                        // fundo. Sem isso o bloco vazaria sobre o cabeçalho do dia.
                        $top = max(0.0, ($ev['iniMin'] - $horaIni * 60) * $pxMin);
                        $bottom = min((float) $altura, ($ev['fimMin'] - $horaIni * 60) * $pxMin);
                        $height = max(20.0, $bottom - $top - 2);
                        $extra = $ev['iniMin'] < $horaIni * 60 ? 'ev-antes'
                            : ($ev['fimMin'] > $horaFim * 60 ? 'ev-depois' : '');
                    @endphp
                    <div class="ev {{ $ev['classe'] }} {{ $extra }}"
                         style="top: {{ $top }}px;
                                height: {{ $height }}px;
                                left: calc({{ $ev['col'] * (100 / $maxCol) }}% + 2px);
                                width: calc({{ 100 / $maxCol }}% - 6px);"
                         title="{{ $ev['cliente'] }} · {{ $ev['servico'] }} · {{ $ev['barbeiro'] }} · {{ $ev['horaStr'] }}">
                        <b>{{ $ev['horaStr'] }} {{ $ev['badges'] }}</b>
                        <span class="ev-nome">{{ $ev['cliente'] }}</span>
                        <small>{{ $ev['servico'] }}</small>
                        @if(!$barbeiroFiltro && !$ehFunc)<small class="text-muted">{{ $ev['barbeiro'] }}</small>@endif
                        @if($ev['podeMarcar'])
                            <span class="ev-acoes">
                                <button type="button" class="btn btn-success" title="Compareceu"
                                        onclick="marcarComp({{ $ev['id'] }}, true)">✓</button>
                                <button type="button" class="btn btn-danger" title="Não compareceu (gera penalidade)"
                                        onclick="marcarComp({{ $ev['id'] }}, false)">✗</button>
                            </span>
                        @elseif($ev['compareceu'] === true)
                            <span class="ev-badge" style="background:#28a74533; color:#9fe0b5;">✓ Compareceu</span>
                        @elseif($ev['compareceu'] === false)
                            <span class="ev-badge" style="background:#dc354533; color:#f3b6bb;">✗ Não compareceu</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-link">← Painel</a>
    </div>
</div>

<script>
    // Header fixo ao rolar: passa de fluxo para position:fixed quando o topo da
    // página o ultrapassa (um spacer de mesma altura evita o salto de layout).
    (function () {
        const header = document.getElementById('cal1-topo');
        if (!header) return;
        const spacer = document.createElement('div');
        let ativo = false;
        let limite = null;
        function aoRolar() {
            if (limite === null) limite = header.offsetTop;
            if (!ativo && window.scrollY >= limite) {
                spacer.style.height = header.offsetHeight + 'px';
                header.parentNode.insertBefore(spacer, header);
                header.classList.add('fixo');
                ativo = true;
            } else if (ativo && window.scrollY < limite) {
                header.classList.remove('fixo');
                spacer.remove();
                ativo = false;
                limite = null; // recalcula na próxima ativação
            }
        }
        window.addEventListener('scroll', aoRolar, { passive: true });
        window.addEventListener('resize', () => { if (ativo) { limite = spacer.offsetTop; } }, { passive: true });
        aoRolar();
    })();

    // Comparecimento direto no bloco — mesma rota/regra do dashboard (no-show
    // penaliza o cliente). Recarrega para o bloco mostrar o resultado.
    // AMBOS os botões pedem confirmação (evita toque acidental no celular); o
    // "Falta" avisa da penalidade antes de confirmar.
    function marcarComp(id, compareceu) {
        const msg = compareceu
            ? 'Confirmar que o cliente COMPARECEU?'
            : 'Marcar como NÃO compareceu?\n\nO cliente fica penalizado (sem agendar até remoção manual). O repasse se mantém se a visita foi paga; só "pagar no local" sai do repasse.';
        if (!confirm(msg)) return;
        axios.post('/agenda/' + id + '/comparecimento', { compareceu })
            .then(() => location.reload())
            .catch(() => alert('Erro ao registrar comparecimento.'));
    }

    // Linha do "agora" (só no dia de hoje): posição em minutos+segundos (fração)
    // recalculada a cada segundo — com a transição CSS linear ela desliza em
    // tempo real, sem pular de minuto em minuto. Some fora da janela da grade.
    (function () {
        const el = document.getElementById('cal1-agora');
        if (!el) return;
        const INI = {{ $horaIni * 60 }}, FIM = {{ $horaFim * 60 }}, PX = {{ $pxMin }};
        function atualizar() {
            const d = new Date();
            const frac = d.getHours() * 60 + d.getMinutes() + d.getSeconds() / 60;
            if (frac < INI || frac > FIM) { el.style.display = 'none'; return; }
            el.style.display = '';
            el.style.top = ((frac - INI) * PX - 1) + 'px';
        }
        atualizar();
        setInterval(atualizar, 1000);
    })();

    // Ao abrir o dia: a página já vem posicionada na linha do tempo — na linha
    // do "agora" se for hoje (e dentro da janela), senão no topo da grade (07h).
    (function () {
        const linha = document.getElementById('cal1-agora');
        const corpo = document.querySelector('.cal1-corpo');
        const alvo  = (linha && linha.style.display !== 'none') ? linha : corpo;
        if (!alvo) return;
        const y = alvo.getBoundingClientRect().top + window.scrollY - 110;
        window.scrollTo({ top: Math.max(0, y), behavior: 'auto' });
    })();
</script>
@endsection
