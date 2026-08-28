@extends("layouts.app")
@section("title", "Histórico de atendimentos")
@section('style')
    <style>
        .card-header { background-color: var(--branco); }
        /* Mesmo visual dos cards da agenda (dashboard) */
        .consulta-card {
            border-radius: 12px;
            border-left: 6px solid #28a745;
            background: #1d1810;
            border-top: 1px solid #3a3127;
            border-right: 1px solid #3a3127;
            border-bottom: 1px solid #3a3127;
            padding: 16px;
            margin-bottom: 16px;
        }
        .consulta-mensal { border-left-color: #0d6efd; }
        .consulta-avulso { border-left-color: #6f42c6; }
        .consulta-faltou { border-left-color: #dc3545; }
        .consulta-data { font-weight: bold; font-size: 1.1rem; color: #3ddc84; }
        .consulta-hora { font-size: 0.95rem; color: #b6a98e; }
        .consulta-info { font-size: 1rem; }
    </style>
@endsection
@section("main")
<div class="container mb-5">
    <div class="my-3 d-flex flex-wrap align-items-center gap-2">
        <h3 class="mb-0">Histórico de atendimentos</h3>
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary ms-auto">← Voltar ao painel</a>
    </div>
    <p class="text-muted small">Cortes já realizados — incluindo os de pacotes e planos consumidos.</p>

    {{-- Filtros --}}
    <div class="card shadow my-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('historico.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="f_cliente">Cliente</label>
                    <input type="text" id="f_cliente" name="cliente" class="form-control form-control-sm"
                           placeholder="Nome (ou parte)" value="{{ $filtros['cliente'] }}">
                </div>
                @if(auth()->user()->adm)
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="f_barbeiro">Barbeiro</label>
                    <select id="f_barbeiro" name="barbeiro" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach($barbeiros as $b)
                            <option value="{{ $b->id }}" @selected((string) $filtros['barbeiro'] === (string) $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="f_tipo">Tipo</label>
                    <select id="f_tipo" name="tipo" class="form-select form-select-sm">
                        <option value="todos" @selected($filtros['tipo'] === 'todos')>Todos</option>
                        <option value="mensal" @selected($filtros['tipo'] === 'mensal')>Plano mensal</option>
                        <option value="avulso" @selected($filtros['tipo'] === 'avulso')>Pacote avulso</option>
                        <option value="sem" @selected($filtros['tipo'] === 'sem')>Sem pacote</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Período</label>
                    <div class="d-flex flex-wrap gap-2 small">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="periodo" id="p_30d" value="30d" @checked($filtros['periodo'] === '30d')>
                            <label class="form-check-label" for="p_30d">Últimos 30 dias</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="periodo" id="p_semana" value="semana" @checked($filtros['periodo'] === 'semana')>
                            <label class="form-check-label" for="p_semana">Semana de</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="periodo" id="p_dia" value="dia" @checked($filtros['periodo'] === 'dia')>
                            <label class="form-check-label" for="p_dia">Dia</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="periodo" id="p_todos" value="todos" @checked($filtros['periodo'] === 'todos')>
                            <label class="form-check-label" for="p_todos">Tudo</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="f_data">Data (semana/dia)</label>
                    <input type="date" id="f_data" name="data" class="form-control form-control-sm" value="{{ $filtros['data'] }}">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary px-4">Filtrar</button>
                    <a href="{{ route('historico.index') }}" class="btn btn-sm btn-outline-secondary">Limpar</a>
                    <span class="ms-auto small text-muted align-self-center">{{ $atendimentos->total() }} atendimento(s)</span>
                </div>
            </form>
        </div>
    </div>

    {{-- Atendimentos agrupados por mês → dia (mesmo padrão da agenda) --}}
    @php
        $agrupados = $atendimentos->getCollection()->groupBy([
            fn($a) => $a->data_inicio->locale('pt_BR')->translatedFormat('F Y'),
            fn($a) => $a->data_inicio->format('Y-m-d'),
        ]);
        $i = 0;
    @endphp
    @if($agrupados->isEmpty())
        <div class="alert alert-info mt-3">Nenhum atendimento encontrado com esses filtros.</div>
    @else
    <div class="accordion shadow" id="accordionHistorico">
        @foreach($agrupados as $mes => $diasDoMes)
            @php $idMes = 'hist-'.\Illuminate\Support\Str::slug($mes); @endphp
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button {{ $i > 0 ? 'collapsed' : '' }}" type="button"
                            data-bs-toggle="collapse" data-bs-target="#{{ $idMes }}">
                        {{ ucfirst($mes) }}
                    </button>
                </h2>
                <div id="{{ $idMes }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}" data-bs-parent="#accordionHistorico">
                    <div class="accordion-body">
                        @foreach($diasDoMes as $dataKey => $listaDia)
                            @php
                                $diaLabel = \Carbon\Carbon::parse($dataKey)->locale('pt_BR')->translatedFormat('l, d \d\e F');
                            @endphp
                            <div class="mb-2">
                                <button class="btn btn-sm w-100 text-start d-flex align-items-center gap-2 btn-outline-secondary"
                                        data-bs-toggle="collapse" data-bs-target="#{{ 'd'.str_replace('-','',$dataKey) }}">
                                    <span>{{ ucfirst($diaLabel) }}</span>
                                    <span class="badge bg-secondary ms-auto">{{ $listaDia->count() }}</span>
                                </button>
                                <div id="{{ 'd'.str_replace('-','',$dataKey) }}" class="collapse pt-2">
                                    @foreach($listaDia as $a)
                                        @php
                                            $classe = $a->plano_mensal_id ? 'consulta-mensal'
                                                : ($a->credito_servico_id ? 'consulta-avulso' : '');
                                            if ($a->compareceu === false) $classe = 'consulta-faltou';
                                        @endphp
                                        <div class="consulta-card {{ $classe }}">
                                            <div class="d-flex flex-wrap align-items-center">
                                                <div class="me-3 text-center">
                                                    <div class="consulta-hora">
                                                        {{ $a->data_inicio->format('H:i') }} <br>às<br>
                                                        {{ $a->data_fim->format('H:i') }}
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    @if($a->plano_mensal_id)<span class="badge bg-primary mb-1">📅 Mensal</span>@endif
                                                    @if($a->credito_servico_id && !$a->plano_mensal_id)<span class="badge mb-1" style="background:#6f42c6">🧾 Pacote avulso</span>@endif
                                                    @if($a->compareceu === true)<span class="badge bg-success mb-1">✓ Compareceu</span>
                                                    @elseif($a->compareceu === false)<span class="badge bg-danger mb-1">✗ Não compareceu</span>@endif
                                                    @if($a->pagar_no_local)<span class="badge bg-info text-dark mb-1">Pagar no local</span>@endif
                                                    <br>
                                                    <strong>Cliente:</strong> {{ $a->user->name ?? '—' }}
                                                    @if($a->funcionario)<br><strong>Barbeiro:</strong> {{ $a->funcionario->name }}@endif
                                                    <br>
                                                    <strong>Serviço:</strong> {{ $a->servico_display }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @php $i++; @endphp
        @endforeach
    </div>
    <div class="d-flex justify-content-center mt-3">
        {{ $atendimentos->links() }}
    </div>
    @endif
</div>
@endsection
