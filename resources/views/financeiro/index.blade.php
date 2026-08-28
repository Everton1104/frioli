@extends('layouts.app')
@section('title', 'Financeiro — Repasses')
@section('main')
<div class="container py-4">
    <h2 class="mb-1" style="color: var(--marrom);">Financeiro — Repasses Semanais</h2>
    <p class="text-secondary mb-3">Controle dos repasses aos barbeiros por semana. Repasse = valor do atendimento × % do serviço.</p>

    {{-- Modal: Nova ordem de pagamento --}}
    <x-app.modal id="modal-add-ordem" title="Nova ordem de pagamento" :btn="[['lbl' => 'Criar ordem', 'color' => 'primary', 'onclick' => '$(\'#form-add-ordem\').submit()']]">
        <form method="POST" id="form-add-ordem" action="{{ route('ordens.store') }}" novalidate>
            @csrf
            @method('post')
            <div class="mb-3">
                <label for="ordem_user_id" class="form-label">Cliente</label>
                <select name="user_id" id="ordem_user_id" class="form-select {{ $errors->has('user_id') ? 'is-invalid' : '' }}" required>
                    <option value="">Selecione...</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id }}" @selected(old('user_id') == $cliente->id)>{{ $cliente->name }}</option>
                    @endforeach
                </select>
                @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <x-app.input label="Descrição" type="text" name="descricao" id="ordem_descricao" required="true">
                Ex.: Pacote 5 cortes
            </x-app.input>
            <div class="mb-3">
                <label for="ordem_valor" class="form-label">Valor (R$)</label>
                <input type="number" step="0.01" min="0.01" name="valor" id="ordem_valor"
                       data-taxa="{{ config('services.infinitepay.taxa_credito') }}"
                       class="form-control {{ $errors->has('valor') ? 'is-invalid' : '' }}"
                       value="{{ old('valor') }}" placeholder="3500.00" required>
                @error('valor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div id="ordem_liquido" class="form-text" style="display:none;">
                    <span class="text-muted">Taxa InfinitePay (~{{ number_format(config('services.infinitepay.taxa_credito'), 2, ',', '.') }}%):</span>
                    <span id="ordem_liquido_taxa" class="text-danger fw-semibold">-R$ 0,00</span>
                    <span class="mx-1 text-muted">•</span>
                    <span class="text-muted">Você recebe:</span>
                    <span id="ordem_liquido_valor" class="text-success fw-semibold">R$ 0,00</span>
                </div>
            </div>
            <div class="form-text">@if((int) (\App\Models\OrdemPagamento::MAX_PARCELAS) > 1) O cliente poderá pagar em até <strong>{{ \App\Models\OrdemPagamento::MAX_PARCELAS }}x</strong>. @else Pagamento à vista — Pix, débito ou crédito. Sem parcelamento. @endif</div>
        </form>
    </x-app.modal>

    {{-- Seletor de semana --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a href="{{ route('financeiro.index', ['inicio' => $semanaAnterior]) }}" class="btn btn-sm btn-outline-light" style="background-color: var(--marrom); color:#1a1410">← Semana anterior</a>
        <span class="fw-semibold mx-2">
            {{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }}
        </span>
        @if(!$eSemanaAtual)
            <a href="{{ route('financeiro.index') }}" class="btn btn-sm btn-outline-light">Semana atual</a>
        @endif
        <a href="{{ route('financeiro.index', ['inicio' => $semanaProxima]) }}" class="btn btn-sm btn-outline-light" style="background-color: var(--marrom); color:#1a1410">Próxima semana →</a>
    </div>

    @if($algumSemPercentual)
        <div class="alert alert-warning py-2">
            ⚠️ Há atendimentos cujo serviço ainda não tem % de repasse configurada (repasse R$ 0,00). Defina a % no cadastro do serviço para que entrem no cálculo.
        </div>
    @endif

    {{-- Resumo geral --}}
    <div class="card shadow mb-4">
        <div class="card-header fw-bold">Resumo da semana</div>
        <div class="card-body">
            <div class="row text-center g-3">
                <div class="col">
                    <div class="small text-secondary">Total faturado</div>
                    <div class="fs-4 fw-bold" style="color: var(--marrom);">R$ {{ number_format($totalGeralFaturado, 2, ',', '.') }}</div>
                </div>
                <div class="col border-start">
                    <div class="small text-secondary">Total a repassar (barbeiros)</div>
                    <div class="fs-4 fw-bold">R$ {{ number_format($totalGeralRepasse, 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Um card por barbeiro --}}
    @foreach($barbeiros as $barbeiro)
        @php $fin = $barbeiro->financas; @endphp
        <div class="card shadow my-3">
            <div class="card-header d-flex justify-content-between align-items-center fw-bold">
                <span>{{ $barbeiro->name }}</span>
                <span class="small fw-normal text-secondary">
                    {{ $fin['quantidade'] }} atendimento(s) ·
                    Repasse: <strong style="color: var(--marrom);">R$ {{ number_format($fin['totalRepasse'], 2, ',', '.') }}</strong>
                </span>
            </div>
            <div class="card-body p-0">
                @if(empty($fin['linhas']))
                    <p class="text-secondary m-3">Sem atendimentos nesta semana.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th scope="col">Data</th>
                                    <th scope="col">Cliente</th>
                                    <th scope="col">Serviço</th>
                                    <th scope="col" class="text-center">Presença</th>
                                    <th scope="col" class="text-end">Valor</th>
                                    <th scope="col" class="text-center">%</th>
                                    <th scope="col" class="text-end">Repasse</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($fin['linhas'] as $linha)
                                    <tr>
                                        <td>{{ $linha['data']->format('d/m H:i') }}</td>
                                        <td>{{ $linha['cliente'] }}</td>
                                        <td>
                                            {{ $linha['servico'] }}
                                            @if($linha['e_plano'])<span class="badge bg-secondary ms-1">Mensal</span>@endif
                                        </td>
                                        <td class="text-center text-nowrap">
                                            @if($linha['compareceu'] === true)
                                                <span class="badge bg-success">✓ Compareceu</span>
                                            @elseif($linha['compareceu'] === false)
                                                <span class="badge" style="background:#b45309" title="Cliente faltou, mas a visita foi paga — o repasse ao barbeiro é mantido">✗ Falta · pago</span>
                                            @else
                                                <button class="btn btn-sm btn-outline-success py-0 px-2" title="Compareceu"
                                                        onclick="marcarPresenca({{ $linha['id'] }}, true)">✓</button>
                                                <button class="btn btn-sm btn-outline-danger py-0 px-2"
                                                        title="Não compareceu — se a visita foi paga o repasse se mantém; pagar-no-local sai do repasse. Penaliza o cliente."
                                                        onclick="marcarPresenca({{ $linha['id'] }}, false)">✗</button>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if($linha['indeterminado'])
                                                <span class="text-warning" title="Valor do pacote indeterminado">—</span>
                                            @else
                                                R$ {{ number_format($linha['base'], 2, ',', '.') }}
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($linha['percentual'] > 0)
                                                {{ rtrim(rtrim(number_format($linha['percentual'], 2, ',', '.'), '0'), ',') }}%
                                            @else
                                                <span class="text-warning" title="Configure a % no serviço">sem %</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">R$ {{ number_format($linha['repasse'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td colspan="4" class="text-end">Total</td>
                                    <td class="text-end">R$ {{ number_format($fin['totalFaturado'], 2, ',', '.') }}</td>
                                    <td></td>
                                    <td class="text-end" style="color: var(--marrom);">R$ {{ number_format($fin['totalRepasse'], 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    {{-- Ordens de Pagamento (avulsas e de plano mensal) — migrou do dashboard --}}
    <div class="card shadow my-3">
        <div class="card-header d-flex justify-content-between align-items-center"
             data-bs-toggle="collapse" data-bs-target="#collapseOrdens" style="cursor:pointer">
            <span>Ordens de Pagamento</span>
            <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#modal-add-ordem" style="background-color: var(--marrom)" onclick="event.stopPropagation();">Nova ordem</button>
        </div>
        <div id="collapseOrdens" class="collapse show">
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Descrição</th>
                                <th>Valor</th>
                                <th>Valor recebido</th>
                                <th>Parcelas</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ordensPagamento as $ordem)
                                @php [$rotulo, $cls] = $ordem->statusBadge(); @endphp
                                <tr>
                                    <td>{{ $ordem->user?->name }}</td>
                                    <td>{{ $ordem->descricao }}</td>
                                    <td>R$ {{ number_format($ordem->valor, 2, ',', '.') }}</td>
                                    <td>
                                        @if(in_array($ordem->status, ['approved', 'pending', 'aberta']))
                                            @php $liq = $ordem->valorLiquido(); @endphp
                                            @if($ordem->liquidoEstimado())
                                                <span title="Estimativa (taxa 6x) — o valor real será registrado no pagamento" style="cursor: help">~R$ {{ number_format($liq, 2, ',', '.') }}</span>
                                            @else
                                                R$ {{ number_format($liq, 2, ',', '.') }}
                                            @endif
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td>@if($ordem->installments) {{ $ordem->installments }}x @elseif($ordem->max_parcelas > 1) até {{ $ordem->max_parcelas }}x @else À vista @endif</td>
                                    <td><span class="badge {{ $cls }}">{{ $rotulo }}</span></td>
                                    <td>
                                        @if ($ordem->status === 'aberta')
                                            <form method="POST" action="{{ route('ordens.cancelar', $ordem->id) }}" class="d-inline" onsubmit="return confirm('Cancelar esta ordem?')">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-warning">Cancelar</button>
                                            </form>
                                        @endif
                                        @if ($ordem->status !== 'approved')
                                            <form method="POST" action="{{ route('ordens.destroy', $ordem->id) }}" class="d-inline" onsubmit="return confirm('Excluir DEFINITIVAMENTE esta ordem? Não poderá ser desfeito.')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger">Excluir</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-muted text-center">Nenhuma ordem criada.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <p class="text-secondary small mt-3">
        Contam os atendimentos que ocorreram (status confirmado ou pago, horário já passado), excluindo cancelados, recusados e faltas marcadas.
        Visitas de plano mensal usam o preço com desconto do pacote. Valores refletem sempre o preço/% atuais do serviço.
    </p>
</div>

<script>
    // Marca comparecimento direto da tela de financeiro (reaproveita o endpoint da agenda).
    // ✓ Compareceu: confirma presença. ✗ Não compareceu: penaliza o cliente; o
    // repasse se mantém se a visita foi paga (online/plano/pacote) e só sai se
    // era "pagar no local" — o recálculo acontece ao recarregar.
    function marcarPresenca(id, compareceu) {
        if (!compareceu && !confirm('Marcar como NÃO compareceu?\n\nO cliente fica penalizado (sem agendar até remoção manual). O repasse ao barbeiro se mantém se a visita foi paga; só "pagar no local" sai do repasse.')) {
            return;
        }
        axios.post('{{ url('/') }}/agenda/' + id + '/comparecimento', { compareceu: compareceu })
            .then(() => location.reload()) // recarrega para recalcular o repasse
            .catch(() => alert('Erro ao registrar comparecimento.'));
    }

    // Estimativa de valor líquido (taxa InfinitePay) no modal de Nova ordem.
    (function () {
        const input   = document.getElementById('ordem_valor');
        const box     = document.getElementById('ordem_liquido');
        const elTaxa  = document.getElementById('ordem_liquido_taxa');
        const elValor = document.getElementById('ordem_liquido_valor');
        if (!input || !box) return;

        const taxa = parseFloat(input.dataset.taxa) || 0;
        const fmtBRL = (n) => 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const recalcular = () => {
            const valor = parseFloat(input.value);
            if (!valor || valor <= 0 || !taxa) { box.style.display = 'none'; return; }
            const desconto = valor * (taxa / 100);
            elTaxa.textContent  = '-' + fmtBRL(desconto);
            elValor.textContent = fmtBRL(valor - desconto);
            box.style.display = 'block';
        };

        input.addEventListener('input', recalcular);
        recalcular();
    })();
</script>
@endsection
