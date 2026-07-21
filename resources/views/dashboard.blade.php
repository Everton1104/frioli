@extends("layouts.app")
@section("title", "Dashboard")
@section('style')
    <style>
        .card-header {
            background-color: var(--branco);
        }
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
        .consulta-pendente {
            border-left-color: #ffc107;
        }
        .consulta-especial {
            border-left-color: #dc3545;
        }
        .consulta-mensal {
            border-left-color: #0d6efd;
        }
        .consulta-data {
            font-weight: bold;
            font-size: 1.1rem;
            color: #3ddc84;
        }
        .consulta-hora {
            font-size: 0.95rem;
            color: #b6a98e;
        }
        .consulta-info {
            font-size: 1rem;
        }
        /* Responsivo: em telas menores (tablet/celular) o card de consulta empilha —
           informações em cima, botões de ação embaixo, cada um em sua própria linha,
           ficando legível e fácil de tocar. Em telas grandes continua lado a lado. */
        .consulta-card { flex-wrap: wrap; }
        @media (max-width: 991.98px) {
            .consulta-card { flex-direction: column !important; align-items: stretch !important; }
            .consulta-card > div:first-child { width: 100%; }
            .consulta-card > .btn { width: 100%; margin-left: 0 !important; margin-top: 4px; }
        }
    </style>
@endsection
@section("main")
<div class="container mb-5">
    <div class="my-3">
        <p class="fs-4">Olá, {{ ucfirst(auth()->user()->name) }}</p>
    </div>

    {{-- Seção de controle para administradores --}}
    @if(auth()->user()->adm == 1 || auth()->user()->func == 1)

        {{-- Modal Adicionar Usuários --}}
        <x-app.modal id="modal-add-usuario" title="Adicionar novo usuário" :btn="[['lbl' => 'Adicionar', 'color' => 'primary', 'onclick' => '$( \'#form-add-usuario\').submit()']]">
            <form method="POST" id="form-add-usuario" action="{{ route('add-usuario') }}" novalidate>
                @csrf
                @method('post')
                <x-app.input label="Nome" type="text" name="nome" id="nome_usuario" required="true" />
                <x-app.input label="WhatsApp" type="tel" name="whatsapp" id="whatsapp_usuario" required="true" />
                @if(auth()->user()->adm)
                    <x-app.input label="Senha" type="password" name="senha" id="senha_usuario" />
                    <x-app.input label="Confirmar Senha" type="password" name="senha_confirmation" id="senha_confirmation_usuario" />
                @endif
                @if(auth()->user()->adm)
                    <x-app.radio name="tipo"
                        :options="[
                            'adm' => 'Administrador',
                            'func' => 'Funcionário',
                            'cli' => 'Cliente'
                        ]"
                    />
                @else
                    <input type="hidden" name="tipo" value="cli">
                @endif
            </form>
        </x-app.modal>

        {{-- Modal Editar Usuários --}}
        <x-app.modal id="modal-edt-usuario" title="Editar usuário" :btn="[['lbl' => 'Atualizar', 'color' => 'success', 'onclick' => '$(\'#form-edt-usuario\').submit()']]">
            <form method="POST" id="form-edt-usuario" action="{{ route('editar-usuario') }}" novalidate>
                @csrf
                @method('post')
                <x-app.input type="hidden" name="id" id="edt-id" required="true" />
                <x-app.input label="Nome" type="text" name="nome_edt" id="edt-name" required="true" />
                <x-app.input label="WhatsApp" type="tel" name="whatsapp_edt" id="edt-whatsapp" />
                @if(auth()->user()->adm)
                    <x-app.input label="Senha" type="password" name="senha_edt" id="senha_usuario_edt" />
                    <x-app.input label="Confirmar Senha" type="password" name="senha_confirmation_edt" id="senha_confirmation_usuario_edt" />
                @endif
                @if(auth()->user()->adm)
                    <x-app.radio name="tipo_edt"
                        :options="[
                            'adm' => 'Administrador',
                            'func' => 'Funcionário',
                            'cli' => 'Cliente'
                        ]"
                    />
                @else
                    <input type="hidden" name="tipo_edt" value="cli">
                @endif

                {{-- Serviços contratados (saldo de unidades por serviço) — só para clientes --}}
                <div id="creditos-section" class="mt-4 d-none">
                    <hr>
                    <label class="form-label fw-semibold mb-2">Serviços contratados</label>
                    <div id="creditos-lista" class="mb-2">
                        <p class="text-muted small mb-0">Nenhum serviço contratado.</p>
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col-7">
                            <select id="credito-servico" class="form-select form-select-sm">
                                @foreach($servicos->where('status', 1) as $s)
                                    <option value="{{ $s->id }}">{{ $s->descricao }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-3">
                            <input type="number" id="credito-qtd" class="form-control form-control-sm" min="1" value="1" title="Quantidade">
                        </div>
                        <div class="col-2">
                            <button type="button" class="btn btn-sm btn-primary w-100" onclick="adicionarCredito()" title="Adicionar">+</button>
                        </div>
                    </div>
                </div>
            </form>
        </x-app.modal>

        {{-- Modal Excluir Usuários --}}
        <x-app.modal id="modal-exc-usuario" title="Excluir Usuário" :btn="[['lbl' => 'Excluir', 'color' => 'danger', 'onclick' => '$(\'#form-excluir-usuario\').submit()']]">
            <form id="form-excluir-usuario" action="{{route('delete-usuario')}}" method="post">
                @csrf
                @method('post')
                <p class="fs-3">Tem certeza que deseja excluir o usuário <span id="excluir-usuario-nome"></span>?</p>
                <input class="d-none" type="text" name="id" id="excluir-usuario-id" value="">
            </form>
        </x-app.modal>

        @if(auth()->user()->adm)
        {{-- Contas de usuário --}}
        <div class="card shadow my-3">
            <div class="card-header"
                data-bs-toggle="collapse"
                data-bs-target="#collapseUsuarios"
                style="cursor: pointer">
                Controle de Contas de Usuário
            </div>
            <div id="collapseUsuarios" class="collapse">
                <div class="card-body p-3 row">
                    <div>
                        <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#modal-add-usuario" style="background-color: var(--marrom)">Novo Usuário</button>
                    </div>
                    <div class="input-group my-3">
                        <span class="input-group-text bg-primary" id="basic-addon1">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff"><path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z"/></svg>
                        </span>
                        <input type="search" class="form-control" id="search" placeholder="Pesquisar por nome" aria-label="Pesquisar" aria-describedby="basic-addon1" oninput="searchUsuario()">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th scope="col">&nbsp;</th>
                                    <th scope="col">Nome</th>
                                    <th scope="col">WhatsApp</th>
                                    <th scope="col">Administrador</th>
                                    <th scope="col">Colaborador</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-usuarios">
                                @foreach ($users as $user)
                                    <tr>
                                        @if ($user->id != 1 || Auth()->user()->id == 1)
                                            @if ($user->func != 1 || Auth()->user()->adm == 1)
                                                <td class="d-flex">
                                                    <svg style="cursor: pointer" onclick="excluirUsuario({{$user->id}},'{{$user->name}}')" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#dc3545"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                                                    <svg style="cursor: pointer" onclick="editarUsuario({{$user->id}})" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#0d6efd"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg>
                                                </td>
                                            @else
                                                <td>COLAB</td>
                                            @endif
                                        @else
                                            <td>ADM</td>
                                        @endif
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->whatsapp ?? '—' }}</td>
                                        <td>{{ $user->adm > 0 ? 'Sim' : 'Não' }}</td>
                                        <td>{{ $user->func > 0 ? 'Sim' : 'Não' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="d-flex justify-content-end">
                            {{ $users->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Modal Adicionar Serviços --}}
        <x-app.modal id="modal-add-servico" title="Adicionar Novo Serviço" :btn="[['lbl' => 'Adicionar', 'color' => 'primary', 'onclick' => '$(\'#form-add-servico\').submit()']]">
            <form method="POST" id="form-add-servico" action="{{ route('servico.store') }}" novalidate>
                @csrf
                @method('post')
                <x-app.input label="Nome do Serviço" type="text" name="descricao" id="servico_desc" required="true" />
                <p class="fs-5 my-2">Duração do Serviço</p>
                <x-app.select label="Horas" name="duracao_h" required="true" :options="['00'=>'00', '01'=>'01', '02'=>'02']" />
                <x-app.select label="Minutos" name="duracao_m" required="true" :options="['00'=>'00', '15'=>'15', '30'=>'30', '45'=>'45']" />
                <x-app.input label="Valor (R$) — agendamento online" type="number" name="valor" id="servico_valor" step="0.01" min="0" placeholder="Ex.: 50.00" />
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" name="recorrente" id="servico_recorrente">
                    <label class="form-check-label" for="servico_recorrente">Serviço mensal (pacote de cortes semanais fixos — não agendável individualmente)</label>
                </div>
            </form>
        </x-app.modal>

        {{-- Modal Editar Serviços --}}
        <x-app.modal id="modal-edt-servico" title="Editar Serviço" :btn="[['lbl' => 'Atualizar', 'color' => 'success', 'onclick' => '$(\'#form-edt-servico\').submit()']]">
            <form method="POST" id="form-edt-servico" action="{{ route('editar-servico') }}" novalidate>
                @csrf
                @method('post')
                <input type="hidden" name="id_edt_servico" id="id_edt_servico" value="{{ old('id_edt_servico') ?? '' }}">
                <x-app.input label="Descrição" type="text" name="descricao_edt_servico" required="true" />
                <p class="fs-5 my-2">Duração do Serviço</p>
                <x-app.select label="Horas" name="duracao_h_edt_servico" required="true" :options="['00'=>'00', '01'=>'01', '02'=>'02']" />
                <x-app.select label="Minutos" name="duracao_m_edt_servico" required="true" :options="['00'=>'00', '15'=>'15', '30'=>'30', '45'=>'45']" />
                <x-app.input label="Valor (R$) — agendamento online" type="number" name="valor_edt_servico" id="valor_edt_servico" step="0.01" min="0" placeholder="Ex.: 50.00" />
                <x-app.radio name="status_servico"
                    :options="[
                        '0' => 'INATIVO',
                        '1' => 'ATIVO'
                    ]"
                />
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" name="recorrente_edt_servico" id="recorrente_edt_servico">
                    <label class="form-check-label" for="recorrente_edt_servico">Serviço mensal (pacote de cortes semanais fixos — não agendável individualmente)</label>
                </div>
            </form>
        </x-app.modal>

        {{-- Modal Excluir Serviços --}}
        <x-app.modal id="modal-exc-servico" title="Excluir Serviço" :btn="[['lbl' => 'Excluir', 'color' => 'danger', 'onclick' => '$(\'#form-excluir-servico\').submit()']]">
            <form id="form-excluir-servico" action="{{route('delete-servico')}}" method="post">
                @csrf
                @method('post')
                <p class="fs-3">Tem certeza que deseja excluir o serviço <span id="excluir-servico-nome"></span>?</p>
                <input class="d-none" type="text" name="excluir-servico-id" id="excluir-servico-id" value="">
            </form>
        </x-app.modal>

        @if(auth()->user()->adm)
        {{-- Serviços --}}
        <div class="card shadow my-3">
            <div class="card-header"
                data-bs-toggle="collapse"
                data-bs-target="#collapseServicos"
                style="cursor: pointer">
                Serviços
            </div>
            <div id="collapseServicos" class="collapse">
                <div class="card-body p-3 row">
                    <div>
                        <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#modal-add-servico" style="background-color: var(--marrom)">Novo Serviço</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th scope="col">&nbsp;</th>
                                    <th scope="col">Serviço</th>
                                    <th scope="col">Duração</th>
                                    <th scope="col">Valor</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($servicos as $servico)
                                    <tr>
                                        <td class="d-flex">
                                            <svg style="cursor: pointer" onclick="excluirServico({{$servico->id}},'{{$servico->descricao}}')" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#dc3545"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                                            <svg style="cursor: pointer" onclick="editarServico('{{$servico->id}}')" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#0d6efd"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg>
                                        </td>
                                        <td>{{ $servico->descricao }}
                                            @if($servico->recorrente)<span class="badge bg-secondary ms-1">Mensal</span>@endif
                                        </td>
                                        <td>{{ $servico->duracao }}</td>
                                        <td>{{ $servico->valor ? 'R$ ' . number_format($servico->valor, 2, ',', '.') : '—' }}</td>
                                        <td class="text-{{ $servico->status == 0 ? 'danger' : 'success' }}">{{ $servico->status == 0 ? 'INATIVO' : 'ATIVO' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Modal: Gerenciar slots de um dia --}}
        <div class="modal fade" id="modal-gerenciar-slots" tabindex="-1">
            <div class="modal-dialog modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="mgm-modal-titulo">Gerenciar disponibilidade</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="mgm-data-atual">
                        {{-- Ações rápidas --}}
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <input type="time" id="mgm-comercial-inicio" class="form-control form-control-sm" style="width:auto" value="{{ \App\Models\PageContent::get('agenda','comercial_inicio','08:00') }}" title="Início do horário comercial">
                            <input type="time" id="mgm-comercial-fim" class="form-control form-control-sm" style="width:auto" value="{{ \App\Models\PageContent::get('agenda','comercial_fim','17:45') }}" title="Fim do horário comercial">
                            <button class="btn btn-sm btn-outline-success" onclick="mgmPreset('comercial')">Horário comercial</button>
                            <button class="btn btn-sm btn-outline-primary" onclick="mgmPreset('tudo')">Selecionar tudo</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="mgmPreset('limpar')">Limpar tudo</button>
                        </div>
                        {{-- Lista de slots --}}
                        <div id="mgm-slots-container">
                            <p class="text-muted">Carregando...</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-success" onclick="mgmSalvarSlots()" id="mgm-btn-salvar">Salvar</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Gerenciar Disponibilidade da Agenda --}}
        <div class="card shadow my-3">
            <div class="card-header">Gerenciar Disponibilidade da Agenda</div>
            <div class="card-body p-3">
                <div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-muted small">Barbeiro:</span>
                    @if(auth()->user()->func)
                        <input type="hidden" id="mgm-barbeiro" value="{{ auth()->user()->id }}">
                        <strong>{{ auth()->user()->name }}</strong>
                    @else
                        <select id="mgm-barbeiro" class="form-select form-select-sm" style="width:auto" onchange="mgmCarregarMes()">
                            @foreach($barbeiros as $b)
                                <option value="{{ $b->id }}" @selected((string) $barbeiroSelecionado === (string) $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <p class="text-muted small mb-3">Clique em um dia para definir quais horários estão disponíveis.</p>

                {{-- Calendário de gestão (sempre visível) --}}
                <div id="mgm-calendario-container" style="max-width:420px; margin:auto; font-family:Arial,sans-serif">
                    <div class="cal-header">
                        <span id="mgm-cal-prev" role="button" style="cursor:pointer">◀</span>
                        <h3 id="mgm-cal-mes-ano" class="mb-0"></h3>
                        <span id="mgm-cal-next" role="button" style="cursor:pointer">▶</span>
                    </div>
                    <div class="cal-header" style="justify-content:center; margin-bottom:10px">
                        <span id="mgm-cal-today" class="btn btn-sm btn-primary">Hoje</span>
                    </div>
                    <div id="mgm-week-btns-top" class="d-flex flex-wrap gap-2 mb-2 justify-content-center"></div>
                    <div class="cal-semana">
                        <div>Dom</div><div>Seg</div><div>Ter</div><div>Qua</div>
                        <div>Qui</div><div>Sex</div><div>Sab</div>
                    </div>
                    <div id="mgm-cal-dias" class="cal-grid"></div>
                </div>

                {{-- Legenda --}}
                <div class="d-flex gap-3 mt-3 justify-content-center flex-wrap">
                    <span><span style="display:inline-block;width:14px;height:14px;background:#16a34a;border-radius:3px;vertical-align:middle"></span> Com horários</span>
                    <span><span style="display:inline-block;width:14px;height:14px;background:#dc2626;border-radius:3px;vertical-align:middle"></span> Sem horários</span>
                    <span><span style="display:inline-block;width:14px;height:14px;background:#f1f1f1;opacity:.4;border-radius:3px;vertical-align:middle"></span> Passado</span>
                </div>
            </div>
        </div>

        {{-- Avisos --}}
        <div class="card shadow my-3">
            <div class="card-header d-flex justify-content-between align-items-center"
                 data-bs-toggle="collapse" data-bs-target="#collapseAvisos" style="cursor:pointer">
                <span>Avisos</span>
                @if($avisos->isNotEmpty())
                    <span class="badge bg-warning text-dark">{{ $avisos->count() }}</span>
                @endif
            </div>
            <div id="collapseAvisos" class="collapse {{ $avisos->isNotEmpty() ? 'show' : '' }}">
                <div class="card-body p-3" id="avisos-lista">
                    @include('partials.avisos')
                </div>
            </div>
        </div>

        {{-- Clientes penalizados (no-show) — adm/func podem remover a penalidade --}}
        @if($penalizados->isNotEmpty())
        <div class="card shadow my-3 border-danger">
            <div class="card-header fw-bold" style="background: rgba(220,53,69,.15)">
                ⛔ Clientes penalizados ({{ $penalizados->count() }})
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Cliente</th><th>WhatsApp</th><th>Desde</th><th class="text-end">Ação</th></tr></thead>
                    <tbody>
                        @foreach($penalizados as $pen)
                        <tr data-penalizado="{{ $pen->id }}">
                            <td>{{ $pen->name }}</td>
                            <td>{{ $pen->whatsapp }}</td>
                            <td>{{ $pen->penalizado_em ? \Carbon\Carbon::parse($pen->penalizado_em)->format('d/m/Y H:i') : '—' }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-success" onclick="removerPenalidade({{ $pen->id }}, {{ json_encode($pen->name) }})">Remover penalidade</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Planos mensais (horários fixos) --}}
        @if(auth()->user()->adm || $planos->isNotEmpty())
        <div class="card shadow my-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Planos mensais (horários fixos)</span>
                @if(auth()->user()->adm)
                <button class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#modal-add-plano" style="background-color: var(--marrom); color:#1a1410">Novo plano</button>
                @endif
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Cliente</th><th>Serviço</th><th>Barbeiro</th><th>Slot fixo</th><th>Mês</th><th>Unidades</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($planos as $pl)
                        <tr>
                            <td>{{ $pl->user->name ?? '—' }}</td>
                            <td>
                                {{ $pl->servico->descricao ?? '—' }}
                                @if($pl->recorrente) <span class="badge text-bg-info" title="Renovação automática mensal (link)">🔁 Recorrente</span>@endif
                            </td>
                            <td>{{ $pl->funcionario->name ?? '—' }}</td>
                            <td>{{ ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][$pl->dia_semana] ?? '' }} {{ \Carbon\Carbon::parse($pl->hora)->format('H:i') }}</td>
                            <td>{{ \Carbon\Carbon::parse($pl->mes)->format('m/Y') }}</td>
                            <td>{{ $pl->unidades_usadas }}/{{ $pl->unidades_total }}</td>
                            <td>
                                @if($pl->status === 'ativo') <span class="badge bg-success">Ativo</span>
                                @elseif($pl->status === 'aguardando_pagamento') <span class="badge bg-warning text-dark">Aguardando pgto</span>
                                @elseif($pl->status === 'consumido') <span class="badge bg-secondary">Consumido</span>
                                @elseif($pl->status === 'expirado') <span class="badge bg-danger">{{ $pl->restantes() }} a negociar</span>
                                @else <span class="badge bg-secondary">{{ $pl->status }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted text-center">Nenhum plano mensal.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Booking público: pedidos pagos aguardando confirmação --}}
        @if((auth()->user()->adm || auth()->user()->func) && $pendentes->isNotEmpty())
        <div class="card mb-3 border-warning">
            <div class="card-header fw-bold" style="background: rgba(255,193,7,.15)">
                ⏳ Aguardando confirmação ({{ $pendentes->count() }})
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Cliente</th><th>Serviço</th><th>Quando</th><th class="text-end">Ação</th></tr></thead>
                    <tbody>
                        @foreach($pendentes as $p)
                        <tr data-pendente="{{ $p->id }}">
                            <td>{{ $p->user->name ?? '—' }}</td>
                            <td>{{ $p->servico->descricao ?? '—' }}</td>
                            <td>{{ \Carbon\Carbon::parse($p->data_inicio)->format('d/m H:i') }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-success btn-conf-pendente" data-id="{{ $p->id }}">Confirmar</button>
                                <button class="btn btn-sm btn-outline-danger btn-rec-pendente" data-id="{{ $p->id }}">Recusar</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <script>
        $(function () {
            $(document).on('click', '.btn-conf-pendente', function () {
                const id = $(this).data('id');
                axios.post(`{{ url('/') }}/agenda/${id}/confirmar`)
                    .then(() => $(`tr[data-pendente="${id}"]`).fadeOut(250, function(){ $(this).remove(); }))
                    .catch(() => alert('Erro ao confirmar.'));
            });
            $(document).on('click', '.btn-rec-pendente', function () {
                const id = $(this).data('id');
                if (!confirm('Recusar este agendamento? O cliente será avisado e o reembolso será processado.')) return;
                axios.post(`{{ url('/') }}/agenda/${id}/recusar`)
                    .then(res => {
                        const modo = res.data.reembolso === 'automatico'
                            ? 'Reembolso automático solicitado.'
                            : 'Reembolso PENDENTE: estorne manualmente no painel da InfinitePay.';
                        alert('Recusado. ' + modo);
                        $(`tr[data-pendente="${id}"]`).fadeOut(250, function(){ $(this).remove(); });
                    })
                    .catch(() => alert('Erro ao recusar.'));
            });
        });
        </script>
        @endif

        @if(auth()->user()->adm)
        {{-- Modal: Nova ordem de pagamento (Mercado Pago) --}}
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
                    {{-- Estimativa do valor líquido após taxas da InfinitePay. Só informativo
                         — a API de Link não devolve o líquido real; o valor cobrado do cliente
                         é o integral. Taxa de exemplo — ajuste INFINITEPAY_TAXA_CREDITO ao real. --}}
                    <div id="ordem_liquido" class="form-text" style="display:none;">
                        <span class="text-muted">Taxa InfinitePay (~{{ number_format(config('services.infinitepay.taxa_credito'), 2, ',', '.') }}%):</span>
                        <span id="ordem_liquido_taxa" class="text-danger fw-semibold">-R$ 0,00</span>
                        <span class="mx-1 text-muted">•</span>
                        <span class="text-muted">Você recebe:</span>
                        <span id="ordem_liquido_valor" class="text-success fw-semibold">R$ 0,00</span>
                    </div>
                </div>
                <div class="form-text">O cliente poderá pagar em até <strong>12x</strong>, sendo <strong>6x sem juros</strong> (taxa paga pelo estabelecimento). Da 7ª à 12ª parcela o juros é pago pelo cliente.</div>
            </form>
        </x-app.modal>

        {{-- Modal: Novo plano mensal (horário fixo semanal) --}}
        <x-app.modal id="modal-add-plano" title="Novo plano mensal" :btn="[['lbl' => 'Criar plano', 'color' => 'primary', 'onclick' => '$(\'#form-add-plano\').submit()']]">
            <form method="POST" id="form-add-plano" action="{{ route('planos.store') }}" novalidate>
                @csrf
                @method('post')
                <div class="mb-3">
                    <label for="plano_user_id" class="form-label">Cliente</label>
                    <select name="user_id" id="plano_user_id" class="form-select {{ $errors->has('user_id') ? 'is-invalid' : '' }}" required>
                        <option value="">Selecione...</option>
                        @foreach ($clientes as $cliente)
                            <option value="{{ $cliente->id }}" @selected(old('user_id') == $cliente->id)>{{ $cliente->name }}</option>
                        @endforeach
                    </select>
                    @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="plano_servico_id" class="form-label">Serviço mensal</label>
                    <select name="servico_id" id="plano_servico_id" class="form-select {{ $errors->has('servico_id') ? 'is-invalid' : '' }}" required>
                        <option value="">Selecione...</option>
                        @foreach ($servicos->where('recorrente', 1) as $s)
                            <option value="{{ $s->id }}" @selected(old('servico_id') == $s->id)>{{ $s->descricao }} — R$ {{ number_format($s->valor, 2, ',', '.') }}</option>
                        @endforeach
                    </select>
                    @error('servico_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="plano_funcionario_id" class="form-label">Barbeiro</label>
                    <select name="funcionario_id" id="plano_funcionario_id" class="form-select {{ $errors->has('funcionario_id') ? 'is-invalid' : '' }}" required>
                        <option value="">Selecione...</option>
                        @foreach ($barbeiros as $b)
                            <option value="{{ $b->id }}" @selected(old('funcionario_id') == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                    @error('funcionario_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label for="plano_dia_semana" class="form-label">Dia da semana</label>
                        <select name="dia_semana" id="plano_dia_semana" class="form-select {{ $errors->has('dia_semana') ? 'is-invalid' : '' }}" required>
                            <option value="">Selecione...</option>
                            @foreach (['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'] as $i => $d)
                                <option value="{{ $i }}" @selected((string) old('dia_semana') === (string) $i)>{{ $d }}</option>
                            @endforeach
                        </select>
                        @error('dia_semana')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="plano_hora" class="form-label">Horário</label>
                        <input type="time" name="hora" id="plano_hora" class="form-control {{ $errors->has('hora') ? 'is-invalid' : '' }}" value="{{ old('hora') }}" required>
                        @error('hora')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="plano_mes" class="form-label">Mês</label>
                        <input type="month" name="mes" id="plano_mes" class="form-control {{ $errors->has('mes') ? 'is-invalid' : '' }}" value="{{ old('mes') ?? now()->copy()->startOfMonth()->addMonth()->format('Y-m') }}" required>
                        @error('mes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-text mt-2">
                    Valor total: <strong id="plano_valor_total">—</strong>
                    <span id="plano_unidades" class="text-muted"></span>
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" name="recorrente" id="plano_recorrente" {{ old('recorrente') ? 'checked' : '' }}>
                    <label class="form-check-label" for="plano_recorrente">
                        Recorrente — o sistema <strong>renova sozinho</strong> a cada mês: gera a cobrança do próximo mês (link) e avisa o cliente pagar. Não é cartão salvo.
                    </label>
                </div>
            </form>
        </x-app.modal>
        <script>
            // Cálculo do valor total do plano mensal (unidades × valor do serviço) via API.
            (function () {
                const servico = document.getElementById('plano_servico_id');
                const dia     = document.getElementById('plano_dia_semana');
                const mes     = document.getElementById('plano_mes');
                const outV    = document.getElementById('plano_valor_total');
                const outU    = document.getElementById('plano_unidades');
                async function recalc() {
                    if (!servico || !servico.value || !dia || dia.value === '' || !mes || !mes.value) {
                        if (outV) outV.textContent = '—';
                        if (outU) outU.textContent = '';
                        return;
                    }
                    try {
                        const r = await fetch(`/api/mensal/calcular?servico_id=${servico.value}&dia_semana=${dia.value}&mes=${mes.value}`);
                        const d = await r.json();
                        if (d.error) { if (outV) outV.textContent = '—'; return; }
                        if (outV) outV.textContent = 'R$ ' + Number(d.valor_total).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                        if (outU) outU.textContent = `(${d.unidades} cortes)`;
                    } catch (e) { if (outV) outV.textContent = '—'; }
                }
                [servico, dia, mes].forEach(el => el && el.addEventListener('change', recalc));
                recalc();
            })();
        </script>

        {{-- Ordens de Pagamento (Mercado Pago) --}}
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
                                        <td>@if($ordem->installments) {{ $ordem->installments }}x @else até {{ $ordem->max_parcelas }}x @endif</td>
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
        @endif

    @endif

    {{-- Agendamentos --}}
    <div class="container py-4">
        <h3 class="mb-4">Agendamentos</h3>

        @if(auth()->user()->adm)
        <div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
            <span class="text-muted small">Filtrar por barbeiro:</span>
            <a class="btn btn-sm {{ !$barbeiroSelecionado ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('dashboard') }}">Todos</a>
            @foreach($barbeiros as $b)
                <a class="btn btn-sm {{ (string) $barbeiroSelecionado === (string) $b->id ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('dashboard', ['barbeiro' => $b->id]) }}">{{ $b->name }}</a>
            @endforeach
        </div>
        @endif

        @if(auth()->user()->adm == 1 || auth()->user()->func == 1)
            {{-- Modal Adicionar/Editar Agendamentos --}}
            <x-app.modal id="modal-add-agenda" title="Adicionar Novo Agendamento" :btn="[['lbl' => 'Adicionar', 'color' => 'primary', 'onclick' => '$(\'#form-add-agenda\').submit()']]">
                <form method="POST" id="form-add-agenda" action="{{ route('agenda.store') }}" novalidate>
                    @csrf
                    @method('post')
                    <input type="hidden" name="user_id" id="user_id" value="{{ old('user_id') }}">
                    <div class="mb-3 position-relative">
                        <label class="form-label">Cliente</label>
                        <input type="text" id="user_id_busca" class="form-control {{ $errors->has('user_id') ? 'is-invalid' : '' }}"
                               placeholder="Digite nome ou WhatsApp..." autocomplete="off">
                        <div id="user_id_resultados" class="list-group position-absolute w-100 d-none shadow"
                             style="z-index:50; max-height:260px; overflow:auto"></div>
                        @error('user_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    @if(auth()->user()->func)
                        {{-- Funcionário só agenda na própria agenda --}}
                        <input type="hidden" name="funcionario_id" value="{{ auth()->user()->id }}">
                    @else
                        <x-app.select label="Barbeiro" name="funcionario_id" required="true" :options="$barbeiros->pluck('name', 'id')" />
                    @endif
                    {{-- Especial (encaixe): pode ser aplicado a qualquer serviço e colocado sobre
                         outros agendamentos. O serviço (nome+duração) vem do pacote escolhido em
                         "Descontar de"; no encaixe livre (sem desconto) é escolhido no catálogo. --}}
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="chk_especial">
                        <label class="form-check-label" for="chk_especial">Especial (encaixe)</label>
                    </div>
                    {{-- Serviço comum: um item por PACOTE contratado (com saldo restante/total).
                         O select não é enviado; ele alimenta os campos ocultos servico_id e credito_id. --}}
                    <div id="servico-normal-wrap" class="mb-3">
                        <label for="servico_sel" class="form-label">Selecione o serviço</label>
                        <select id="servico_sel" class="form-select {{ $errors->has('servico_id') ? 'is-invalid' : '' }}" required>
                            <option value="">Selecione o cliente primeiro</option>
                        </select>
                        @error('servico_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    {{-- O checkbox não é enviado no POST; este campo oculto carrega a flag
                         (sincronizado em aplicarModoEspecial). --}}
                    <input type="hidden" name="especial" id="especial_flag" value="0">
                    <input type="hidden" name="servico_id" id="servico_id" value="{{ old('servico_id') }}">
                    <input type="hidden" name="credito_id" id="credito_id" value="{{ old('credito_id') }}">
                    {{-- Especial: de qual pacote contratado descontar (ou encaixe livre) --}}
                    <div id="credito-alvo-wrap" class="mb-3 d-none">
                        <label for="credito_alvo" class="form-label">Descontar de</label>
                        <select id="credito_alvo" class="form-select">
                            <option value="">Não descontar (encaixe livre)</option>
                        </select>
                    </div>
                    {{-- Encaixe livre (sem desconto): define o serviço pelo catálogo, só para
                         o nome e a duração do horário. Visível apenas no especial sem pacote. --}}
                    <div id="servico-livre-wrap" class="mb-3 d-none">
                        <label for="servico_livre" class="form-label">Serviço (encaixe livre)</label>
                        <select id="servico_livre" class="form-select">
                            <option value="">Selecione o serviço</option>
                            @foreach($servicos->where('status', 1)->where('visivel_cliente', 1) as $s)
                                <option value="{{ $s->id }}">{{ $s->descricao }}</option>
                            @endforeach
                        </select>
                    </div>
                    {{-- Especial: duração personalizada (min) para casos específicos.
                         Vazio = usa a duração do serviço descontado/escolhido. --}}
                    <div id="duracao-especial-wrap" class="mb-3 d-none">
                        <label for="duracao_especial" class="form-label">Duração personalizada</label>
                        {{-- Valores fixos em incrementos de 15 min (em minutos) para não
                             conflitar com a grade de horários. Vazio = duração do serviço. --}}
                        <select class="form-select" id="duracao_especial" name="duracao_min">
                            <option value="">Padrão do serviço</option>
                            @for($m = 15; $m <= 240; $m += 15)
                                @php
                                    $h   = intdiv($m, 60);
                                    $min = $m % 60;
                                    $lbl = ($h === 0 ? '00' : $h) . ':' . str_pad($min, 2, '0', STR_PAD_LEFT);
                                @endphp
                                <option value="{{ $m }}">{{ $lbl }}</option>
                            @endfor
                        </select>
                        <small class="text-muted">Opcional. Se vazio, usa a duração do serviço descontado.</small>
                    </div>
                    <input type="hidden" name="data_inicio" id="data_inicio" />
                    <input type="hidden" name="data_fim" id="data_fim" />
                    <input type="hidden" id="dia_selecionado" name="dia_selecionado">
                    <input type="hidden" id="hora_selecionada" name="hora_selecionada" value="{{ old('hora_selecionada') }}">
                    <input type="hidden" id="agendamento_id" name="agendamento_id" value="{{ old('agendamento_id') }}">
                    <x-app.calendar :servicos="$servicos" :is-adm="true" />
                </form>
            </x-app.modal>

            <div class="my-3">
                <button class="btn btn-outline-light" onclick="abrirNovoAgendamento()" style="background-color: var(--marrom)">Novo Agendamento</button>
            </div>

            <div class="input-group my-3">
                <span class="input-group-text bg-primary" id="basic-addon1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff"><path d="M400-240v-80h160v80H400ZM240-440v-80h480v80H240ZM120-640v-80h720v80H120Z"/></svg>
                </span>
                <input type="search" class="form-control" id="search-consulta" placeholder="Filtrar por cliente" aria-label="Filtrar por cliente" aria-describedby="basic-addon1" oninput="searchConsulta()">
            </div>
        @endif

        @if(!auth()->user()->adm && !auth()->user()->func)
        {{-- Ação primária: agendar corte (respeita penalidade de no-show) --}}
        @if(auth()->user()->isPenalizado())
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 my-3">
            <span>⚠️ Você está com uma pendência e não pode agendar online no momento. Regularize com a barbearia para voltar a agendar.</span>
            <a class="btn btn-sm ms-auto" target="_blank" rel="noopener" style="background-color: var(--marrom); color:#1a1410"
               href="https://wa.me/{{ $whatsappAdmin }}?text={{ urlencode('Olá! Preciso regularizar minha pendência para voltar a agendar.') }}">Falar no WhatsApp</a>
        </div>
        @else
        <div class="card shadow my-3">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <div class="flex-grow-1">
                    <div class="fw-semibold fs-5">Pronto para o próximo corte?</div>
                    <div class="text-muted small">Escolha o barbeiro, o serviço, o dia e o horário e pague pelo site.</div>
                </div>
                <a href="{{ route('agendar.index') }}" class="btn btn-lg px-4" style="background-color: var(--marrom); color:#1a1410">✂️ Agendar corte</a>
            </div>
        </div>
        @endif

        {{-- Pacotes mensais disponíveis: oferta visível ao cliente (aquisição só na barbearia) --}}
        @php
            $pacotesMensais = $servicos->where('recorrente', 1)
                ->where('visivel_cliente', 1)->where('status', 1)->where('valor', '>', 0)->values();
        @endphp
        @if($pacotesMensais->isNotEmpty())
        <div class="card shadow my-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                Pacotes mensais disponíveis <span class="badge text-bg-light">na barbearia</span>
            </div>
            <div class="card-body p-3">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach($pacotesMensais as $pm)
                        <div class="border rounded px-3 py-2 small">
                            <strong>{{ $pm->descricao }}</strong>
                            <div class="text-muted">R$ {{ number_format($pm->valor, 2, ',', '.') }} / corte · horário fixo semanal</div>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted small">Aquisição apenas presencialmente na barbearia.</span>
                    <a class="btn btn-sm ms-auto" target="_blank" rel="noopener" style="background-color: var(--marrom); color:#1a1410"
                       href="https://wa.me/{{ $whatsappAdmin }}?text={{ urlencode('Olá! Quero saber mais sobre o pacote mensal.') }}">Quero assinar</a>
                </div>
            </div>
        </div>
        @endif

        {{-- Meus pacotes (validade / Negociar) --}}
        @if($meusCreditos->isNotEmpty())
        <div class="card shadow my-3">
            <div class="card-header">Meus pacotes</div>
            <div class="card-body p-3">
                @foreach($meusCreditos as $cred)
                    @php
                        $usadas   = $cred->agendamentos_count ?? $cred->usadas();
                        $restam   = $cred->restantes();
                        $expirado = $cred->expirado();
                        $negociar = $cred->negociar();
                    @endphp
                    <div class="border rounded px-2 py-1 mb-1 small">
                        <strong>{{ $cred->servico->descricao ?? '—' }}</strong>
                        <span class="badge bg-secondary">{{ $usadas }}/{{ $cred->quantidade }} usados</span>
                        @if($negociar)
                            <a class="badge bg-warning text-dark text-decoration-none" target="_blank" rel="noopener"
                               href="https://wa.me/{{ $whatsappAdmin }}?text={{ urlencode('Olá! Quero renegociar meu pacote de ' . ($cred->servico->descricao ?? '') . ' (expirado).') }}">Negociar</a>
                        @else
                            <span class="badge {{ $restam > 0 ? 'bg-success' : 'bg-danger' }}">resta {{ $restam }}</span>
                        @endif
                        @if($cred->expira_em)
                            <span class="text-muted">· expira em {{ $cred->expira_em->format('d/m/Y') }}{{ $expirado ? ' (expirado)' : '' }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($meusPlanos->isNotEmpty())
        <div class="card shadow my-3">
            <div class="card-header">Meus planos mensais</div>
            <div class="card-body p-3">
                @foreach($meusPlanos as $pl)
                    @php
                        $diasSlot = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
                        $slot = ($diasSlot[$pl->dia_semana] ?? '') . ' ' . \Carbon\Carbon::parse($pl->hora)->format('H:i');
                    @endphp
                    <div class="border rounded px-2 py-1 mb-1 small">
                        <strong>{{ $pl->servico->descricao ?? '—' }}</strong>
                        · {{ $pl->funcionario->name ?? '—' }} · {{ $slot }}
                        <span class="badge bg-secondary">{{ $pl->unidades_usadas }}/{{ $pl->unidades_total }} usadas</span>
                        @if($pl->status === 'ativo')
                            <span class="badge bg-success">Ativo</span>
                        @elseif($pl->status === 'aguardando_pagamento')
                            <span class="badge bg-warning text-dark">Aguardando pagamento</span>
                        @elseif($pl->status === 'consumido')
                            <span class="badge bg-secondary">Concluído</span>
                        @elseif($pl->status === 'expirado' && $pl->restantes() > 0)
                            <a class="badge bg-warning text-dark text-decoration-none" target="_blank" rel="noopener"
                               href="https://wa.me/{{ $whatsappAdmin }}?text={{ urlencode('Olá! Quero renegociar meu plano mensal (restam ' . $pl->restantes() . ' cortes).') }}">Negociar {{ $pl->restantes() }}</a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

            <x-app.modal id="modal-reagendar" title="Reagendar Agendamento"
                :btn="[['lbl' => 'Confirmar', 'color' => 'primary', 'onclick' => '$(\'#form-reagendar\').submit()']]">
                <form method="POST" id="form-reagendar" action="{{ route('agenda.store') }}" novalidate>
                    @csrf
                    <input type="hidden" name="user_id" value="{{ auth()->id() }}" />
                    <input type="hidden" name="agendamento_id" id="agendamento_id" value="{{ old('agendamento_id') }}" />
                    <input type="hidden" name="data_inicio" id="data_inicio" />
                    <input type="hidden" name="data_fim" id="data_fim" />
                    <input type="hidden" id="dia_selecionado" name="dia_selecionado" />
                    <input type="hidden" id="hora_selecionada" name="hora_selecionada" value="{{ old('hora_selecionada') }}" />
                    <input type="hidden" name="servico_id" id="servico_id" value="{{ old('servico_id') }}" />
                    <input type="hidden" name="funcionario_id" id="funcionario_id_reagendar" value="" />
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Serviço</label>
                        <input type="text" id="servico_nome_display" class="form-control" readonly
                               style="background:#f8f9fa; cursor:default"
                               value="{{ old('servico_id') ? ($servicos->firstWhere('id', old('servico_id'))?->descricao ?? '') : '' }}" />
                    </div>
                    <x-app.calendar :servicos="$servicos" :is-adm="false" />
                </form>
            </x-app.modal>

            {{-- Modal: horário especial (staff) — cliente não pode reagendar por aqui --}}
            <div class="modal fade" id="modal-horario-especial" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Horário especial</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">Este horário não pode ser reagendado por aqui, pois se trata de um horário especial. Nossa equipe entrará em contato com você para ajustar. 😊</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendi</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if(!auth()->user()->adm && !auth()->user()->func)
            {{-- Meus pagamentos (Mercado Pago) --}}
            <div class="card shadow my-3">
                <div class="card-header">Meus pagamentos</div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Descrição</th>
                                    <th>Valor</th>
                                    <th>Parcelas</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($minhasOrdens as $ordem)
                                    @php [$rotulo, $cls] = $ordem->statusBadge(); @endphp
                                    <tr>
                                        <td>{{ $ordem->descricao }}</td>
                                        <td>R$ {{ number_format($ordem->valor, 2, ',', '.') }}</td>
                                        <td>@if($ordem->max_parcelas > 1) até {{ $ordem->max_parcelas }}x ({{ min((int) $ordem->max_parcelas, \App\Models\OrdemPagamento::MAX_SEM_JUROS) }}x sem juros) @else À vista @endif</td>
                                        <td><span class="badge {{ $cls }}">{{ $rotulo }}</span></td>
                                        <td>
                                            @if ($ordem->pagavel())
                                                <a href="{{ route('pagamentos.pagar', $ordem) }}" class="btn btn-sm" style="background-color: var(--marrom); color:#fff">Pagar</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted text-center">Você não tem pagamentos no momento.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal: ação sobre consulta (reagendar / excluir / dispensar) --}}
        <div class="modal fade" id="modal-acao-consulta" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-acao-titulo">O que deseja fazer?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p id="modal-acao-info" class="mb-0"></p>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Dispensar</button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary" id="btn-acao-reagendar">Reagendar</button>
                            <button type="button" class="btn btn-danger" id="btn-acao-excluir">Excluir</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: confirmar exclusão/cancelamento --}}
        <div class="modal fade" id="modal-confirmar-exclusao" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-confirmar-titulo">Confirmar exclusão</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p id="modal-confirmar-info" class="mb-0"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
                        <button type="button" class="btn btn-danger" id="btn-confirmar-excluir">Confirmar</button>
                    </div>
                </div>
            </div>
        </div>

        @php
            // Badge de status de um lembrete: 'enviado' | 'erro' | null (não enviado)
            // $clsEnviado permite trocar a cor do "enviado" (ex.: azul na véspera pré-confirmada)
            $lembreteBadge = function (?string $status, string $titulo, string $clsEnviado = 'bg-success'): string {
                [$txt, $cls] = match ($status) {
                    'enviado' => ['enviado', $clsEnviado],
                    'erro'    => ['falhou',  'bg-danger'],
                    default   => ['não enviado', 'bg-secondary'],
                };
                return "<span class=\"badge {$cls}\" style=\"font-weight:500\">{$titulo}: {$txt}</span>";
            };
            // Uma linha "lembrete → confirmação": o badge do lembrete e uma seta apontando
            // para o resultado. Se já confirmou, mostra o momento (azul/verde); se o lembrete
            // foi enviado mas ainda não confirmou, mostra "aguardando confirmação" (cinza).
            $confirmaLinha = function (string $badge, $em, string $rotulo, string $cls, ?string $status): string {
                $linha = '<div class="d-flex align-items-center gap-1 flex-wrap" style="font-size:.72rem">' . $badge;
                if ($em) {
                    $linha .= '<span class="text-muted">→</span>'
                        . '<span class="badge ' . $cls . '" style="font-weight:500">' . $rotulo . ' ' . $em->format('d/m \à\s H:i') . '</span>';
                } elseif ($status === 'enviado') {
                    $linha .= '<span class="text-muted">→</span>'
                        . '<span class="badge bg-secondary" style="font-weight:500">aguardando confirmação</span>';
                }
                return $linha . '</div>';
            };
        @endphp
        <div class="accordion shadow" id="accordionMeses">
            @foreach ($consultas as $mes => $diasDoMes)
                @php
                    $id = Str::slug($mes);
                    $isOpen = $mes === $mesAtual ? 'show' : '';
                    $isCollapsed = $mes === $mesAtual ? '' : 'collapsed';
                @endphp
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-{{ $id }}">
                        <button class="accordion-button {{ $isCollapsed }}" type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#collapse-{{ $id }}">
                            {{ ucfirst($mes) }}
                        </button>
                    </h2>
                    <div id="collapse-{{ $id }}" class="accordion-collapse collapse {{ $isOpen }}"
                        data-bs-parent="#accordionMeses">
                        <div class="accordion-body">
                            @forelse ($diasDoMes as $dataKey => $listaDia)
                                @php
                                    $isHoje   = $dataKey === $hoje;
                                    $diaLabel = \Carbon\Carbon::parse($dataKey)->locale('pt_BR')->translatedFormat('l, d \d\e F');
                                @endphp
                                <div class="mb-2">
                                    <button class="btn btn-sm w-100 text-start d-flex align-items-center gap-2 {{ $isHoje ? 'btn-primary' : 'btn-outline-secondary' }}"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#dia-{{ $dataKey }}">
                                        <span>{{ ucfirst($diaLabel) }}</span>
                                        <span class="badge {{ $isHoje ? 'bg-light text-dark' : 'bg-secondary' }} ms-auto">{{ $listaDia->count() }}</span>
                                    </button>
                                    <div id="dia-{{ $dataKey }}" class="collapse {{ $isHoje ? 'show' : '' }} pt-2">
                                        @foreach ($listaDia as $consulta)
                                            @php $isStaff = auth()->user()->adm == 1 || auth()->user()->func == 1; @endphp
                                            <div class="consulta-card {{ !$consulta->confirmado ? 'consulta-pendente' : '' }} {{ $consulta->especial ? 'consulta-especial' : '' }} {{ $consulta->plano_mensal_id ? 'consulta-mensal' : '' }} d-flex {{ $isStaff ? 'justify-content-between align-items-center' : 'flex-column' }}" data-consulta-id="{{ $consulta->id }}">
                                                <div class="d-flex flex-grow-1" @if($isStaff) onclick="editarConsulta({{ $consulta->id }})" style="cursor: pointer" @endif>
                                                    <div class="me-3 text-center">
                                                        <div class="consulta-hora">
                                                            {{ \Carbon\Carbon::parse($consulta->data_inicio)->format('H:i') }} <br>às<br>
                                                            {{ \Carbon\Carbon::parse($consulta->data_fim)->format('H:i') }}
                                                        </div>
                                                        @if($consulta->especial)
                                                            <div class="mt-2"><span class="badge bg-danger">Horário especial</span></div>
                                                        @endif
                                                    </div>
                                                    <div>
                                                        @if($consulta->confirmado)
                                                            <span class="badge bg-success mb-1">✓ Confirmado</span>
                                                        @elseif($consulta->pre_confirmado_em)
                                                            <span class="badge bg-info text-dark mb-1">Pré-confirmado</span>
                                                        @elseif($isStaff)
                                                            <span class="badge bg-warning text-dark mb-1">Pendente confirmação</span>
                                                        @else
                                                            <span class="badge bg-warning text-dark mb-1">Aguardando confirmação</span>
                                                        @endif
                                                        @if($consulta->plano_mensal_id)<span class="badge bg-primary mb-1 ms-1">📅 Mensal</span>@endif
                                                        <br>
                                                        <strong>Cliente:</strong> {{ $consulta->user->name }}
                                                        @if($consulta->funcionario)<br><strong>Barbeiro:</strong> {{ $consulta->funcionario->name }}@endif
                                                        @if($consulta->pagar_no_local) <span class="badge bg-info text-dark ms-1">Pagar no local</span>@endif
                                                        <br>
                                                        <strong>Serviço:</strong> {{ $consulta->servico_display }}
                                                        @if($isStaff)
                                                            <div class="d-flex flex-column gap-1 mt-2">
                                                                {!! $confirmaLinha(
                                                                    $lembreteBadge($consulta->lembrete_24h, 'Véspera', $consulta->pre_confirmado_em ? 'bg-info text-dark' : 'bg-success'),
                                                                    $consulta->pre_confirmado_em, 'Pré-confirmou em:', 'bg-info text-dark', $consulta->lembrete_24h) !!}
                                                                {!! $confirmaLinha(
                                                                    $lembreteBadge($consulta->lembrete_2h, '2h antes'),
                                                                    $consulta->confirmado_em, 'Confirmou em:', 'bg-success', $consulta->lembrete_2h) !!}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                                @if($isStaff)
                                                    @if(!$consulta->confirmado)
                                                        <button class="btn btn-sm btn-success btn-confirmar ms-2"
                                                                onclick="event.stopPropagation(); confirmarConsulta({{ $consulta->id }})">
                                                            Confirmar
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-primary btn-reenviar ms-2"
                                                                title="Enviar pedido de confirmação agora no WhatsApp"
                                                                onclick="event.stopPropagation(); reenviarLembrete({{ $consulta->id }}, this)">
                                                            Enviar pedido de confirmação agora
                                                        </button>
                                                    @endif
                                                    {{-- Comparecimento (gera penalidade em no-show) --}}
                                                    @if(is_null($consulta->compareceu))
                                                        <button class="btn btn-sm btn-outline-success ms-2"
                                                                onclick="event.stopPropagation(); marcarComparecimento({{ $consulta->id }}, true)">Compareceu</button>
                                                        <button class="btn btn-sm btn-outline-danger ms-2"
                                                                onclick="event.stopPropagation(); marcarComparecimento({{ $consulta->id }}, false)">Não compareceu</button>
                                                    @elseif($consulta->compareceu === true)
                                                        <span class="badge bg-success ms-2">✓ Compareceu</span>
                                                    @else
                                                        <span class="badge bg-danger ms-2">✗ Não compareceu</span>
                                                    @endif
                                                    <button class="btn btn-sm btn-danger ms-2"
                                                            onclick="event.stopPropagation(); excluirConsultaById({{ $consulta->id }})">
                                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                                                    </button>
                                                @elseif($consulta->data_inicio->isFuture())
                                                    @php $consultaEhStaff = (bool) $consulta->especial; @endphp
                                                    <div class="d-flex gap-2 mt-3">
                                                        <button class="btn btn-sm btn-outline-primary"
                                                                data-id="{{ $consulta->id }}"
                                                                data-servico-id="{{ $consulta->servico_id }}"
                                                                data-servico-nome="{{ $consulta->servico->descricao ?? '' }}"
                                                                data-staff="{{ $consultaEhStaff ? '1' : '0' }}"
                                                                data-funcionario-id="{{ $consulta->funcionario_id }}"
                                                                onclick="reagendarConsulta(this.dataset.id, this.dataset.servicoId, this.dataset.servicoNome, this.dataset.staff === '1', this.dataset.funcionarioId)">
                                                            Reagendar
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger"
                                                                data-id="{{ $consulta->id }}"
                                                                data-servico-id="{{ $consulta->servico_id }}"
                                                                data-servico-nome="{{ $consulta->servico->descricao ?? '' }}"
                                                                data-staff="{{ $consultaEhStaff ? '1' : '0' }}"
                                                                data-funcionario-id="{{ $consulta->funcionario_id }}"
                                                                onclick="cancelarConsulta(this.dataset.id, this.dataset.servicoId, this.dataset.servicoNome, this.dataset.staff === '1', this.dataset.funcionarioId)">
                                                            Cancelar
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted">Nenhum agendamento neste mês.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@section('scriptEnd')
    @if(auth()->user()->adm == 1 || auth()->user()->func == 1)
    <script>
        // ── Sessão (para regras de proteção no JS da tabela de usuários) ────
        const AUTH_ID  = {{ (int) auth()->id() }};
        const AUTH_ADM = {{ (int) auth()->user()->adm }};

        // ── Mapa de consultas para acesso por id ─────────────────────────────
        const consultaMap = {};
        @foreach($consultas->flatten() as $c)
            consultaMap[{{ $c->id }}] = {
                id: {{ $c->id }},
                user_id: {{ $c->user_id }},
                servico_id: {{ $c->servico_id }},
                confirmado: {{ $c->confirmado ? 'true' : 'false' }},
                pre_confirmado_em: @json($c->pre_confirmado_em),
                confirmado_em: @json($c->confirmado_em),
                lembrete_24h: @json($c->lembrete_24h),
                lembrete_2h: @json($c->lembrete_2h),
                user: { name: @json($c->user->name) },
                servico: { descricao: @json($c->servico->descricao ?? '') },
                data_inicio: @json($c->data_inicio->format('Y-m-d H:i:s')),
                data_fim: @json($c->data_fim->format('Y-m-d H:i:s')),
                mes: @json(\Carbon\Carbon::parse($c->data_inicio)->locale('pt_BR')->translatedFormat('F Y')),
                inicio: @json($c->data_inicio->format('H:i')),
                fim: @json($c->data_fim->format('H:i')),
                dia: @json($c->data_inicio->format('d')),
            };
        @endforeach

        // ── Comparecimento / penalidade ─────────────────────────────────────
        function marcarComparecimento(id, compareceu) {
            if (!compareceu && !confirm('Marcar como NÃO compareceu? O cliente fica penalizado e não poderá agendar até a penalidade ser removida.')) return;
            axios.post(`{{ url('/') }}/agenda/${id}/comparecimento`, { compareceu })
                .then(() => location.reload())
                .catch(() => alert('Erro ao registrar comparecimento.'));
        }

        function removerPenalidade(userId, nome) {
            if (!confirm(`Remover a penalidade de ${nome}? Ele voltará a poder agendar.`)) return;
            axios.post(`{{ url('/') }}/usuario/${userId}/remover-penalidade`)
                .then(() => location.reload())
                .catch(() => alert('Erro ao remover penalidade.'));
        }

        // ── Busca de cliente (nome ou WhatsApp) no modal de nova consulta ────
        (function () {
            const input  = document.getElementById('user_id_busca');
            const box    = document.getElementById('user_id_resultados');
            const hidden = document.getElementById('user_id');
            if (!input || !box || !hidden) return;
            let timer = null;

            const escolher = (id, nome) => {
                hidden.value = id;
                input.value  = nome;
                box.classList.add('d-none');
                popularSelectServicos(id); // recarrega serviços/pacotes do cliente
            };

            input.addEventListener('input', () => {
                hidden.value = '';
                clearTimeout(timer);
                const q = input.value.trim();
                if (q.length < 2) { box.classList.add('d-none'); return; }
                timer = setTimeout(() => {
                    axios.get('{{ url("/") }}/usuarios-search', { params: { q } })
                        .then(res => {
                            // só clientes (não adm/func)
                            const lista = (res.data.data || []).filter(u => !u.adm && !u.func);
                            if (!lista.length) {
                                box.innerHTML = '<div class="list-group-item small text-muted">Nenhum cliente encontrado.</div>';
                            } else {
                                box.innerHTML = lista.map(u =>
                                    `<button type="button" class="list-group-item list-group-item-action small py-1" data-id="${u.id}" data-nome="${(u.name || '').replace(/"/g, '&quot;')}">
                                        ${u.name} <span class="text-muted">${u.whatsapp ?? ''}</span>
                                    </button>`).join('');
                            }
                            box.classList.remove('d-none');
                        })
                        .catch(() => box.classList.add('d-none'));
                }, 300);
            });

            box.addEventListener('click', (e) => {
                const btn = e.target.closest('button[data-id]');
                if (!btn) return;
                escolher(btn.dataset.id, btn.dataset.nome);
            });

            document.addEventListener('click', (e) => {
                if (!input.contains(e.target) && !box.contains(e.target)) box.classList.add('d-none');
            });
        })();

        // ── Agendamentos ─────────────────────────────────────────────────────
        function abrirNovoAgendamento() {
            document.getElementById('agendamento_id').value = '';
            document.getElementById('user_id').value = '';
            const ubNovo = document.getElementById('user_id_busca'); if (ubNovo) ubNovo.value = '';
            document.getElementById('duracao_especial').value = '';
            popularSelectServicos('');
            document.getElementById('dia_selecionado').value = '';
            document.getElementById('hora_selecionada').value = '';
            document.getElementById('data_inicio').value = '';
            document.getElementById('data_fim').value = '';
            document.getElementById('horarios').innerHTML = '';
            document.getElementById('horarios-erro').textContent = '';
            document.querySelectorAll('.cal-dia').forEach(d => d.classList.remove('cal-selecionado'));
            dataAtual = new Date();
            carregarMes();
            new bootstrap.Modal(document.getElementById('modal-add-agenda')).show();
        }

        function editarConsulta(id) {
            axios.get(`/agenda/${id}/edit`)
                .then(res => {
                    const c = res.data;

                    document.getElementById('user_id').value       = c.user_id;
                    const ubEdt = document.getElementById('user_id_busca'); if (ubEdt) ubEdt.value = c.user?.name ?? '';
                    document.getElementById('agendamento_id').value = c.id;
                    document.getElementById('dia_selecionado').value = c.data_inicio.split(' ')[0];
                    document.getElementById('hora_selecionada').value = c.data_inicio.split(' ')[1].substring(0, 5);
                    document.getElementById('data_inicio').value   = c.data_inicio;
                    document.getElementById('data_fim').value      = c.data_fim;

                    // Navegar o calendário para o mês da consulta
                    dataAtual = new Date(c.data_inicio.replace(' ', 'T'));

                    // Duração personalizada: se for especial e a duração salva diferir do
                    // padrão do serviço, pré-preenche o campo (senão deixa vazio = padrão).
                    const durField = document.getElementById('duracao_especial');
                    if (durField) {
                        durField.value = '';
                        if (c.especial) {
                            const durMin = Math.round(
                                (new Date(c.data_fim.replace(' ', 'T')) - new Date(c.data_inicio.replace(' ', 'T'))) / 60000
                            );
                            const s = SERVICOS.find(x => x.id == c.servico_id);
                            let defMin = null;
                            if (s) { const [h, m] = s.duracao.split(':').map(Number); defMin = h * 60 + m; }
                            if (defMin === null || durMin !== defMin) durField.value = durMin;
                        }
                    }

                    // Carrega os serviços do cliente (forçando o serviço/pacote atual) antes de abrir
                    popularSelectServicos(c.user_id, { servicoId: c.servico_id, creditoId: c.credito_servico_id, ordinal: c.credito_ordinal, especial: c.especial }, () => {
                        new bootstrap.Modal(document.getElementById('modal-add-agenda')).show();

                        carregarMes().then(() => {
                            const diaNumero = parseInt(c.data_inicio.split(' ')[0].split('-')[2]);
                            document.querySelectorAll('.cal-dia').forEach(d => {
                                if (parseInt(d.textContent) === diaNumero && !d.classList.contains('disabled')) {
                                    d.classList.add('cal-selecionado');
                                }
                            });
                            getHoras(c.data_inicio.split(' ')[0]);
                        });
                    });
                });
        }

        function excluirConsultaById(id) {
            const c = consultaMap[id];
            if (!c) return;

            document.getElementById('modal-acao-titulo').textContent = 'O que deseja fazer?';
            document.getElementById('modal-acao-info').textContent =
                `Agendamento de ${c.user.name} — ${c.servico.descricao} — Dia ${c.dia} às ${c.inicio}`;
            document.getElementById('btn-acao-reagendar').textContent = 'Reagendar';
            document.getElementById('btn-acao-excluir').textContent   = 'Excluir sem reagendar';

            const modalAcao = new bootstrap.Modal(document.getElementById('modal-acao-consulta'));

            document.getElementById('btn-acao-reagendar').onclick = () => {
                modalAcao.hide();
                editarConsulta(id);
            };

            document.getElementById('btn-acao-excluir').onclick = () => {
                document.getElementById('modal-confirmar-titulo').textContent = 'Confirmar exclusão';
                document.getElementById('modal-confirmar-info').textContent =
                    `Tem certeza que deseja excluir o agendamento de ${c.user.name} (${c.servico.descricao}) das ${c.inicio} às ${c.fim} do dia ${c.dia}?`;
                document.getElementById('btn-confirmar-excluir').textContent = 'Excluir';

                const modalConfirmar = new bootstrap.Modal(document.getElementById('modal-confirmar-exclusao'));

                document.getElementById('btn-confirmar-excluir').onclick = () => {
                    axios.delete(`/agenda/${id}`)
                        .then(() => {
                            modalConfirmar.hide();
                            const card = document.querySelector(`.consulta-card[data-consulta-id="${id}"]`);
                            if (card) card.remove();
                            delete consultaMap[id];
                        })
                        .catch(() => alert('Erro ao excluir agendamento'));
                };

                document.getElementById('modal-acao-consulta').addEventListener('hidden.bs.modal', () => {
                    modalConfirmar.show();
                }, { once: true });
                modalAcao.hide();
            };

            modalAcao.show();
        }

        function dispensarAviso(id) {
            axios.post(`/aviso/${id}/dispensar`)
                .then(() => {
                    document.getElementById(`aviso-${id}`)?.remove();
                    const restantes = document.querySelectorAll('#avisos-lista [id^="aviso-"]').length;
                    if (restantes === 0) {
                        const lista = document.getElementById('avisos-lista');
                        if (lista) lista.innerHTML = '<p class="text-muted mb-0">Nenhum aviso pendente.</p>';
                        document.querySelector('[data-bs-target="#collapseAvisos"] .badge')?.remove();
                    } else {
                        const badge = document.querySelector('[data-bs-target="#collapseAvisos"] .badge');
                        if (badge) badge.textContent = restantes;
                    }
                })
                .catch(() => alert('Erro ao dispensar aviso'));
        }

        function confirmarConsulta(id) {
            axios.post(`/agenda/${id}/confirmar`)
                .then(() => {
                    const card = document.querySelector(`.consulta-card[data-consulta-id="${id}"]`);
                    if (card) {
                        card.classList.remove('consulta-pendente');
                        card.querySelector('.badge.bg-warning')?.remove();
                        card.querySelector('.btn-confirmar')?.remove();
                    }
                    if (consultaMap[id]) {
                        consultaMap[id].confirmado = true;
                    }
                })
                .catch(() => alert('Erro ao confirmar agendamento'));
        }

        // Badge de status de um lembrete: 'enviado' | 'erro' | null (não enviado)
        // clsEnviado permite trocar a cor do "enviado" (ex.: azul na véspera pré-confirmada)
        function lembreteBadge(status, titulo, clsEnviado = 'bg-success') {
            const mapa = {
                enviado: ['enviado', clsEnviado],
                erro:    ['falhou',  'bg-danger'],
            };
            const [txt, cls] = mapa[status] ?? ['não enviado', 'bg-secondary'];
            return `<span class="badge ${cls}" style="font-weight:500">${titulo}: ${txt}</span>`;
        }

        // Formata timestamp ISO ("2026-06-02T12:05:00...") em "02/06 às 12:05" (sem conversão de fuso)
        function fmtConfirma(iso) {
            if (!iso) return null;
            return `${iso.substring(8, 10)}/${iso.substring(5, 7)} às ${iso.substring(11, 16)}`;
        }

        // Badge de status geral de confirmação no card JS (espelha o render do servidor)
        function statusConfirmacaoBadge(c) {
            if (c.confirmado) return '<span class="badge bg-success mb-1">✓ Confirmado</span><br>';
            if (c.pre_confirmado_em) return '<span class="badge bg-info text-dark mb-1">Pré-confirmado</span><br>';
            return '<span class="badge bg-warning text-dark mb-1">Pendente confirmação</span><br>';
        }

        // Uma linha "lembrete → confirmação" (espelha o $confirmaLinha do servidor)
        function confirmaLinha(badge, iso, rotulo, cls, status) {
            const em = fmtConfirma(iso);
            let h = `<div class="d-flex align-items-center gap-1 flex-wrap" style="font-size:.72rem">${badge}`;
            if (em) {
                h += `<span class="text-muted">→</span><span class="badge ${cls}" style="font-weight:500">${rotulo} ${em}</span>`;
            } else if (status === 'enviado') {
                h += '<span class="text-muted">→</span><span class="badge bg-secondary" style="font-weight:500">aguardando confirmação</span>';
            }
            return h + '</div>';
        }

        function reenviarLembrete(id, btn) {
            const rotulo = 'Enviar pedido de confirmação agora';
            if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }
            axios.post(`/agenda/${id}/reenviar-lembrete`)
                .then(() => {
                    if (btn) { btn.textContent = 'Enviado ✓'; }
                    setTimeout(() => { if (btn) { btn.disabled = false; btn.textContent = rotulo; } }, 2500);
                })
                .catch((e) => {
                    if (btn) { btn.disabled = false; btn.textContent = rotulo; }
                    alert(e.response?.data?.error ?? 'Erro ao reenviar pedido de confirmação');
                });
        }

        // (removido) fluxo "avisar pedido pronto" — o flag retirada não se aplica à barbearia.

        // ── Usuários ─────────────────────────────────────────────────────────
        function excluirUsuario(id, nome) {
            $('#excluir-usuario-id').val(id);
            $('#excluir-usuario-nome').text(nome);
            setTimeout(() => $('#modal-exc-usuario').modal('show'), 250);
        }

        let users = @json($users->items());

        function editarUsuario(id) {
            const user = users.find(u => u.id == id);
            if (!user) return;

            $('#edt-id').val(id);
            $('#edt-name').val(user.name);
            $('#edt-whatsapp').val(user.whatsapp ?? '');

            // Resetar todos os radios antes de marcar o correto
            $('[name="tipo_edt"]').prop('checked', false);
            if (user.adm == 1) {
                $('#tipo_edt_adm').prop('checked', true);
            } else if (user.func == 1) {
                $('#tipo_edt_func').prop('checked', true);
            } else {
                $('#tipo_edt_cli').prop('checked', true);
            }

            // Serviços contratados só fazem sentido para clientes
            const ehCliente = user.adm != 1 && user.func != 1;
            const secCreditos = document.getElementById('creditos-section');
            if (ehCliente) {
                secCreditos.classList.remove('d-none');
                carregarCreditos(id);
            } else {
                secCreditos.classList.add('d-none');
            }

            $('#modal-edt-usuario').modal('show');
        }

        // ── Serviços contratados (créditos) ──────────────────────────────────
        function carregarCreditos(userId) {
            axios.get(`/api/clientes/${userId}/creditos`)
                .then(res => renderCreditos(res.data))
                .catch(() => {
                    document.getElementById('creditos-lista').innerHTML =
                        '<p class="text-danger small mb-0">Falha ao carregar serviços contratados.</p>';
                });
        }

        function renderCreditos(lista) {
            const box = document.getElementById('creditos-lista');
            const wa = @json($whatsappAdmin);
            if (!lista || lista.length === 0) {
                box.innerHTML = '<p class="text-muted small mb-0">Nenhum serviço contratado.</p>';
                return;
            }
            box.innerHTML = lista.map(c => {
                const resto = c.negociar
                    ? `<a class="badge bg-warning text-dark text-decoration-none" target="_blank" rel="noopener" href="https://wa.me/${wa}?text=${encodeURIComponent('Olá! Quero renegociar meu pacote de ' + c.descricao + ' (expirado).')}">Negociar</a>`
                    : `<span class="badge ${c.restantes > 0 ? 'bg-success' : 'bg-danger'}">resta ${c.restantes}</span>`;
                return `
                <div class="d-flex align-items-center justify-content-between border rounded px-2 py-1 mb-1">
                    <span class="small">
                        ${c.descricao}
                        <span class="badge bg-secondary">${c.usadas}/${c.quantidade} usadas</span>
                        ${resto}
                        ${c.expira_em ? `<span class="text-muted">expira ${c.expira_em}${c.expirado ? ' (expirado)' : ''}</span>` : ''}
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removerCredito(${c.id})" title="Remover">&times;</button>
                </div>`;
            }).join('');
        }

        function adicionarCredito() {
            const userId     = document.getElementById('edt-id').value;
            const servico_id = document.getElementById('credito-servico').value;
            const quantidade = parseInt(document.getElementById('credito-qtd').value) || 1;
            if (!userId || !servico_id) return;
            axios.post(`/clientes/${userId}/creditos`, { servico_id, quantidade })
                .then(() => {
                    document.getElementById('credito-qtd').value = 1;
                    carregarCreditos(userId);
                })
                .catch(err => alert(err.response?.data?.message ?? 'Erro ao adicionar serviço.'));
        }

        function removerCredito(creditoId) {
            const userId = document.getElementById('edt-id').value;
            if (!userId) return;
            if (!confirm('Remover este pacote contratado do cliente?')) return;
            axios.delete(`/clientes/${userId}/creditos/${creditoId}`)
                .then(() => carregarCreditos(userId))
                .catch(() => alert('Erro ao remover pacote.'));
        }

        // Pacotes do cliente atualmente carregados (alimentam o select de serviço e o "Descontar de").
        let creditosClienteAtual = [];

        // Limpa a seleção de dia/horário (a duração depende do serviço escolhido).
        function resetSelecaoHorario() {
            document.getElementById('horarios-erro').textContent = '';
            document.getElementById('horarios').innerHTML = '';
            document.querySelectorAll('.cal-dia').forEach(d => d.classList.remove('cal-selecionado'));
            document.getElementById('dia_selecionado').value  = '';
            document.getElementById('hora_selecionada').value = '';
            document.getElementById('data_inicio').value      = '';
            document.getElementById('data_fim').value         = '';
        }

        // Sincroniza os campos ocultos servico_id / credito_id conforme o modo:
        // - especial + pacote → servico_id vem do próprio pacote do "Descontar de"; credito_id = pacote
        // - especial + livre  → servico_id vem do catálogo (#servico_livre); credito_id vazio
        // - comum             → vêm do pacote escolhido no select (valor "c-{pacoteId}")
        function sincronizarAgendamento() {
            const chk = document.getElementById('chk_especial');
            if (chk && chk.checked) {
                const alvo = document.getElementById('credito_alvo').value;
                if (alvo) {
                    const c = creditosClienteAtual.find(x => x.id == alvo);
                    document.getElementById('servico_id').value = c ? String(c.servico_id) : '';
                    document.getElementById('credito_id').value = alvo;
                } else {
                    document.getElementById('servico_id').value = document.getElementById('servico_livre').value;
                    document.getElementById('credito_id').value = '';
                }
                return;
            }
            const sel = document.getElementById('servico_sel');
            let servicoId = '', creditoId = '';
            if (sel && sel.value.startsWith('c-')) {
                creditoId = sel.value.slice(2);
                const c = creditosClienteAtual.find(x => x.id == creditoId);
                servicoId = c ? String(c.servico_id) : '';
            }
            document.getElementById('servico_id').value = servicoId;
            document.getElementById('credito_id').value = creditoId;
        }

        // Mostra/esconde os campos conforme o checkbox "Especial". O catálogo de
        // serviço (encaixe livre) só aparece no especial quando não há pacote a descontar.
        function aplicarModoEspecial() {
            const chk = document.getElementById('chk_especial');
            const sel = document.getElementById('servico_sel');
            const alvo = document.getElementById('credito_alvo');
            const wrapNormal = document.getElementById('servico-normal-wrap');
            const wrapAlvo   = document.getElementById('credito-alvo-wrap');
            const wrapLivre  = document.getElementById('servico-livre-wrap');
            const wrapDur    = document.getElementById('duracao-especial-wrap');
            const especial = !!(chk && chk.checked);
            // Sincroniza a flag enviada no POST (o checkbox não tem name próprio).
            const flag = document.getElementById('especial_flag');
            if (flag) flag.value = especial ? '1' : '0';
            const semDesconto = especial && !(alvo && alvo.value);
            if (wrapNormal) wrapNormal.classList.toggle('d-none', especial);
            if (sel) sel.required = !especial;
            if (wrapAlvo) wrapAlvo.classList.toggle('d-none', !especial);
            if (wrapLivre) wrapLivre.classList.toggle('d-none', !semDesconto);
            if (wrapDur) wrapDur.classList.toggle('d-none', !especial);
            sincronizarAgendamento();
        }

        // Popula o select de serviço comum (um item por PACOTE de serviço visível com
        // saldo) e o "Descontar de" (qualquer pacote com saldo + encaixe livre).
        // forcar = {servicoId, creditoId} reabre um agendamento existente no modo
        // certo: comum (pacote no select) ou especial (checkbox + "Descontar de").
        function popularSelectServicos(userId, forcar, cb) {
            const sel  = document.getElementById('servico_sel');
            const alvo = document.getElementById('credito_alvo');
            const chk  = document.getElementById('chk_especial');
            if (!sel) { if (cb) cb(); return; }
            if (!userId) {
                creditosClienteAtual = [];
                sel.innerHTML = '<option value="">Selecione o cliente primeiro</option>';
                if (alvo) alvo.innerHTML = '<option value="">Não descontar (encaixe livre)</option>';
                if (chk) chk.checked = false;
                aplicarModoEspecial();
                if (cb) cb();
                return;
            }
            axios.get(`/api/clientes/${userId}/creditos`).then(res => {
                creditosClienteAtual = res.data;
                const comSaldo = res.data.filter(c => c.restantes > 0);

                // O número exibido é a PRÓXIMA unidade a consumir (1ª, 2ª, ... de N),
                // ou seja usadas+1 — coerente com o que o card mostrará após agendar.
                // Select de serviço comum: todos os pacotes com saldo
                let opts = '<option value="">Selecione o serviço</option>';
                comSaldo.forEach(c => {
                    opts += `<option value="c-${c.id}">${c.descricao} (${c.usadas + 1}/${c.quantidade})</option>`;
                });

                // "Descontar de": qualquer pacote com saldo (de qualquer serviço) + encaixe livre
                let optsAlvo = '<option value="">Não descontar (encaixe livre)</option>';
                comSaldo.forEach(c => {
                    optsAlvo += `<option value="${c.id}">${c.descricao} (${c.usadas + 1}/${c.quantidade})</option>`;
                });

                // Edição: o agendamento é especial conforme a flag salva no agendamento.
                const ehEspecial = !!(forcar && forcar.especial);

                // Em edição o pacote pode já estar esgotado por ESTA consulta; usa o
                // ordinal real da consulta (vindo do backend) em vez de usadas+1.
                const ordForcado = c => (forcar && forcar.ordinal)
                    ? forcar.ordinal
                    : Math.min(c.usadas + 1, c.quantidade);

                // Comum em edição: garante a opção do pacote atual mesmo sem saldo
                if (forcar && forcar.servicoId && !ehEspecial && forcar.creditoId
                    && !opts.includes(`value="c-${forcar.creditoId}"`)) {
                    const c = creditosClienteAtual.find(x => x.id == forcar.creditoId);
                    const s = SERVICOS.find(x => x.id == forcar.servicoId);
                    const label = c ? `${c.descricao} (${ordForcado(c)}/${c.quantidade})` : (s ? s.descricao : 'Serviço atual');
                    opts += `<option value="c-${forcar.creditoId}">${label}</option>`;
                }
                // Especial em edição que descontava de um pacote: garante a opção no "Descontar de"
                if (ehEspecial && forcar.creditoId && !optsAlvo.includes(`value="${forcar.creditoId}"`)) {
                    const c = creditosClienteAtual.find(x => x.id == forcar.creditoId);
                    const label = c ? `${c.descricao} (${ordForcado(c)}/${c.quantidade})` : 'Pacote atual';
                    optsAlvo += `<option value="${forcar.creditoId}">${label}</option>`;
                }

                sel.innerHTML = opts;
                if (alvo) alvo.innerHTML = optsAlvo;
                if (chk) chk.checked = !!ehEspecial;

                if (ehEspecial) {
                    if (alvo) alvo.value = forcar.creditoId ? String(forcar.creditoId) : '';
                    // Encaixe livre (sem desconto): reabre o serviço no catálogo
                    if (!forcar.creditoId) {
                        const livre = document.getElementById('servico_livre');
                        if (livre && forcar.servicoId) livre.value = String(forcar.servicoId);
                    }
                } else if (forcar && forcar.creditoId) {
                    sel.value = `c-${forcar.creditoId}`;
                }

                aplicarModoEspecial();
                if (cb) cb();
            }).catch(() => { creditosClienteAtual = []; aplicarModoEspecial(); if (cb) cb(); });
        }

        // Ao trocar o cliente no Novo Agendamento, recarrega os pacotes disponíveis e reinicia a seleção.
        document.getElementById('user_id')?.addEventListener('change', function () {
            popularSelectServicos(this.value);
            resetSelecaoHorario();
        });

        // Ao trocar o pacote no select de serviço comum, sincroniza os campos ocultos
        // e reinicia a seleção de dia/horário (o serviço define a duração dos slots).
        document.getElementById('servico_sel')?.addEventListener('change', function () {
            sincronizarAgendamento();
            resetSelecaoHorario();
        });

        // Ao marcar/desmarcar "Serviço especial": alterna os campos e reinicia o horário.
        // Ao marcar, deixa o "Descontar de" vazio para obrigar o funcionário a escolher
        // conscientemente de qual pacote descontar (ou "Não descontar").
        document.getElementById('chk_especial')?.addEventListener('change', function () {
            if (this.checked) {
                const alvo = document.getElementById('credito_alvo');
                if (alvo) alvo.value = '';
            }
            aplicarModoEspecial();
            resetSelecaoHorario();
        });

        // Ao trocar o pacote no "Descontar de" (especial): sincroniza servico/credito,
        // alterna o catálogo de encaixe livre e reinicia o horário (o serviço pode mudar
        // a duração dos slots).
        document.getElementById('credito_alvo')?.addEventListener('change', function () {
            aplicarModoEspecial();
            resetSelecaoHorario();
        });

        // Encaixe livre: ao escolher o serviço no catálogo, sincroniza e reinicia o horário.
        document.getElementById('servico_livre')?.addEventListener('change', function () {
            sincronizarAgendamento();
            resetSelecaoHorario();
        });

        // Duração personalizada (especial): muda a duração dos slots, reinicia o horário.
        document.getElementById('duracao_especial')?.addEventListener('change', function () {
            resetSelecaoHorario();
        });

        // ── Auto-atualização parcial (só funcionário/adm) ────────────────────
        // Atualiza avisos e consultas via axios (sem recarregar a página), para
        // não perder dados de um cadastro/edição em andamento.
        function atualizarBadgeAvisos(count) {
            const header = document.querySelector('[data-bs-target="#collapseAvisos"]');
            if (!header) return;
            let badge = header.querySelector('.badge');
            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge bg-warning text-dark';
                    header.appendChild(badge);
                }
                badge.textContent = count;
            } else if (badge) {
                badge.remove();
            }
        }

        function atualizarAvisos() {
            axios.get('/avisos-parcial')
                .then(res => {
                    const lista = document.getElementById('avisos-lista');
                    if (lista && typeof res.data.html === 'string') lista.innerHTML = res.data.html;
                    atualizarBadgeAvisos(res.data.count ?? 0);
                })
                .catch(() => {});
        }

        function atualizarConsultas() {
            // não mexe enquanto o funcionário digita no filtro
            if (document.activeElement === document.getElementById('search-consulta')) return;
            const termo = document.getElementById('search-consulta')?.value ?? '';
            axios.get('/agenda-search', { params: { q: termo } })
                .then(res => {
                    // preserva exatamente o estado (aberto/fechado) dos acordeões (mês/dia)
                    const abertos = new Set();
                    document.querySelectorAll('#accordionMeses .collapse.show').forEach(el => abertos.add(el.id));
                    renderAccordionConsultas(res.data);
                    // reconcilia nos dois sentidos: reabre os que estavam abertos e fecha os demais
                    // (evita que o dia de hoje, forçado a 'show' no render, reabra depois de fechado)
                    document.querySelectorAll('#accordionMeses .collapse').forEach(el => {
                        const deveAbrir = abertos.has(el.id);
                        el.classList.toggle('show', deveAbrir);
                        const btn = document.querySelector(`[data-bs-target="#${el.id}"]`);
                        if (btn) btn.classList.toggle('collapsed', !deveAbrir);
                    });
                })
                .catch(() => {});
        }

        // A cada 30s: atualização parcial, não destrói formulários/modais abertos.
        setInterval(function () {
            atualizarAvisos();
            atualizarConsultas();
        }, 30000);

        let searchUsuarioTimeout = null;
        let searchUsuarioTermoAtual = '';

        function searchUsuario(page = 1) {
            clearTimeout(searchUsuarioTimeout);
            searchUsuarioTermoAtual = document.getElementById('search').value;
            searchUsuarioTimeout = setTimeout(() => {
                axios.get('/usuarios-search', { params: { q: searchUsuarioTermoAtual, page } })
                    .then(res => renderTabelaUsuarios(res.data));
            }, page === 1 ? 300 : 0);
        }

        function renderTabelaUsuarios(paginated) {
            users = paginated.data;
            const tbody = document.getElementById('tbody-usuarios');
            const celulaAcoes = (id, nome) => `<td class="d-flex">
                        <svg style="cursor:pointer" onclick="excluirUsuario(${id},'${nome.replace(/'/g,"\\'")}') " xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#dc3545"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                        <svg style="cursor:pointer" onclick="editarUsuario(${id})" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#0d6efd"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg>
                    </td>`;

            tbody.innerHTML = paginated.data.map(user => {
                // Mesma proteção do blade: super admin (id=1) só é editável por id=1;
                // colaborador (func) só é editável por admin.
                let acoes;
                if (user.id != 1 || AUTH_ID == 1) {
                    acoes = (user.func != 1 || AUTH_ADM) ? celulaAcoes(user.id, user.name) : '<td>COLAB</td>';
                } else {
                    acoes = '<td>ADM</td>';
                }
                return `<tr>
                    ${acoes}
                    <td>${user.name}</td>
                    <td>${user.whatsapp ?? '—'}</td>
                    <td>${user.adm > 0 ? 'Sim' : 'Não'}</td>
                    <td>${user.func > 0 ? 'Sim' : 'Não'}</td>
                </tr>`;
            }).join('');

            renderPaginacaoUsuarios(paginated);
        }

        function renderPaginacaoUsuarios(paginated) {
            const container = document.querySelector('#collapseUsuarios .d-flex.justify-content-end');
            if (!container) return;

            const { current_page, last_page } = paginated;
            if (last_page <= 1) { container.innerHTML = ''; return; }

            let html = '<nav><ul class="pagination pagination-sm mb-0">';

            html += `<li class="page-item ${current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="event.preventDefault(); searchUsuario(${current_page - 1})">‹</a>
            </li>`;

            for (let p = 1; p <= last_page; p++) {
                html += `<li class="page-item ${p === current_page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="event.preventDefault(); searchUsuario(${p})">${p}</a>
                </li>`;
            }

            html += `<li class="page-item ${current_page === last_page ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="event.preventDefault(); searchUsuario(${current_page + 1})">›</a>
            </li>`;

            html += '</ul></nav>';
            container.innerHTML = html;
        }

        // ── Serviços ──────────────────────────────────────────────────────────
        function excluirServico(id, nome) {
            $('#excluir-servico-id').val(id);
            $('#excluir-servico-nome').text(nome);
            setTimeout(() => $('#modal-exc-servico').modal('show'), 250);
        }

        let servicos = @json($servicos);
        function editarServico(id) {
            const servico = servicos.find(s => s.id == id);
            if (!servico) return;

            $('#id_edt_servico').val(id);
            $('#descricao_edt_servico').val(servico.descricao);
            $('#duracao_h_edt_servico').val(servico.duracao.split(':')[0].padStart(2, '0'));
            $('#duracao_m_edt_servico').val(servico.duracao.split(':')[1].padStart(2, '0'));
            $('#valor_edt_servico').val(servico.valor ?? '');

            $('[name="status_servico"]').prop('checked', false);
            $(`#status_servico_${servico.status}`).prop('checked', true);

            $('#recorrente_edt_servico').prop('checked', !!servico.recorrente);

            $('#modal-edt-servico').modal('show');
        }

        // ── Calendário de gestão de disponibilidade ──────────────────────────
        let mgmDataAtual       = new Date();
        let mgmDiasDisponiveis = [];

        // Barbeiro cuja grade está sendo gerenciada (func → próprio; adm → selecionado).
        function mgmFid() {
            const sel = document.getElementById('mgm-barbeiro');
            return sel ? sel.value : '';
        }

        const mgmCalStorageKey = 'mgm_cal_mes_ano';
        const mgmSavedMes = sessionStorage.getItem(mgmCalStorageKey);
        if (mgmSavedMes) {
            const [mgmAnoSaved, mgmMesSaved] = mgmSavedMes.split('-').map(Number);
            mgmDataAtual = new Date(mgmAnoSaved, mgmMesSaved, 1);
        }

        async function mgmCarregarMes() {
            const ano = mgmDataAtual.getFullYear();
            const mes = mgmDataAtual.getMonth() + 1;
            sessionStorage.setItem(mgmCalStorageKey, `${ano}-${mgmDataAtual.getMonth()}`);
            try {
                const res = await axios.get(`/api/dias-disponiveis/${ano}/${mes}?funcionario_id=${mgmFid()}`);
                mgmDiasDisponiveis = res.data; // array de números de dia
            } catch(e) {
                mgmDiasDisponiveis = [];
            }
            mgmGerarCalendario();
        }

        function mgmGerarCalendario() {
            const ano = mgmDataAtual.getFullYear();
            const mes = mgmDataAtual.getMonth();

            const nomeMes = mgmDataAtual.toLocaleString('pt-BR', { month: 'long' });
            document.getElementById('mgm-cal-mes-ano').textContent =
                `${nomeMes.charAt(0).toUpperCase() + nomeMes.slice(1)} ${ano}`;

            const primeiroDia  = new Date(ano, mes, 1);
            const ultimoDia    = new Date(ano, mes + 1, 0);
            const inicioSemana = primeiroDia.getDay();
            const totalDias    = ultimoDia.getDate();

            const grid = document.getElementById('mgm-cal-dias');
            grid.innerHTML = '';

            // Dias do mês anterior
            const ultimoDiaMesAnt = new Date(ano, mes, 0).getDate();
            for (let i = inicioSemana - 1; i >= 0; i--) {
                const dia = ultimoDiaMesAnt - i;
                const div = document.createElement('div');
                div.classList.add('cal-dia', 'disabled');
                div.textContent = dia;
                const ds = new Date(ano, mes - 1, dia).getDay();
                if (ds === 0 || ds === 6) div.classList.add('cal-fds');
                grid.appendChild(div);
            }

            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);

            for (let d = 1; d <= totalDias; d++) {
                const div      = document.createElement('div');
                const dataDiv  = new Date(ano, mes, d);
                const diaSem   = dataDiv.getDay();
                div.classList.add('cal-dia');
                div.textContent = d;

                if (diaSem === 0 || diaSem === 6) div.classList.add('cal-fds');

                const passado = dataDiv < hoje;

                if (passado) {
                    div.style.opacity = '0.55';
                } else if (mgmDiasDisponiveis.includes(d)) {
                    div.classList.add('cal-disponivel');
                } else {
                    div.classList.add('cal-bloqueado');
                }

                div.style.cursor = 'pointer';
                const mesF = String(mes + 1).padStart(2, '0');
                const diaF = String(d).padStart(2, '0');
                div.onclick = () => mgmAbrirModal(`${ano}-${mesF}-${diaF}`, passado);

                grid.appendChild(div);
            }

            // Completar grade
            const totalCelulas = inicioSemana + totalDias;
            const resto = totalCelulas % 7;
            if (resto !== 0) {
                for (let d = 1; d <= 7 - resto; d++) {
                    const div = document.createElement('div');
                    div.classList.add('cal-dia', 'disabled');
                    div.textContent = d;
                    const ds = new Date(ano, mes + 1, d).getDay();
                    if (ds === 0 || ds === 6) div.classList.add('cal-fds');
                    grid.appendChild(div);
                }
            }

            // Botões de semana acima do calendário (um por semana do mês; passadas desabilitadas).
            const numLinhas = Math.ceil((inicioSemana + totalDias) / 7);
            const semanas = [];
            for (let i = 0; i < numLinhas; i++) {
                const dom = new Date(ano, mes, 1 - inicioSemana + i * 7);
                const sab = new Date(ano, mes, 1 - inicioSemana + i * 7 + 6);
                semanas.push({ dom, sab, passado: sab < hoje });
            }
            mgmRenderBotoesSemanas(semanas);
        }

        // Botões "agenda da semana" ACIMA do calendário — um por semana do mês;
        // semanas totalmente passadas (sábado < hoje) ficam desabilitadas.
        function mgmRenderBotoesSemanas(semanas) {
            const cont = document.getElementById('mgm-week-btns-top');
            if (!cont) return;
            cont.innerHTML = '';
            const iso = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            const rot = d => `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}`;
            semanas.forEach(({ dom, sab, passado }) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm ' + (passado ? 'btn-outline-secondary' : 'btn-outline-primary') + ' mgm-btn-semana';
                btn.textContent = `Semana ${rot(dom)}`;
                btn.title = `${rot(dom)} – ${rot(sab)}`;
                if (passado) { btn.disabled = true; }
                else { btn.addEventListener('click', () => mgmAbrirSemana(iso(dom), btn)); }
                cont.appendChild(btn);
            });
        }

        async function mgmAbrirSemana(domingo, btn) {
            const original = btn.textContent;
            btn.disabled = true; btn.textContent = 'Enviando...';
            try {
                const { data } = await axios.post('/api/disponibilidade/semana/' + domingo, { funcionario_id: mgmFid() });
                alert('Semana preenchida (' + (data.inicio || '') + '–' + (data.fim || '') + ').\n\nPreenchidos: ' + data.preenchidos.length + '\nIgnorados (já configurados): ' + data.ignorados.length);
                await mgmCarregarMes();
            } catch (e) {
                alert(e.response?.data?.error ?? 'Falha ao preencher a semana.');
                btn.disabled = false; btn.textContent = original;
            }
        }

        document.getElementById('mgm-cal-prev').addEventListener('click', () => {
            mgmDataAtual.setMonth(mgmDataAtual.getMonth() - 1);
            mgmCarregarMes();
        });
        document.getElementById('mgm-cal-next').addEventListener('click', () => {
            mgmDataAtual.setMonth(mgmDataAtual.getMonth() + 1);
            mgmCarregarMes();
        });
        document.getElementById('mgm-cal-today').addEventListener('click', () => {
            mgmDataAtual = new Date();
            sessionStorage.removeItem(mgmCalStorageKey);
            mgmCarregarMes();
        });

        // ── Modal de slots ────────────────────────────────────────────────────
        let mgmModoLeitura = false;

        function mgmAbrirModal(data, readonly = false) {
            mgmModoLeitura = readonly;

            const dtObj = new Date(`${data}T00:00:00`);
            const titulo = dtObj.toLocaleDateString('pt-BR', {
                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
            });

            document.getElementById('mgm-modal-titulo').textContent =
                (titulo.charAt(0).toUpperCase() + titulo.slice(1))
                + (readonly ? ' — somente leitura' : '');

            document.getElementById('mgm-data-atual').value = data;
            document.getElementById('mgm-slots-container').innerHTML =
                '<p class="text-muted">Carregando...</p>';

            const btnSalvar  = document.getElementById('mgm-btn-salvar');
            const btnPresets = document.querySelectorAll('[onclick^="mgmPreset"]');
            btnSalvar.style.display  = readonly ? 'none' : '';
            btnPresets.forEach(b => b.style.display = readonly ? 'none' : '');

            new bootstrap.Modal(document.getElementById('modal-gerenciar-slots')).show();

            axios.get(`/api/disponibilidade/${data}?funcionario_id=${mgmFid()}`)
                .then(res => mgmRenderSlots(res.data))
                .catch(() => {
                    document.getElementById('mgm-slots-container').innerHTML =
                        '<p class="text-danger">Erro ao carregar os horários.</p>';
                });
        }

        function mgmRenderSlots(slots) {
            const container = document.getElementById('mgm-slots-container');
            container.innerHTML = '';

            slots.forEach(slot => {
                const id      = `mgm-slot-${slot.hora.replace(':', '-')}`;
                const agends  = slot.agendamentos ?? [];
                // Serviços de staff são "transparentes": não bloqueiam a disponibilidade do slot.
                const bloqueadoPorAgend = agends.some(a => !a.is_staff);

                const row = document.createElement('div');
                row.className = 'slot-row d-flex align-items-center py-2 border-bottom flex-wrap';
                row.style.minHeight = '44px';

                const check = document.createElement('input');
                check.type      = 'checkbox';
                check.className = 'form-check-input me-3 mgm-slot-check flex-shrink-0';
                check.id        = id;
                check.value     = slot.hora;
                check.checked   = slot.disponivel;
                if (mgmModoLeitura || bloqueadoPorAgend) check.disabled = true;
                if (bloqueadoPorAgend) check.title = 'Horário bloqueado — há um agendamento';

                const label = document.createElement('label');
                label.htmlFor   = id;
                label.className = 'me-3 flex-shrink-0';
                label.style.cssText = 'min-width:52px; font-family:monospace; font-size:1rem';
                label.textContent = slot.hora;

                row.appendChild(check);
                row.appendChild(label);

                agends.forEach(agend => {
                    const badge = document.createElement('span');
                    badge.className   = `badge ${agend.is_staff ? 'bg-danger' : 'bg-success'} text-wrap text-start me-1`;
                    badge.style.fontSize = '0.8rem';
                    badge.textContent = `${agend.paciente} — ${agend.servico} (${agend.inicio}–${agend.fim})`;
                    row.appendChild(badge);
                });

                container.appendChild(row);
            });
        }

        function mgmPreset(tipo) {
            const checks = document.querySelectorAll('.mgm-slot-check:not(:disabled)');
            let ini = null, fimV = null;
            if (tipo === 'comercial') {
                ini  = (document.getElementById('mgm-comercial-inicio') || {}).value || '08:00';
                fimV = (document.getElementById('mgm-comercial-fim') || {}).value || '17:45';
            }
            checks.forEach(cb => {
                if (tipo === 'tudo')      cb.checked = true;
                else if (tipo === 'limpar') cb.checked = false;
                else if (tipo === 'comercial') {
                    cb.checked = cb.value >= ini && cb.value <= fimV;
                }
            });
            // Salva o horário comercial editável (persiste no servidor)
            if (tipo === 'comercial' && ini && fimV) {
                axios.post('/api/agenda/horario-comercial', { inicio: ini, fim: fimV }).catch(() => {});
            }
        }

        async function mgmSalvarSlots() {
            const data  = document.getElementById('mgm-data-atual').value;
            const slots = [...document.querySelectorAll('.mgm-slot-check:checked')]
                .map(cb => cb.value);

            document.getElementById('mgm-btn-salvar').disabled = true;

            try {
                await axios.post(`/api/disponibilidade/${data}`, { slots, funcionario_id: mgmFid() });
                location.reload();
            } catch(e) {
                alert('Erro ao salvar disponibilidade.');
                document.getElementById('mgm-btn-salvar').disabled = false;
            }
        }

        // Inicializar calendário de gestão ao carregar a página
        document.addEventListener('DOMContentLoaded', () => {
            mgmCarregarMes();

            // Horário comercial: salva globalmente ao alterar os inputs (debounce 400ms).
            let comercialTimer = null;
            ['mgm-comercial-inicio', 'mgm-comercial-fim'].forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('change', () => {
                    clearTimeout(comercialTimer);
                    comercialTimer = setTimeout(() => {
                        const ini = (document.getElementById('mgm-comercial-inicio') || {}).value;
                        const fim = (document.getElementById('mgm-comercial-fim') || {}).value;
                        if (ini && fim) axios.post('/api/agenda/horario-comercial', { inicio: ini, fim: fim }).catch(() => {});
                    }, 400);
                });
            });

            @if($errors->any() && old('data_inicio'))
                // Repopula o select de serviços com os pacotes do cliente antes de reabrir
                popularSelectServicos(@json(old('user_id')), {
                    servicoId: @json(old('servico_id')),
                    creditoId: @json(old('credito_id')),
                });
                new bootstrap.Modal(document.getElementById('modal-add-agenda')).show();
            @endif
        });

        // ── Busca de consultas ────────────────────────────────────────────────
        const hojeStr = '{{ now()->toDateString() }}';
        let consultaTimeout = null;
        function searchConsulta() {
            clearTimeout(consultaTimeout);
            consultaTimeout = setTimeout(() => {
                const termo = document.getElementById('search-consulta').value;
                axios.get('/agenda-search', { params: { q: termo } })
                    .then(res => renderAccordionConsultas(res.data));
            }, 300);
        }

        function renderAccordionConsultas(data) {
            const accordion = document.getElementById('accordionMeses');
            accordion.innerHTML = '';

            Object.keys(data).forEach(mes => {
                const id = mes.toLowerCase().replace(/\s+/g, '-');

                // Agrupar por dia
                const diasMap = {};
                data[mes].forEach(c => {
                    const dataKey = c.data_inicio.substring(0, 10);
                    if (!diasMap[dataKey]) diasMap[dataKey] = [];
                    diasMap[dataKey].push(c);
                });

                let html = `
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button"
                                data-bs-toggle="collapse" data-bs-target="#collapse-${id}">
                                ${mes.charAt(0).toUpperCase() + mes.slice(1)}
                            </button>
                        </h2>
                        <div id="collapse-${id}" class="accordion-collapse collapse" data-bs-parent="#accordionMeses">
                            <div class="accordion-body">
                `;

                Object.keys(diasMap).forEach(dataKey => {
                    const consultasDia = diasMap[dataKey];
                    const isHoje = dataKey === hojeStr;
                    const dtObj  = new Date(dataKey + 'T00:00:00');
                    const diaLabel = dtObj.toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' });
                    const diaLabelCap = diaLabel.charAt(0).toUpperCase() + diaLabel.slice(1);
                    const idDia = 'dia-' + dataKey;

                    html += `
                        <div class="mb-2">
                            <button class="btn btn-sm w-100 text-start d-flex align-items-center gap-2 ${isHoje ? 'btn-primary' : 'btn-outline-secondary'}"
                                    data-bs-toggle="collapse" data-bs-target="#${idDia}">
                                <span>${diaLabelCap}</span>
                                <span class="badge ${isHoje ? 'bg-light text-dark' : 'bg-secondary'} ms-auto">${consultasDia.length}</span>
                            </button>
                            <div id="${idDia}" class="collapse ${isHoje ? 'show' : ''} pt-2">
                    `;

                    consultasDia.forEach(c => {
                        const inicio = c.data_inicio.substring(11, 16);
                        const fim    = c.data_fim.substring(11, 16);
                        const dia    = c.data_inicio.substring(8, 10);

                        consultaMap[c.id] = { ...c, mes, inicio, fim, dia };

                        const nomePaciente = document.createElement('span');
                        nomePaciente.textContent = c.user.name;
                        // servico_display já inclui o pacote descontado e o saldo: "Serviço X (2/5)"
                        const nomeServico = document.createElement('span');
                        nomeServico.textContent = c.servico_display ?? (c.servico?.descricao ?? '');

                        html += `
                            <div class="consulta-card ${c.confirmado ? '' : 'consulta-pendente'} ${c.especial ? 'consulta-especial' : ''} d-flex justify-content-between align-items-center" data-consulta-id="${c.id}">
                                <div class="d-flex flex-grow-1" onclick="editarConsulta(${c.id})" style="cursor:pointer">
                                    <div class="me-3 text-center">
                                        <div class="consulta-hora">${inicio} <br>às<br> ${fim}</div>
                                        ${c.especial ? '<div class="mt-2"><span class="badge bg-danger">Horário especial</span></div>' : ''}
                                    </div>
                                    <div>
                                        ${statusConfirmacaoBadge(c)}
                                        <strong>Cliente:</strong> ${nomePaciente.textContent}<br>
                                        <strong>Serviço:</strong> ${nomeServico.textContent}
                                        <div class="d-flex flex-column gap-1 mt-2">
                                            ${confirmaLinha(lembreteBadge(c.lembrete_24h, 'Véspera', c.pre_confirmado_em ? 'bg-info text-dark' : 'bg-success'), c.pre_confirmado_em, 'Pré-confirmou em:', 'bg-info text-dark', c.lembrete_24h)}
                                            ${confirmaLinha(lembreteBadge(c.lembrete_2h, '2h antes'), c.confirmado_em, 'Confirmou em:', 'bg-success', c.lembrete_2h)}
                                        </div>
                                    </div>
                                </div>
                                ${!c.confirmado
                                    ? `<button class="btn btn-sm btn-success btn-confirmar ms-2" onclick="event.stopPropagation(); confirmarConsulta(${c.id})">Confirmar</button><button class="btn btn-sm btn-outline-primary btn-reenviar ms-2" title="Reenviar pedido de confirmação no WhatsApp" onclick="event.stopPropagation(); reenviarLembrete(${c.id}, this)">Reenviar pedido de confirmação</button>`
                                    : ''}
                                <button class="btn btn-sm btn-danger ms-2"
                                    onclick="event.stopPropagation(); excluirConsultaById(${c.id})">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                                </button>
                            </div>
                        `;
                    });

                    html += `</div></div>`;
                });

                html += `</div></div></div>`;
                accordion.innerHTML += html;
            });
        }

        // ── Estimativa de valor líquido (taxa InfinitePay) no modal de ordem
        (function () {
            const input   = document.getElementById('ordem_valor');
            const box     = document.getElementById('ordem_liquido');
            const elTaxa  = document.getElementById('ordem_liquido_taxa');
            const elValor = document.getElementById('ordem_liquido_valor');
            if (!input || !box) return;

            const taxa = parseFloat(input.dataset.taxa) || 0;

            const fmtBRL = (n) => 'R$ ' + n.toLocaleString('pt-BR', {
                minimumFractionDigits: 2, maximumFractionDigits: 2,
            });

            const recalcular = () => {
                const valor = parseFloat(input.value);
                if (!valor || valor <= 0 || !taxa) {
                    box.style.display = 'none';
                    return;
                }
                const desconto = valor * (taxa / 100);
                elTaxa.textContent  = '-' + fmtBRL(desconto);
                elValor.textContent = fmtBRL(valor - desconto);
                box.style.display = 'block';
            };

            input.addEventListener('input', recalcular);
            recalcular();
        })();
    </script>
    @else
    <script>
        function abrirModalAgendamento(agendamentoId, servicoId, servicoNome, funcionarioId) {
            document.getElementById('agendamento_id').value       = agendamentoId ?? '';
            document.getElementById('servico_id').value           = servicoId ?? '';
            document.getElementById('servico_nome_display').value = servicoNome ?? '';
            const fReag = document.getElementById('funcionario_id_reagendar');
            if (fReag) fReag.value = funcionarioId ?? '';
            document.querySelectorAll('.cal-dia').forEach(d => d.classList.remove('cal-selecionado'));
            document.getElementById('dia_selecionado').value  = '';
            document.getElementById('hora_selecionada').value = '';
            document.getElementById('data_inicio').value      = '';
            document.getElementById('data_fim').value         = '';
            document.getElementById('horarios').innerHTML     = '';
            document.getElementById('horarios-erro').textContent = '';
            dataAtual = new Date();
            carregarMes();
            new bootstrap.Modal(document.getElementById('modal-reagendar')).show();
        }

        function reagendarConsulta(id, servicoId, servicoNome, ehStaff, funcionarioId) {
            if (ehStaff) {
                new bootstrap.Modal(document.getElementById('modal-horario-especial')).show();
                return;
            }
            abrirModalAgendamento(id, servicoId, servicoNome, funcionarioId);
        }

        function cancelarConsulta(id, servicoId, servicoNome, ehStaff, funcionarioId) {
            document.getElementById('modal-acao-titulo').textContent = 'O que deseja fazer?';
            document.getElementById('modal-acao-info').textContent   = 'Você pode reagendar para outro horário ou cancelar definitivamente.';
            document.getElementById('btn-acao-reagendar').textContent = 'Reagendar';
            document.getElementById('btn-acao-excluir').textContent   = 'Cancelar sem reagendar';

            const modalAcao = new bootstrap.Modal(document.getElementById('modal-acao-consulta'));

            document.getElementById('btn-acao-reagendar').onclick = () => {
                modalAcao.hide();
                reagendarConsulta(id, servicoId, servicoNome, ehStaff, funcionarioId);
            };

            document.getElementById('btn-acao-excluir').onclick = () => {
                document.getElementById('modal-confirmar-titulo').textContent = 'Cancelar agendamento';
                document.getElementById('modal-confirmar-info').textContent   = 'Tem certeza que deseja cancelar este agendamento? O funcionário será notificado.';
                document.getElementById('btn-confirmar-excluir').textContent  = 'Confirmar cancelamento';

                const modalConfirmar = new bootstrap.Modal(document.getElementById('modal-confirmar-exclusao'));

                document.getElementById('btn-confirmar-excluir').onclick = () => {
                    axios.delete(`/agenda/${id}`)
                        .then(() => {
                            modalConfirmar.hide();
                            const card = document.querySelector(`.consulta-card[data-consulta-id="${id}"]`);
                            if (card) card.remove();
                        })
                        .catch(err => {
                            alert(err.response?.data?.error ?? 'Erro ao cancelar agendamento');
                        });
                };

                document.getElementById('modal-acao-consulta').addEventListener('hidden.bs.modal', () => {
                    modalConfirmar.show();
                }, { once: true });
                modalAcao.hide();
            };

            modalAcao.show();
        }
    </script>
    @endif
@endsection
