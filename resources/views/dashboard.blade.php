@extends("layouts.app")
@section("title", "Dashboard")
@section('style')
    <style>
        .card-header {
            background-color: var(--branco);
        }
        /* Seta de "abre/fecha" nos cards recolhíveis */
        .card-seta {
            margin-left: auto;
            font-size: .8rem;
            opacity: .7;
            transition: transform .2s ease;
            user-select: none;
        }
        .card-header[aria-expanded="true"] .card-seta {
            transform: rotate(180deg);
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
        .consulta-reserva {
            border-left-color: #dc3545;
            background-color: rgba(220, 53, 69, 0.08);
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
                                @foreach($servicos->where('status', 1)->where('recorrente', 0) as $s)
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

        {{-- Modal Excluir Usuário DEFINITIVAMENTE (hard delete — só admin) --}}
        @if(auth()->user()->adm)
        <x-app.modal id="modal-exc-usuario-hard" title="Excluir DEFINITIVAMENTE" :btn="[['lbl' => 'Excluir definitivo', 'color' => 'danger', 'onclick' => '$(\'#form-excluir-usuario-hard\').submit()']]">
            <form id="form-excluir-usuario-hard" action="{{ route('usuario.excluir-definitivo') }}" method="post">
                @csrf
                @method('post')
                <input class="d-none" type="text" name="id" id="excluir-usuario-hard-id" value="">
                <p class="fs-4 text-danger fw-bold">⚠ Ação irreversível</p>
                <p>Excluir <strong>definitivamente</strong> o usuário <span id="excluir-usuario-hard-nome"></span>?</p>
                <p class="text-danger small">Remove a conta <strong>e todo o histórico</strong> do cliente: agendamentos, avisos, pacotes (créditos), planos mensais e ordens de pagamento. <strong>Não dá para desfazer.</strong></p>
            </form>
        </x-app.modal>
        @endif

        @if(auth()->user()->adm || auth()->user()->func)
        {{-- Contas de usuário --}}
        <div class="card shadow my-3">
            <div class="card-header d-flex align-items-center"
                data-bs-toggle="collapse"
                data-bs-target="#collapseUsuarios"
                aria-expanded="false"
                style="cursor: pointer">
                <span>Controle de Contas de Usuário</span>
                <span class="card-seta">▾</span>
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
                                            @if (($user->func != 1 || Auth()->user()->adm == 1) && (!$user->adm || Auth()->user()->id == 1))
                                                <td class="d-flex">
                                                    <svg style="cursor: pointer" onclick="excluirUsuario({{$user->id}},'{{$user->name}}')" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#dc3545"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                                                    <svg style="cursor: pointer" onclick="editarUsuario({{$user->id}})" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#0d6efd"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg> @if(auth()->user()->adm) <svg style="cursor: pointer" title="Excluir DEFINITIVAMENTE (irreversível)" onclick="excluirUsuarioHard({{$user->id}},'{{$user->name}}')" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#842029"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM8.46 11.88l1.41-1.41L12 12.59l2.12-2.12 1.41 1.41L13.41 14l2.12 2.12-1.41 1.41L12 15.41l-2.12 2.12-1.41-1.41L10.59 14l-2.13-2.12zM15.5 4l-1-1h-5l-1 1H5v2h14V4z"/></svg> @endif
                                                </td>
                                            @else
                                                <td>{{ $user->adm ? 'ADM' : 'COLAB' }}</td>
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
                <x-app.input label="Repasse ao funcionário (%) — parte do valor que vai para o barbeiro" type="number" name="repasse_percent" id="servico_repasse" step="0.01" min="0" max="100" placeholder="Ex.: 50" />
                {{-- Tipo primeiro (mensal/quinzenal), valor só se NÃO for mensal —
                     pacote mensal tem o valor calculado pela composição. --}}
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" name="recorrente" id="servico_recorrente">
                    <label class="form-check-label" for="servico_recorrente">Serviço mensal (pacote de cortes semanais fixos — não agendável individualmente)</label>
                </div>
                <div id="add-combo-editor" class="mt-2 ps-3 border-start" style="display:none">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" value="1" name="quinzenal" id="add_quinzenal">
                        <label class="form-check-label" for="add_quinzenal">Quinzenal — cliente vem a cada 15 dias (semana sim, semana não)</label>
                        <div class="form-text ms-4">Nas semanas vazias o horário fica livre para outros clientes; outro quinzenal pode usar o mesmo dia/horário nas semanas alternadas.</div>
                    </div>
                    <div class="form-check mt-2 ms-4" id="add_idas_diferentes_wrap" style="display:none">
                        <input class="form-check-input" type="checkbox" value="1" name="idas_diferentes" id="add_idas_diferentes">
                        <label class="form-check-label" for="add_idas_diferentes">1ª ida diferente da 2ª (ex.: 1ª só corte, 2ª corte + barba)</label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" value="1" name="mostrar_clientes" id="add_mostrar_clientes">
                        <label class="form-check-label" for="add_mostrar_clientes">Mostrar este pacote aos clientes (aparece no painel deles, com valores e itens)</label>
                    </div>
                    <div class="form-text fw-semibold mt-1">Composição do pacote (preço com desconto de cada serviço)</div>
                    <div id="add-composicao" class="mb-2"></div>
                    <div class="form-text fw-semibold" id="add-dist-label">Distribuição das 4 visitas base</div>
                    <div id="add-distribuicao" class="mb-2"></div>
                    <div class="form-text">Total do pacote: <strong id="add-total">R$ 0,00</strong></div>
                    <input type="hidden" name="composicao" id="add_composicao" value="">
                    <input type="hidden" name="distribuicao" id="add_distribuicao" value="">
                </div>
                <div id="wrap_valor_add">
                    <x-app.input label="Valor (R$) — agendamento online (somente serviço avulso)" type="number" name="valor" id="servico_valor" step="0.01" min="0" placeholder="Ex.: 50.00" />
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
                <x-app.input label="Repasse ao funcionário (%) — parte do valor que vai para o barbeiro" type="number" name="repasse_percent_edt_servico" id="repasse_percent_edt_servico" step="0.01" min="0" max="100" placeholder="Ex.: 50" />
                {{-- Tipo primeiro (mensal/quinzenal); valor só se NÃO for mensal. --}}
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" name="recorrente_edt_servico" id="recorrente_edt_servico">
                    <label class="form-check-label" for="recorrente_edt_servico">Serviço mensal (pacote de cortes semanais fixos — não agendável individualmente)</label>
                </div>
                <div id="edt-combo-editor" class="mt-2 ps-3 border-start" style="display:none">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" value="1" name="quinzenal_edt_servico" id="edt_quinzenal">
                        <label class="form-check-label" for="edt_quinzenal">Quinzenal — cliente vem a cada 15 dias (semana sim, semana não)</label>
                        <div class="form-text ms-4">Nas semanas vazias o horário fica livre para outros clientes; outro quinzenal pode usar o mesmo dia/horário nas semanas alternadas.</div>
                    </div>
                    <div class="form-check mt-2 ms-4" id="edt_idas_diferentes_wrap" style="display:none">
                        <input class="form-check-input" type="checkbox" value="1" name="idas_diferentes_edt_servico" id="edt_idas_diferentes">
                        <label class="form-check-label" for="edt_idas_diferentes">1ª ida diferente da 2ª (ex.: 1ª só corte, 2ª corte + barba)</label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" value="1" name="mostrar_clientes_edt_servico" id="edt_mostrar_clientes">
                        <label class="form-check-label" for="edt_mostrar_clientes">Mostrar este pacote aos clientes (aparece no painel deles, com valores e itens)</label>
                    </div>
                    <div class="form-text fw-semibold mt-1">Composição do pacote (preço com desconto de cada serviço)</div>
                    <div id="edt-composicao" class="mb-2"></div>
                    <div class="form-text fw-semibold" id="edt-dist-label">Distribuição das 4 visitas base</div>
                    <div id="edt-distribuicao" class="mb-2"></div>
                    <div class="form-text">Total do pacote: <strong id="edt-total">R$ 0,00</strong></div>
                    <input type="hidden" name="composicao_edt_servico" id="edt_composicao" value="">
                    <input type="hidden" name="distribuicao_edt_servico" id="edt_distribuicao" value="">
                </div>
                <div id="wrap_valor_edt">
                    <x-app.input label="Valor (R$) — agendamento online (somente serviço avulso)" type="number" name="valor_edt_servico" id="valor_edt_servico" step="0.01" min="0" placeholder="Ex.: 50.00" />
                </div>
                <x-app.radio name="status_servico"
                    :options="[
                        '0' => 'INATIVO',
                        '1' => 'ATIVO'
                    ]"
                />
            </form>
        </x-app.modal>
        @php
            $servicosComunsJs = $servicos->where('recorrente', 0)->where('visivel_cliente', 1)->values()
                ->map(fn($s) => ['id' => (int) $s->id, 'nome' => $s->descricao])->values();
        @endphp
        <script>
            // Editor de composição + distribuição do combo mensal (Fase 5).
            const SERVICOS_COMUNS = @json($servicosComunsJs);
            // prefix: 'add' | 'edt'. dadosIniciais: {composicao:{id:preco}, distribuicao:[[ids],...]} ou null.
            // Editor por prefix (p/ re-render quando o tipo quinzenal liga/desliga).
            const COMBO_EDITORS = {};

            function initComboEditor(prefix, dadosIniciais) {
                const compBox = document.getElementById(prefix + '-composicao');
                const distBox = document.getElementById(prefix + '-distribuicao');
                const outTotal = document.getElementById(prefix + '-total');
                const inComp = document.getElementById(prefix + '_composicao');
                const inDist = document.getElementById(prefix + '_distribuicao');
                if (!compBox) return;
                const ini = dadosIniciais || { composicao: {}, distribuicao: [[],[],[],[]] };
                // Quinzenal: idas iguais → sem seletor (0 posições visíveis, a
                // distribuição é gerada); "idas diferentes" → 2 posições (Ida 1/2).
                // Semanal: as 4 semanas do mês.
                const chkQuinzenal = document.getElementById(prefix + '_quinzenal');
                const chkIdas = document.getElementById(prefix + '_idas_diferentes');
                const nVisitas = () => !chkQuinzenal || !chkQuinzenal.checked
                    ? 4
                    : (chkIdas && chkIdas.checked ? 2 : 0);

                compBox.innerHTML = SERVICOS_COMUNS.map(s =>
                    '<div class="d-flex align-items-center justify-content-between mb-1"><span>' + s.nome + '</span>' +
                    '<div class="input-group input-group-sm" style="width:150px"><span class="input-group-text">R$</span>' +
                    '<input type="number" step="0.01" min="0" class="form-control comp-preco" data-svc="' + s.id + '" value="' + (ini.composicao[s.id] ?? '') + '" placeholder="0,00"></div></div>'
                ).join('');

                const precosPreenchidos = () => {
                    const obj = {};
                    compBox.querySelectorAll('.comp-preco').forEach(el => {
                        const v = parseFloat(el.value);
                        if (el.value !== '' && !isNaN(v) && v > 0) obj[el.dataset.svc] = v;
                    });
                    return obj;
                };

                function renderDistribuicao() {
                    const precos = precosPreenchidos();
                    const svcs = Object.keys(precos);
                    // Quinzenal com idas iguais: sem seletor — cada ida inclui todos
                    // os serviços com preço definido (a distribuição é gerada).
                    const quinzenal = chkQuinzenal && chkQuinzenal.checked;
                    const mostrar = !quinzenal || (chkIdas && chkIdas.checked);
                    distBox.style.display = mostrar ? '' : 'none';
                    if (!mostrar) { distBox.innerHTML = ''; return; }
                    const rotulo = quinzenal ? 'Ida' : 'Visita';
                    distBox.innerHTML = Array.from({ length: nVisitas() }, (_, i) => {
                        const n = i + 1;
                        const marcados = new Set((ini.distribuicao[i] || []).map(x => String(x)));
                        const checks = svcs.length ? svcs.map(sid => {
                            const nome = (SERVICOS_COMUNS.find(s => String(s.id) === String(sid)) || {}).nome || sid;
                            return '<div class="form-check form-check-inline mb-0">' +
                                '<input class="form-check-input dist-chk" type="checkbox" value="' + sid + '" data-sem="' + i + '" ' + (marcados.has(String(sid)) ? 'checked' : '') + ' id="' + prefix + '_d' + i + '_' + sid + '">' +
                                '<label class="form-check-label" for="' + prefix + '_d' + i + '_' + sid + '">' + nome + '</label></div>';
                        }).join('') : '<span class="text-muted small">Preencha um preço acima.</span>';
                        return '<div class="d-flex flex-wrap align-items-center gap-2 mb-1"><span class="badge text-bg-secondary" style="min-width:60px">' + rotulo + ' ' + n + '</span> ' + checks + '</div>';
                    }).join('');
                    distBox.querySelectorAll('.dist-chk').forEach(el => el.addEventListener('change', atualizar));
                }

                function atualizar() {
                    const precos = precosPreenchidos();
                    inComp.value = JSON.stringify(precos);
                    let dist, total = 0;
                    if (chkQuinzenal && chkQuinzenal.checked && !(chkIdas && chkIdas.checked)) {
                        // Quinzenal idas iguais: 2 idas/mês com todos os serviços da
                        // composição (mesma regra do backend — distribuicaoQuinzenal).
                        dist = [Object.keys(precos).map(Number), Object.keys(precos).map(Number)];
                    } else {
                        dist = Array.from({ length: Math.max(1, nVisitas()) }, () => []);
                        distBox.querySelectorAll('.dist-chk:checked').forEach(el => { dist[Number(el.dataset.sem)].push(Number(el.value)); });
                    }
                    dist.forEach(v => v.forEach(sid => { total += precos[sid] || 0; }));
                    outTotal.textContent = 'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2 })
                        + (chkQuinzenal && chkQuinzenal.checked ? ' (2 idas/mês)' : '');
                    inDist.value = JSON.stringify(dist);
                    ini.distribuicao = dist; // preserva os checks ao re-renderizar
                }

                compBox.querySelectorAll('.comp-preco').forEach(el => el.addEventListener('input', () => { renderDistribuicao(); atualizar(); }));
                renderDistribuicao();
                atualizar();
                COMBO_EDITORS[prefix] = { render: () => { renderDistribuicao(); atualizar(); } };
            }

            (function () {
                const addChk = document.getElementById('servico_recorrente');
                const addEditor = document.getElementById('add-combo-editor');
                const addValor = document.getElementById('wrap_valor_add');
                if (addChk && addEditor) {
                    addChk.addEventListener('change', () => {
                        addEditor.style.display = addChk.checked ? '' : 'none';
                        if (addValor) addValor.style.display = addChk.checked ? 'none' : '';
                        if (addChk.checked && !addEditor.dataset.inited) { initComboEditor('add', null); addEditor.dataset.inited = '1'; }
                    });
                }
                const edtChk = document.getElementById('recorrente_edt_servico');
                const edtEditor = document.getElementById('edt-combo-editor');
                const edtValor = document.getElementById('wrap_valor_edt');
                if (edtChk && edtEditor) {
                    edtChk.addEventListener('change', () => {
                        edtEditor.style.display = edtChk.checked ? '' : 'none';
                        if (edtValor) edtValor.style.display = edtChk.checked ? 'none' : '';
                        if (edtChk.checked && !edtEditor.dataset.inited) { initComboEditor('edt', null); edtEditor.dataset.inited = '1'; }
                    });
                }

                // Rótulo da distribuição + nº de visitas do editor: no quinzenal a
                // distribuição é automática (oculta), exceto com "idas diferentes".
                // O toggle re-renderiza o editor com o novo tamanho.
                const ligaRotuloQuinzenal = (chkId, labelId, prefix) => {
                    const chk = document.getElementById(chkId), label = document.getElementById(labelId);
                    const idasWrap = document.getElementById(prefix + '_idas_diferentes_wrap');
                    const idasChk = document.getElementById(prefix + '_idas_diferentes');
                    if (!chk || !label) return;
                    const aplicar = () => {
                        label.textContent = chk.checked && !(idasChk && idasChk.checked)
                            ? 'Cada ida dele inclui TODOS os serviços com preço acima — o ciclo é montado pelo sistema (2 idas/mês; em meses de 5 semanas entra 1 ida extra, como nos mensais)'
                            : (chk.checked ? 'Monte cada ida dele (a 1ª e a 2ª do mês)' : 'Distribuição das 4 visitas base');
                        if (idasWrap) idasWrap.style.display = chk.checked ? '' : 'none';
                    };
                    chk.addEventListener('change', () => {
                        aplicar();
                        if (COMBO_EDITORS[prefix]) COMBO_EDITORS[prefix].render();
                    });
                    if (idasChk) idasChk.addEventListener('change', () => {
                        aplicar();
                        if (COMBO_EDITORS[prefix]) COMBO_EDITORS[prefix].render();
                    });
                    aplicar();
                };
                ligaRotuloQuinzenal('add_quinzenal', 'add-dist-label', 'add');
                ligaRotuloQuinzenal('edt_quinzenal', 'edt-dist-label', 'edt');
            })();
        </script>

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
            <div class="card-header d-flex align-items-center"
                data-bs-toggle="collapse"
                data-bs-target="#collapseServicos"
                aria-expanded="false"
                style="cursor: pointer">
                <span>Serviços</span>
                <span class="card-seta">▾</span>
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
                                    <th scope="col">Repasse</th>
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
                                            @if($servico->recorrente)
                                                <span class="badge bg-primary ms-1">📅 Mensal</span>
                                                @if($servico->quinzenal)<span class="badge ms-1" style="background:#6f42c6" title="Cliente vem a cada 15 dias — semanas vazias ficam livres">🔁 Quinzenal</span>@endif
                                                <span class="badge ms-1 {{ $servico->visivel_cliente ? 'bg-success' : 'bg-warning text-dark' }}"
                                                      title="{{ $servico->visivel_cliente ? 'Aparece no painel dos clientes (valores e itens)' : 'Oculto dos clientes — marque "Mostrar aos clientes" no editor' }}">
                                                    {{ $servico->visivel_cliente ? '👁 visível p/ cliente' : '🚫 oculto' }}
                                                </span>
                                            @else
                                                <span class="badge ms-1" style="background:#6f42c6">✂️ Avulso</span>
                                            @endif
                                        </td>
                                        <td>{{ $servico->duracao }}</td>
                                        <td>{{ $servico->valor ? 'R$ ' . number_format($servico->valor, 2, ',', '.') : '—' }}</td>
                                        <td>{{ $servico->repasse_percent !== null ? rtrim(rtrim(number_format($servico->repasse_percent, 2, ',', '.'), '0'), ',') . '%' : '—' }}</td>
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
                            <input type="time" step="900" id="mgm-comercial-inicio" class="form-control form-control-sm" style="width:auto" value="{{ \App\Models\PageContent::get('agenda','comercial_inicio','08:00') }}" title="Início do horário comercial">
                            <input type="time" step="900" id="mgm-comercial-fim" class="form-control form-control-sm" style="width:auto" value="{{ \App\Models\PageContent::get('agenda','comercial_fim','17:45') }}" title="Fim do horário comercial">
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
                        <select id="mgm-barbeiro" class="form-select form-select-sm" style="width:auto" onchange="mgmAtualizarJanela(); mgmCarregarMes()">
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
                    {{-- Janela de horário POR BARBEIRO (cada um define a sua; usada ao liberar a semana) --}}
                    <div id="mgm-janela-barbeiro" class="d-flex flex-wrap align-items-center gap-2 justify-content-center mb-2">
                        <span class="text-muted small">Horário deste barbeiro:</span>
                        <input type="time" step="900" id="mgm-janela-inicio" class="form-control form-control-sm" style="width:auto" title="Início da janela do barbeiro selecionado">                        <span class="text-muted small">até</span>
                        <input type="time" step="900" id="mgm-janela-fim" class="form-control form-control-sm" style="width:auto" title="Fim da janela do barbeiro selecionado">
                        <button class="btn btn-sm btn-success" type="button" onclick="mgmSalvarJanela()">Salvar horário</button>
                    </div>
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
                    <span><span style="display:inline-block;width:14px;height:14px;background:#fff3cd;border:1px solid #f0ad4e;border-radius:3px;vertical-align:middle"></span> Fixo não liberado</span>
                    <span><span style="display:inline-block;width:14px;height:14px;background:#f1f1f1;opacity:.4;border-radius:3px;vertical-align:middle"></span> Passado</span>
                </div>
            </div>
        </div>

        {{-- Avisos --}}
        <div class="card shadow my-3">
            <div class="card-header d-flex justify-content-between align-items-center"
                 data-bs-toggle="collapse" data-bs-target="#collapseAvisos"
                 aria-expanded="{{ $avisos->isNotEmpty() ? 'true' : 'false' }}" style="cursor:pointer">
                <span>Avisos</span>
                <span class="d-flex align-items-center gap-2">
                    @if($avisos->isNotEmpty())
                        <span class="badge bg-warning text-dark">{{ $avisos->count() }}</span>
                    @endif
                    <span class="card-seta">▾</span>
                </span>
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

        {{-- Planos mensais (horários fixos) — só os em vigor; realizados no /historico --}}
        @if(auth()->user()->adm || auth()->user()->func || $planos->isNotEmpty())
        <div class="card shadow my-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-1"
                 data-bs-toggle="collapse"
                 data-bs-target="#collapsePlanosMensais"
                 aria-expanded="false"
                 style="cursor: pointer">
                <span>Planos mensais (horários fixos)</span>
                <span class="d-flex align-items-center gap-2">
                    @if(auth()->user()->adm || auth()->user()->func)
                    <a href="{{ route('historico.index') }}" class="small text-decoration-none" onclick="event.stopPropagation()">Histórico de atendimentos →</a>
                    <button class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#modal-add-plano" style="background-color: var(--marrom); color:#1a1410" onclick="event.stopPropagation()">Vincular plano</button>
                    @endif
                    <span class="card-seta">▾</span>
                </span>
            </div>
            <div id="collapsePlanosMensais" class="collapse">
                <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Cliente</th><th>Itens inclusos</th><th>Barbeiro</th><th>Slot fixo</th><th>Ciclo</th><th>Visitas</th><th>Status</th></tr></thead>
                    <tbody>
                        @php $assinaturasComBotao = []; @endphp
                        @forelse($planos as $pl)
                        <tr>
                            <td>{{ $pl->user->name ?? '—' }}</td>
                            <td>
                                {{ $pl->descricaoItens() }}
                                @if($pl->recorrente) <span class="badge text-bg-info" title="Renovação automática mensal (link)">🔁 Recorrente</span>@endif
                            </td>
                            <td>{{ $pl->funcionario->name ?? '—' }}</td>
                            <td>{{ ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][$pl->dia_semana] ?? '' }} {{ \Carbon\Carbon::parse($pl->hora)->format('H:i') }}@if($pl->servico->quinzenal) <span class="badge" style="background:#6f42c6">quinzenal</span>@endif</td>
                            <td>
                                @if($pl->data_inicio && $pl->data_fim)
                                    {{ \Carbon\Carbon::parse($pl->data_inicio)->format('d/m') }} → {{ \Carbon\Carbon::parse($pl->data_fim)->format('d/m/Y') }}
                                @else
                                    {{ \Carbon\Carbon::parse($pl->mes)->format('m/Y') }}
                                @endif
                            </td>
                            <td>
                                <div>{{ $pl->unidades_usadas }}/{{ $pl->unidades_total }}</div>
                                @if($pl->itens->isNotEmpty())
                                    <div class="small text-muted">{{ $pl->restantesPorServico() }}</div>
                                @endif
                            </td>
                            <td>
                                @if(auth()->user()->adm && in_array($pl->status, ['ativo', 'aguardando_pagamento']))
                                    <button type="button" class="btn btn-sm btn-link text-secondary p-0 lh-1 align-baseline me-1 btn-edit-plano" data-plano="{{ $pl->id }}" title="Editar quantidades e valor com desconto">Editar</button>
                                @endif
                                @if((auth()->user()->adm || auth()->user()->func) && $pl->status === 'aguardando_pagamento')
                                    <form method="POST" action="{{ route('planos.ativar', $pl) }}" class="d-inline me-1"
                                          onsubmit="return confirm('Ativar este plano agora? Use para pagamento PRESENCIAL (dinheiro/cartão na hora).')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-link text-success p-0 lh-1 align-baseline" title="Ativar — pagamento presencial">Ativar (pago no local)</button>
                                    </form>
                                @endif
                                @if($pl->status === 'ativo') <span class="badge bg-success">Ativo</span>
                                @elseif($pl->status === 'aguardando_pagamento') <span class="badge bg-warning text-dark">Aguardando pgto</span>
                                @elseif($pl->status === 'consumido') <span class="badge bg-secondary">Consumido</span>
                                @elseif($pl->status === 'expirado') <span class="badge bg-danger">{{ $pl->restantes() }} a negociar</span>
                                @else <span class="badge bg-secondary">{{ $pl->status }}</span>
                                @endif
                                @if(auth()->user()->adm || auth()->user()->func)
                                    @php $aid = $pl->assinatura->id ?? null; @endphp
                                    @if($aid && $pl->assinatura->status === 'ativo' && !in_array($aid, $assinaturasComBotao) && in_array($pl->status, ['ativo', 'aguardando_pagamento']))
                                        @php $assinaturasComBotao[] = $aid; @endphp
                                        <form method="POST" action="{{ route('assinaturas.cancelar', $pl->assinatura) }}" class="d-inline-block ms-2"
                                              onsubmit="return confirm('Cancelar este plano? As renovações automáticas param. O ciclo já pago segue valendo até o fim.')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0 lh-1 align-baseline" title="Interrompe as renovações automáticas">Cancelar plano</button>
                                        </form>
                                    @elseif($aid && $pl->assinatura->status === 'cancelado' && !in_array($aid, $assinaturasComBotao))
                                        @php $assinaturasComBotao[] = $aid; @endphp
                                        <span class="badge text-bg-secondary ms-1" title="Assinatura cancelada — sem renovações">Cancelada</span>
                                    @endif
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
                if (!confirm('Recusar este agendamento? O cliente será avisado e, se pagou online, ganhará 1 crédito do serviço para remarcar.')) return;
                axios.post(`{{ url('/') }}/agenda/${id}/recusar`)
                    .then(res => {
                        const modo = res.data.credito
                            ? 'O cliente ganhou 1 unidade de crédito do serviço (sem validade). Devolução do valor, se pedida, é combinada direto no WhatsApp.'
                            : 'O cliente foi avisado para reagendar.';
                        alert('Recusado. ' + modo);
                        $(`tr[data-pendente="${id}"]`).fadeOut(250, function(){ $(this).remove(); });
                    })
                    .catch(() => alert('Erro ao recusar.'));
            });
        });
        </script>
        @endif

        {{-- Intenções de agendamento: cliente penalizado pedindo horário no local --}}
        @if((auth()->user()->adm || auth()->user()->func) && $intencoes->isNotEmpty())
        <div class="card mb-3 border-info">
            <div class="card-header fw-bold" style="background: rgba(13,110,253,.10)">
                🕓 Intenções de agendamento ({{ $intencoes->count() }})
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Cliente</th><th>Serviço</th><th>Quando</th><th class="text-end">Ação</th></tr></thead>
                    <tbody>
                        @foreach($intencoes as $p)
                        <tr data-intencao="{{ $p->id }}">
                            <td>{{ $p->user->name ?? '—' }}</td>
                            <td>{{ $p->servico->descricao ?? '—' }}</td>
                            <td>{{ \Carbon\Carbon::parse($p->data_inicio)->format('d/m H:i') }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-success btn-aprovar-intencao" data-id="{{ $p->id }}">Aprovar</button>
                                <button class="btn btn-sm btn-outline-danger btn-recusar-intencao" data-id="{{ $p->id }}">Recusar</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <script>
        $(function () {
            $(document).on('click', '.btn-aprovar-intencao', function () {
                const id = $(this).data('id');
                axios.post(`{{ url('/') }}/agenda/${id}/aprovar-intencao`)
                    .then(() => $(`tr[data-intencao="${id}"]`).fadeOut(250, function () { $(this).remove(); }))
                    .catch(err => alert(err.response?.data?.error ?? 'Erro ao aprovar.'));
            });
            $(document).on('click', '.btn-recusar-intencao', function () {
                const id = $(this).data('id');
                if (!confirm('Recusar este pedido? O cliente continua penalizado e será avisado.')) return;
                axios.post(`{{ url('/') }}/agenda/${id}/recusar-intencao`)
                    .then(() => $(`tr[data-intencao="${id}"]`).fadeOut(250, function () { $(this).remove(); }))
                    .catch(err => alert(err.response?.data?.error ?? 'Erro ao recusar.'));
            });
        });
        </script>
        @endif

        {{-- Trocas de dia/horário de plano mensal pedidas pelo cliente (aguardando decisão) --}}
        @if((auth()->user()->adm || auth()->user()->func) && $trocasPendentes->isNotEmpty())
        @php
            $diasCurta = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
        @endphp
        <div class="card mb-3 border-warning">
            <div class="card-header fw-bold" style="background: rgba(255,193,7,.15)">
                🔄 Trocas de dia/horário — planos mensais ({{ $trocasPendentes->count() }})
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Cliente</th><th>Barbeiro</th><th>Slot atual</th><th>Novo dia/horário</th><th>Pedido em</th><th class="text-end">Ação</th></tr></thead>
                    <tbody>
                        @foreach($trocasPendentes as $t)
                        <tr data-troca="{{ $t->id }}">
                            <td>{{ $t->user->name ?? '—' }}<div class="small text-muted">{{ $t->user->whatsapp ?? '' }}</div></td>
                            <td>{{ $t->assinatura->funcionario->name ?? '—' }}</td>
                            <td>{{ $diasCurta[$t->assinatura->dia_semana] ?? '' }} às {{ \Carbon\Carbon::parse($t->assinatura->hora)->format('H:i') }}</td>
                            <td><strong>{{ $t->slotDesc() }}</strong></td>
                            <td>{{ $t->created_at->format('d/m H:i') }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('plano-trocas.aprovar', $t) }}" class="d-inline"
                                      onsubmit="return confirm('Aprovar a troca para {{ $t->slotDesc() }}? Os agendamentos futuros deste plano serão remarcados para o novo dia/horário.')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">Aprovar</button>
                                </form>
                                <form method="POST" action="{{ route('plano-trocas.recusar', $t) }}" class="d-inline"
                                      onsubmit="return confirm('Recusar esta troca? O cliente verá a recusa no painel dele.')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Recusar</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif


        @if(auth()->user()->adm || auth()->user()->func)
        {{-- Modal: Vincular plano mensal a um cliente --}}
        <x-app.modal id="modal-add-plano" title="Vincular plano mensal" :btn="[['lbl' => 'Vincular', 'color' => 'primary', 'onclick' => '$(\'#form-add-plano\').submit()']]">
            <form method="POST" id="form-add-plano" action="{{ route('planos.store') }}" novalidate>
                @csrf
                @method('post')
                <div class="mb-3">
                    <label for="plano_cliente_busca" class="form-label">Cliente</label>
                    <input type="text" id="plano_cliente_busca" class="form-control {{ $errors->has('user_id') ? 'is-invalid' : '' }}" placeholder="Digite o nome do cliente..." autocomplete="off" value="{{ old('user_id') ? \App\Models\User::find(old('user_id'))?->name : '' }}">
                    <input type="hidden" name="user_id" id="plano_user_id" value="{{ old('user_id') }}" required>
                    <div id="plano_cliente_resultados" class="list-group position-relative" style="max-height:200px;overflow:auto;z-index:5"></div>
                    @error('user_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                @if ($errors->any())
                    <div class="alert alert-danger py-2 mb-3">{{ implode(' · ', $errors->all()) }}</div>
                @endif
                <div class="mb-3">
                    <label for="plano_servico_base_id" class="form-label">Combo (serviço mensal) — define nome e duração do slot</label>
                    <select name="servico_base_id" id="plano_servico_base_id" class="form-select {{ $errors->has('servico_base_id') ? 'is-invalid' : '' }}" required>
                        <option value="">Selecione...</option>
                        @foreach ($servicos->where('recorrente', 1) as $s)
                            <option value="{{ $s->id }}" @selected(old('servico_base_id') == $s->id)>{{ $s->descricao }}</option>
                        @endforeach
                    </select>
                    @error('servico_base_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                        <input type="time" name="hora" id="plano_hora" step="900" class="form-control {{ $errors->has('hora') ? 'is-invalid' : '' }}" value="{{ old('hora') }}" required>
                        @error('hora')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-text mt-1 small">O pacote começa no mês atual (semana atual em diante). Se vier no meio do mês, o valor é proporcional às semanas restantes.</div>
                <div class="mb-2">
                    <label for="plano_dia_renovacao" class="form-label">Melhor dia para pagar a renovação <span class="text-muted small">(todo mês; só a equipe altera)</span></label>
                    <select name="dia_renovacao" id="plano_dia_renovacao" class="form-select" required>
                        <option value="">Escolha o dia...</option>
                        @for($d = 1; $d <= 31; $d++)
                            <option value="{{ $d }}" @selected((string) old('dia_renovacao') === (string) $d)>Dia {{ $d }}</option>
                        @endfor
                    </select>
                </div>
                <div id="plano_combo_preview" class="mt-3 border rounded p-2" style="display:none">
                    <div class="form-text fw-semibold">Composição do pacote (herdada do combo)</div>
                    <div id="plano_combo_itens" class="small"></div>
                    <div class="form-text mt-2"><span id="plano_valor_lbl">Valor do pacote (4 visitas)</span>: <strong id="plano_valor_total">—</strong></div>
                    <div class="form-text" id="plano_extra_lbl">Em meses de 5 semanas, a 5ª visita é automática (repete a 1ª).</div>
                </div>
                <div id="plano_combo_alert" class="alert alert-warning py-2 mt-3 small" style="display:none">
                    Este combo ainda não tem composição cadastrada. Edite o serviço mensal para definir serviços inclusos, preços com desconto e distribuição.
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" name="consumir_no_ato" id="plano_consumir" {{ old('consumir_no_ato') ? 'checked' : '' }}>
                    <label class="form-check-label" for="plano_consumir">
                        Consumir 1 unidade agora (o cliente já usou um serviço no ato da criação).
                    </label>
                </div>
                <div class="form-text mt-2 small">O plano renova automaticamente todo mês no dia de pagamento (gera a cobrança e avisa o cliente).</div>
            </form>
        </x-app.modal>
        <script>
            // Preview do combo master ao escolher no "Novo plano mensal" (Fase 5).
            (function () {
                const base = document.getElementById('plano_servico_base_id');
                const preview = document.getElementById('plano_combo_preview');
                const alerta  = document.getElementById('plano_combo_alert');
                const itens   = document.getElementById('plano_combo_itens');
                const outV    = document.getElementById('plano_valor_total');
                const lblV    = document.getElementById('plano_valor_lbl');
                const lblE    = document.getElementById('plano_extra_lbl');
                if (!base) return;

                async function carregar() {
                    preview.style.display = 'none';
                    alerta.style.display = 'none';
                    if (!base.value) return;
                    try {
                        const d = (await axios.get('/api/servico/' + base.value + '/composicao')).data;
                        if (!d.tem_composicao) { alerta.style.display = ''; return; }
                        const nomes = {};
                        d.servicos.forEach(s => nomes[s.id] = s.descricao);
                        const comps = Object.entries(d.composicao).map(([sid, p]) =>
                            '<div>' + (nomes[sid] || ('serviço ' + sid)) + ': <strong>R$ ' + Number(p).toLocaleString('pt-BR', {minimumFractionDigits: 2}) + '</strong></div>'
                        ).join('');
                        itens.innerHTML = comps + '<div class="text-muted">' + (d.quinzenal ? 'Idas' : 'Visitas') + ': ' + d.distribuicao.map(v => v.length + ' svc').join(' · ') + '</div>';
                        outV.textContent = 'R$ ' + Number(d.total_base).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                        if (d.quinzenal) {
                            lblV.textContent = 'Valor do pacote (2 idas/mês)';
                            lblE.textContent = 'Em meses com 3 semanas da fase dele, a 3ª ida é automática (repete a 1ª). Nas semanas livres o horário fica disponível.';
                        } else {
                            lblV.textContent = 'Valor do pacote (4 visitas)';
                            lblE.textContent = 'Em meses de 5 semanas, a 5ª visita é automática (repete a 1ª).';
                        }
                        preview.style.display = '';
                    } catch (e) { alerta.style.display = ''; }
                }
                base.addEventListener('change', carregar);
                carregar();
            })();
        </script>
        <script>
            // Autocomplete de cliente (axios) no "Novo plano mensal".
            (function () {
                const inp = document.getElementById('plano_cliente_busca');
                const hidden = document.getElementById('plano_user_id');
                const box = document.getElementById('plano_cliente_resultados');
                if (!inp || !hidden || !box) return;
                let t = null;
                inp.addEventListener('input', function () {
                    hidden.value = '';
                    const q = inp.value.trim();
                    if (q.length < 2) { box.innerHTML = ''; return; }
                    clearTimeout(t);
                    t = setTimeout(function () {
                        axios.get('/api/clientes/busca', { params: { q: q } }).then(function (r) {
                            box.innerHTML = r.data.map(function (c) {
                                const nome = String(c.name).replace(/"/g, '');
                                const wa = c.whatsapp ? ' <small class="text-muted">' + c.whatsapp + '</small>' : '';
                                return '<button type="button" class="list-group-item list-group-item-action py-1" data-id="' + c.id + '" data-nome="' + nome + '">' + nome + wa + '</button>';
                            }).join('');
                        }).catch(function () { box.innerHTML = ''; });
                    }, 250);
                });
                box.addEventListener('click', function (e) {
                    const b = e.target.closest('button[data-id]');
                    if (!b) return;
                    hidden.value = b.dataset.id;
                    inp.value = b.dataset.nome;
                    box.innerHTML = '';
                });
            })();
        </script>

        @if(auth()->user()->adm)
        {{-- Modal: Editar plano mensal (quantidades + valor unitário com desconto) --}}
        <x-app.modal id="modal-edit-plano" title="Editar plano mensal" :btn="[['lbl' => 'Salvar', 'color' => 'primary', 'onclick' => '$(\'#form-edit-plano\').submit()']]">
            <form method="POST" id="form-edit-plano" action="" novalidate>
                @csrf
                @method('PUT')
                <div id="edit-plano-itens" class="mb-3"></div>
                <div class="row g-2">
                    <div class="col">
                        <label class="form-label small mb-1">Dia de pagamento (renovação)</label>
                        <select name="dia_renovacao" id="edit_dia_renovacao" class="form-select form-select-sm" required></select>
                    </div>
                    <div class="col form-text small align-self-end">Visita extra automática quando o mês tem semanas extras (repete a 1ª)</div>
                </div>
            </form>
        </x-app.modal>
        <script>
            (function () {
                const modalEl = document.getElementById('modal-edit-plano');
                if (!modalEl) return;
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                const form = document.getElementById('form-edit-plano');
                const itensBox = document.getElementById('edit-plano-itens');
                const diaSel = document.getElementById('edit_dia_renovacao');
                // Popula select 1-31 uma vez.
                diaSel.innerHTML = '';
                for (let d = 1; d <= 31; d++) diaSel.innerHTML += '<option value="' + d + '">Dia ' + d + '</option>';

                const baseUrl = '{{ route("planos.update", ["plano" => 0]) }}';

                document.addEventListener('click', function (e) {
                    const btn = e.target.closest('.btn-edit-plano');
                    if (!btn) return;
                    const id = btn.dataset.plano;
                    axios.get('/planos-mensais/' + id + '/editar').then(function (r) {
                        const d = r.data;
                        form.action = baseUrl.replace('/0', '/' + id);

                        itensBox.innerHTML = d.itens.map(function (it, idx) {
                            return '<div class="d-flex align-items-center gap-2 mb-1">' +
                                '<span class="flex-grow-1">' + it.descricao + '</span>' +
                                '<input type="hidden" name="itens[' + idx + '][servico_id]" value="' + it.servico_id + '">' +
                                '<input type="number" name="itens[' + idx + '][quantidade]" value="' + it.quantidade + '" min="0" class="form-control form-control-sm" style="width:80px" title="quantidade">' +
                                '<div class="input-group input-group-sm" style="width:140px"><span class="input-group-text">R$</span>' +
                                '<input type="number" step="0.01" name="itens[' + idx + '][valor_unitario]" value="' + it.valor_unitario + '" min="0" class="form-control" title="valor unitário c/ desconto"></div>' +
                                '</div>';
                        }).join('') + '<div class="form-text">Valor unitário <strong>com desconto</strong> — usado para precificar a unidade extra da 5ª semana.</div>';

                        diaSel.value = d.dia_renovacao || '';
                        modal.show();
                    });
                });
            })();
        </script>
        @endif

        @endif {{-- modal plano mensal + cálculo: adm ou func --}}

    @endif

    {{-- Agendamentos --}}
    <div class="container py-4">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
            <h3 class="mb-0">Agendamentos</h3>
            @if(auth()->user()->adm || auth()->user()->func)
            <a href="{{ route('calendario1') }}" class="btn btn-sm ms-2" style="background-color: var(--marrom); color:#1a1410">📅 Ver calendário</a>
            @endif
        </div>

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
                        <div class="form-text small">Use para encaixar um horário <strong>sobre outro agendamento</strong> já existente (atendimento simultâneo).</div>
                    </div>
                    {{-- Serviço comum: um item por PACOTE contratado (com saldo restante/total).
                         O select não é enviado; ele alimenta os campos ocultos servico_id e credito_id. --}}
                    <div id="servico-normal-wrap" class="mb-3">
                        <label for="servico_sel" class="form-label">Selecione o serviço</label>
                        <div class="form-text small mb-1">Os serviços avulsos (pacotes) devem ser adicionados no <strong>cadastro do cliente</strong> (Editar Usuário).</div>
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
        @php
            // Próximos agendamentos reais (confirmados, pagos ou reserva de plano) —
            // destaque no topo; intenções e recusados ficam de fora (têm card próprio).
            $proximos = $consultas->flatten()
                ->filter(fn($c) => $c->data_inicio->isFuture() && in_array($c->status, \App\Models\AgendamentoModel::OCUPANTES))
                ->sortBy('data_inicio')->values();
            $proximo = $proximos->first();

            // Situação do pagamento do mês (substitui a antiga tabela "Meus pagamentos"):
            // cobrança em aberto = pendente (botão pagar); aprovada no mês = em dia.
            $ordensPendentes = $minhasOrdens->filter(fn($o) => $o->pagavel())->sortBy('id')->values();
            $ordemPendente   = $ordensPendentes->first();
            $pagoNoMes       = !$ordemPendente && $minhasOrdens->contains(
                fn($o) => $o->status === 'approved' && $o->pago_em?->isCurrentMonth()
            );
        @endphp

        {{-- Pendência (no-show): aviso — o botão de agendar fica no card abaixo --}}
        @if(auth()->user()->isPenalizado())
        <div class="alert alert-info my-3">
            <div class="fw-semibold">Você está com uma pendência</div>
            <div class="small mb-0">Você pode agendar normalmente com <strong>pagamento online</strong>. Pedidos para <strong>pagar no local</strong> ficam sujeitos a aprovação do barbeiro.</div>
        </div>
        @endif

        {{-- Topo: próximo corte + situação do pagamento do mês + ação primária --}}
        <div class="card shadow my-3">
            <div class="card-body p-3">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="flex-grow-1">
                        @if($proximo)
                            <div class="text-uppercase fw-semibold" style="font-size:.72rem; letter-spacing:.08em; color:#b6a98e">Seu próximo corte</div>
                            <div class="fs-4 fw-bold" style="color:#3ddc84">
                                {{ $proximo->data_inicio->locale('pt_BR')->translatedFormat('l, d/m') }}
                                às {{ $proximo->data_inicio->format('H:i') }}
                            </div>
                            <div>
                                {{ $proximo->servico_display }}@if($proximo->funcionario) · {{ $proximo->funcionario->name }}@endif
                                @if($proximo->status === \App\Models\AgendamentoModel::STATUS_RESERVA_RENOVACAO)
                                    <span class="badge bg-danger ms-1">renovar plano</span>
                                @endif
                            </div>
                            @if($proximos->count() > 1)
                                <div class="small text-muted">E mais {{ $proximos->count() - 1 }} agendamento(s) futuro(s) — detalhes em “Agendamentos”, no fim da página.</div>
                            @endif
                        @else
                            <div class="fw-semibold fs-5">Pronto para o próximo corte?</div>
                            <div class="text-muted small">Escolha o barbeiro, o serviço, o dia e o horário e pague pelo site.</div>
                        @endif
                    </div>
                    <a href="{{ route('agendar.index') }}" class="btn btn-lg px-4" style="background-color: var(--marrom); color:#1a1410">✂️ Agendar corte</a>
                </div>

                @if($ordemPendente || $pagoNoMes)
                    <hr class="my-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @if($ordemPendente)
                            <span class="badge bg-danger p-2">Pagamento do mês pendente</span>
                            @if($ordensPendentes->count() > 1)
                                <span class="small text-muted">{{ $ordensPendentes->count() }} cobranças em aberto</span>
                            @endif
                            {{-- Botão some sozinho após o pagamento (a ordem deixa de ser pagável) --}}
                            <a href="{{ route('pagamentos.pagar', $ordemPendente) }}" class="btn btn-sm ms-auto" style="background-color: var(--marrom); color:#1a1410">Pagar agora</a>
                        @else
                            <span class="badge bg-success p-2">✓ Pagamento do mês realizado</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Intenções de agendamento do cliente (aguardando aprovação do barbeiro) --}}
        @if($minhasIntencoes->isNotEmpty())
        <div class="card shadow my-3 border-info">
            <div class="card-header fw-bold">🕓 Aguardando aprovação do barbeiro</div>
            <div class="card-body p-3">
                @foreach($minhasIntencoes as $mi)
                    <div class="border rounded px-2 py-1 mb-1 small">
                        <strong>{{ $mi->servico->descricao ?? '—' }}</strong>
                        · {{ $mi->funcionario->name ?? '—' }}
                        · {{ \Carbon\Carbon::parse($mi->data_inicio)->format('d/m/Y H:i') }}
                        <span class="badge bg-info text-dark">pagar no local</span>
                        <span class="text-muted">— seu pedido foi enviado. Aguarde a resposta do barbeiro.</span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @php
            // Só o plano do momento (ativo ou aguardando pagamento); ciclos antigos
            // (consumidos/expirados) ficam de fora para não poluir o painel.
            $planosAtuais = $meusPlanos->whereIn('status', ['ativo', 'aguardando_pagamento'])->values();
            // Sem plano atual mas com ciclo expirado e visitas não usadas → Negociar.
            $planoNegociar = $planosAtuais->isEmpty()
                ? $meusPlanos->first(fn($p) => $p->status === 'expirado' && $p->restantes() > 0)
                : null;
        @endphp
        @if($planosAtuais->isNotEmpty() || $planoNegociar)
        <div class="card shadow my-3">
            <div class="card-header">Meu plano mensal</div>
            <div class="card-body p-3">
                @foreach($planosAtuais as $pl)
                    @php
                        $diasSlot = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
                        $slot = ($diasSlot[$pl->dia_semana] ?? '') . ' às ' . \Carbon\Carbon::parse($pl->hora)->format('H:i')
                            . ($pl->servico->quinzenal ? ' (quinzenal — semana sim, semana não)' : '');
                        // Troca de dia/horário: pendente trava nova solicitação; a última
                        // resolvida (7 dias) dá o feedback de aprovada/recusada.
                        $trocaPendente  = $pl->assinatura
                            ? $minhasTrocas->where('assinatura_id', $pl->assinatura->id)
                                ->where('status', \App\Models\PlanoTroca::STATUS_PENDENTE)->first()
                            : null;
                        $trocaFeedback  = $pl->assinatura
                            ? $minhasTrocas->where('assinatura_id', $pl->assinatura->id)
                                ->whereIn('status', [\App\Models\PlanoTroca::STATUS_APROVADA, \App\Models\PlanoTroca::STATUS_RECUSADA])
                                ->filter(fn($t) => $t->resolvido_em && $t->resolvido_em->gt(now()->subDays(7)))
                                ->first()
                            : null;
                    @endphp
                    <div class="border rounded px-2 py-2 mb-1">
                        <div class="d-flex flex-wrap align-items-center gap-1">
                            <strong>{{ $pl->descricaoItens() }}</strong>
                            @if($pl->status === 'ativo')
                                <span class="badge bg-success">Ativo</span>
                            @else
                                <span class="badge bg-warning text-dark">Aguardando pagamento</span>
                            @endif
                            @if($pl->assinatura && $pl->assinatura->status === 'cancelado')
                                <span class="badge text-bg-secondary" title="Renovações canceladas — este ciclo segue valendo até o fim">Cancelado — vale até o fim do ciclo</span>
                            @endif
                        </div>
                        <div class="small">
                            {{ $pl->funcionario->name ?? '—' }} · {{ $slot }}
                            <span class="badge bg-secondary">{{ $pl->unidades_usadas }}/{{ $pl->unidades_total }} visitas</span>
                            @if($pl->itens->isNotEmpty())<span class="text-muted">· {{ $pl->restantesPorServico() }}</span>@endif
                            @if($pl->data_fim)<span class="text-muted">· ciclo até {{ $pl->data_fim->format('d/m/Y') }}</span>@endif
                        </div>
                        @if($pl->temDistribuicao() && $pl->status === 'ativo' && $pl->proximaVisitaDesc())
                            <div class="small text-muted">Próxima visita: {{ $pl->proximaVisitaDesc() }}</div>
                        @endif
                        {{-- Troca de dia/horário: só enquanto a assinatura estiver ativa e não
                             houver outro pedido pendente. Fica pendente até o staff aprovar. --}}
                        @if($pl->assinatura && $pl->assinatura->status === 'ativo' && !$trocaPendente)
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2 me-1 btn-trocar-dia"
                                    data-url="{{ route('assinaturas.trocar-dia', $pl->assinatura) }}"
                                    data-dia-atual="{{ $pl->assinatura->dia_semana }}">
                                🔄 Trocar dia/horário
                            </button>
                        @endif
                        @if($trocaPendente)
                            <div class="small mt-2">
                                <span class="badge bg-info text-dark">🔄 Troca solicitada: {{ $trocaPendente->slotDesc() }}</span>
                                <span class="text-muted">— aguardando a barbearia confirmar. Seu plano segue no horário atual até lá.</span>
                            </div>
                        @elseif($trocaFeedback)
                            @if($trocaFeedback->status === \App\Models\PlanoTroca::STATUS_APROVADA)
                                <div class="small mt-2"><span class="badge bg-success">✓ Troca aprovada</span>
                                    <span class="text-muted">— plano movido para {{ $trocaFeedback->slotDesc() }} em {{ $trocaFeedback->resolvido_em->format('d/m') }}.</span></div>
                            @else
                                <div class="small mt-2"><span class="badge bg-secondary">Troca recusada em {{ $trocaFeedback->resolvido_em->format('d/m') }}</span>
                                    <span class="text-muted">— fale com a barbearia pelo WhatsApp se quiser entender o motivo.</span></div>
                            @endif
                        @endif

                        {{-- Cancelamento pelo próprio cliente: só enquanto a assinatura
                             (renovação automática) estiver ativa. --}}
                        @if($pl->assinatura && $pl->assinatura->status === 'ativo')
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-cancelar-plano"
                                    data-url="{{ route('assinaturas.cancelar', $pl->assinatura) }}">
                                Cancelar plano
                            </button>
                        @endif
                    </div>
                @endforeach
                @if($planoNegociar)
                    <div class="border rounded px-2 py-2 small d-flex flex-wrap align-items-center gap-2">
                        <span>Seu último plano expirou com <strong>{{ $planoNegociar->restantes() }} visita(s) não usada(s)</strong>.</span>
                        <a class="badge bg-warning text-dark text-decoration-none" target="_blank" rel="noopener"
                           href="https://wa.me/{{ $whatsappAdmin }}?text={{ urlencode('Olá! Quero renegociar meu plano mensal (restam ' . $planoNegociar->restantes() . ' visitas).') }}">Negociar pelo WhatsApp</a>
                    </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Meus pacotes avulsos: os que ainda têm unidade sobrando OU têm
             agendamento futuro (a última unidade já reservada — resta 0, mas o
             corte marcado aparece). Esgotados sem previsão saem da lista. --}}
        @php
            $avulsosAtivos = $meusCreditos->filter(fn($c) => $c->restantes() > 0 || $c->agendamentos->isNotEmpty())->values();
        @endphp
        @if($avulsosAtivos->isNotEmpty())
        <div class="card shadow my-3">
            <div class="card-header">Meus pacotes avulsos</div>
            <div class="card-body p-3">
                @foreach($avulsosAtivos as $cred)
                    @php
                        $restam          = $cred->restantes();
                        $expirado        = $cred->expirado();
                        $agendado        = $cred->agendamentos->first();
                    @endphp
                    <div class="border rounded px-2 py-2 mb-1 small d-flex flex-wrap align-items-center gap-2">
                        <div class="flex-grow-1">
                            <strong>{{ $cred->servico->descricao ?? '—' }}</strong>
                            @if($restam > 0)
                                <span class="badge bg-success">resta {{ $restam }}</span>
                            @endif
                            @if($cred->expira_em)
                                <span class="text-muted">· expira em {{ $cred->expira_em->format('d/m/Y') }}{{ $expirado ? ' (expirado)' : '' }}</span>
                            @endif
                            @if($agendado)
                                <div class="text-muted">
                                    ✓ Agendado: {{ $agendado->data_inicio->format('d/m/Y H:i') }}
                                    @if($agendado->funcionario) · {{ $agendado->funcionario->name }} @endif
                                </div>
                            @endif
                        </div>
                        @if($expirado)
                            <a class="badge bg-warning text-dark text-decoration-none" target="_blank" rel="noopener"
                               href="https://wa.me/{{ $whatsappAdmin }}?text={{ urlencode('Olá! Quero renegociar meu pacote de ' . ($cred->servico->descricao ?? '') . ' (expirado).') }}">Negociar</a>
                        @elseif($restam > 0)
                            {{-- Com saldo além do já agendado, o botão segue disponível:
                                 pacote de 2+ unidades pode marcar todas pelo site. --}}
                            <a href="{{ route('agendar.index', ['pacote' => $cred->id]) }}"
                               class="btn btn-sm" style="background-color: var(--marrom); color:#1a1410">{{ $agendado ? 'Agendar próxima' : 'Agendar' }}</a>
                        @endif
                    </div>
                @endforeach
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
                            <div class="text-muted">
                                @if($pm->temComposicao()){{ $pm->descricaoItens() }} · @endif
                                R$ {{ number_format($pm->valor, 2, ',', '.') }} · horário fixo semanal
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted small">O pacote mensal é adquirido presencialmente na barbearia ou pelo nosso WhatsApp.</span>
                    <a class="btn btn-sm ms-auto" target="_blank" rel="noopener" style="background-color: var(--marrom); color:#1a1410"
                       href="https://wa.me/{{ $whatsappAdmin }}?text={{ urlencode('Olá! Quero saber mais sobre o pacote mensal.') }}">Quero assinar</a>
                </div>
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

            {{-- Modal: cancelar plano mensal (próprio cliente) — o aviso do acerto via WhatsApp faz parte do ato --}}
            <x-app.modal id="modal-cancelar-plano" title="Cancelar plano mensal" color="danger"
                :btn="[['lbl' => 'Confirmar cancelamento', 'color' => 'danger', 'onclick' => '$(\'#form-cancelar-plano\').submit()']]">
                <form method="POST" id="form-cancelar-plano" action="">
                    @csrf
                    <p>Tem certeza que deseja cancelar seu plano mensal?</p>
                    <ul class="text-muted small mb-3">
                        <li>As <strong>renovações automáticas param</strong> — nenhuma cobrança nova será gerada.</li>
                        <li>O ciclo atual <strong>já pago segue valendo</strong> até o fim (suas visitas agendadas continuam).</li>
                    </ul>
                    <div class="alert alert-warning small mb-3">
                        ⚠️ Importante: os valores das <strong>visitas restantes já pagas</strong> devem ser acertados
                        <strong>diretamente com a barbearia, pelo WhatsApp</strong>.
                    </div>
                    <a class="btn btn-sm btn-success" target="_blank" rel="noopener"
                       href="https://wa.me/{{ $whatsappAdmin }}?text={{ urlencode('Olá! Cancelei meu plano mensal pelo site e quero acertar os valores das visitas restantes.') }}">
                       💬 Falar no WhatsApp para acertar os valores
                    </a>
                </form>
            </x-app.modal>

            {{-- Modal: trocar dia/horário do plano — fica PENDENTE até a barbearia aprovar --}}
            <x-app.modal id="modal-trocar-dia" title="Trocar dia/horário do plano" color="primary"
                :btn="[['lbl' => 'Solicitar troca', 'color' => 'primary', 'onclick' => '$(\'#form-trocar-dia\').submit()']]">
                <form method="POST" id="form-trocar-dia" action="">
                    @csrf
                    <p>Escolha o novo dia e horário do seu plano mensal:</p>
                    <div class="row g-3 mb-2">
                        <div class="col-7">
                            <label for="troca_dia_semana" class="form-label">Dia da semana</label>
                            <select name="dia_semana" id="troca_dia_semana" class="form-select" required>
                                @foreach (['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'] as $i => $d)
                                    <option value="{{ $i }}">{{ $d }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-5">
                            <label for="troca_hora" class="form-label">Horário</label>
                            <select name="hora" id="troca_hora" class="form-select" required>
                                @for($m = 7 * 60; $m <= 21 * 60; $m += 15)
                                    @php $h = sprintf('%02d:%02d', intdiv($m, 60), $m % 60); @endphp
                                    <option value="{{ $h }}">{{ $h }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-info small mb-0">
                        ℹ️ A troca <strong>não é imediata</strong>: fica pendente e vale somente depois que a
                        barbearia <strong>confirmar</strong>. Até lá, seu plano segue no dia/horário atual.
                    </div>
                </form>
            </x-app.modal>
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
                                            <div class="consulta-card {{ !$consulta->confirmado ? 'consulta-pendente' : '' }} {{ $consulta->especial ? 'consulta-especial' : '' }} {{ $consulta->plano_mensal_id ? 'consulta-mensal' : '' }} {{ $consulta->status === 'reserva_renovacao' ? 'consulta-reserva' : '' }} d-flex {{ $isStaff ? 'justify-content-between align-items-center' : 'flex-column' }}" data-consulta-id="{{ $consulta->id }}">
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
                                                        @if($consulta->plano_mensal_id)<span class="badge bg-primary mb-1 ms-1">📅 Mensal</span>
                                                        @elseif($consulta->credito_servico_id)<span class="badge mb-1 ms-1" style="background:#6f42c6">🧾 Pacote avulso</span>
                                                        @else<span class="badge mb-1 ms-1" style="background:#6f42c6">✂️ Avulso</span>@endif
                                                        @if($consulta->status === 'reserva_renovacao')<span class="badge bg-danger mb-1 ms-1">⚠️ Sem saldo — renovar</span>@endif
                                                        <br>
                                                        <strong>Cliente:</strong> {{ $consulta->user->name }}
                                                        @if($consulta->funcionario)<br><strong>Barbeiro:</strong> {{ $consulta->funcionario->name }}@endif
                                                        @if($consulta->pagar_no_local) <span class="badge bg-info text-dark ms-1">Pagar no local</span>@endif
                                                        <br>
                                                        <strong>Serviço:</strong> {{ $consulta->servico_display }}
                                                        @if($isStaff)
                                                            <div class="d-flex flex-column gap-1 mt-2">
                                                                {!! $confirmaLinha(
                                                                    $lembreteBadge($consulta->lembrete_24h, 'Véspera'),
                                                                    $consulta->confirmado_em, 'Confirmou em:', 'bg-success', $consulta->lembrete_24h) !!}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                                @if($isStaff)
                                                    {{-- Comparecimento (gera penalidade em no-show) --}}
                                                    @if($consulta->status !== 'reserva_renovacao' && is_null($consulta->compareceu))
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

        // Liga/desliga "indicado" (compra de plano mensal pelo site) direto na tabela.
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
                    axios.get('{{ url("/") }}/usuarios-search', { params: { q, clientes: 1 } })
                        .then(res => {
                            // clientes=1 no backend já exclui adm/func
                            const lista = res.data.data || [];
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

        function excluirUsuarioHard(id, nome) {
            $('#excluir-usuario-hard-id').val(id);
            $('#excluir-usuario-hard-nome').text(nome);
            setTimeout(() => $('#modal-exc-usuario-hard').modal('show'), 250);
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
                    acoes = ((user.func != 1 || AUTH_ADM) && (!user.adm || AUTH_ID == 1)) ? celulaAcoes(user.id, user.name) : (user.adm ? '<td>ADM</td>' : '<td>COLAB</td>');
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
            $('#repasse_percent_edt_servico').val(servico.repasse_percent ?? '');
            $('#valor_edt_servico').val(servico.valor ?? '');

            $('[name="status_servico"]').prop('checked', false);
            $(`#status_servico_${servico.status}`).prop('checked', true);

            $('#recorrente_edt_servico').prop('checked', !!servico.recorrente);
            $('#edt_quinzenal').prop('checked', !!servico.recorrente && !!servico.quinzenal);
            // Quinzenal com 1ª ida ≠ 2ª: abre o editor de idas automaticamente.
            const idasDiferentes = !!servico.quinzenal
                && Array.isArray(servico.distribuicao) && servico.distribuicao.length >= 2
                && JSON.stringify(servico.distribuicao[0] ?? []) !== JSON.stringify(servico.distribuicao[1] ?? []);
            $('#edt_idas_diferentes').prop('checked', idasDiferentes);
            $('#edt_mostrar_clientes').prop('checked', !!servico.recorrente && !!servico.visivel_cliente);

            // Combo mensal: esconde o campo "valor" (calculado pela soma da composição).
            const edtValorWrap = document.getElementById('wrap_valor_edt');
            if (edtValorWrap) edtValorWrap.style.display = servico.recorrente ? 'none' : '';

            // Editor de composição/distribuição do combo mensal (Fase 5). Mostra sempre
            // que for mensal — mesmo sem composição ainda (p/ cadastrar a primeira vez).
            const edtEditor = document.getElementById('edt-combo-editor');
            if (servico.recorrente) {
                edtEditor.style.display = '';
                initComboEditor('edt', { composicao: servico.composicao || {}, distribuicao: servico.distribuicao || [[],[],[],[]] });
                // Atualiza rótulo/tamanho (quinzenal) depois do editor inicializado.
                $('#edt_quinzenal').trigger('change');
            } else {
                edtEditor.style.display = 'none';
            }

            $('#modal-edt-servico').modal('show');
        }

        // ── Calendário de gestão de disponibilidade ──────────────────────────
        let mgmDataAtual       = new Date();
        let mgmDiasDisponiveis = [];
        let mgmDiasFixos       = []; // dias não liberados mas com agendamento fixo (ex.: plano mensal)

        // Barbeiro cuja grade está sendo gerenciada (func → próprio; adm → selecionado).
        // Janela de horário POR BARBEIRO (do servidor; null = usa o padrão global).
        const mgmJanelas = @json($barbeiros->mapWithKeys(fn($b) => [$b->id => ['inicio' => $b->horario_inicio, 'fim' => $b->horario_fim]]));
        const mgmJanelaPadrao = { inicio: @json(\App\Models\PageContent::get('agenda', 'comercial_inicio', '08:00')), fim: @json(\App\Models\PageContent::get('agenda', 'comercial_fim', '17:45')) };

        function mgmAtualizarJanela() {
            const fid = mgmFid();
            const j = mgmJanelas[fid] || {};
            const ini = document.getElementById('mgm-janela-inicio');
            const fim = document.getElementById('mgm-janela-fim');
            if (ini) ini.value = j.inicio || mgmJanelaPadrao.inicio;
            if (fim) fim.value = j.fim || mgmJanelaPadrao.fim;
        }

        async function mgmSalvarJanela() {
            const ini = (document.getElementById('mgm-janela-inicio') || {}).value;
            const fim = (document.getElementById('mgm-janela-fim') || {}).value;
            if (!ini || !fim) { alert('Defina o início e o fim do horário.'); return; }
            try {
                await axios.post('/api/agenda/horario-comercial', { inicio: ini, fim: fim, funcionario_id: mgmFid() });
                mgmJanelas[mgmFid()] = { inicio: ini, fim: fim };
                alert('Horário deste barbeiro salvo: ' + ini + ' às ' + fim + '.\nVale para as próximas semanas que você liberar.');
            } catch (e) {
                alert(e.response?.data?.error ?? 'Falha ao salvar o horário.');
            }
        }

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
                const [resDisponiveis, resFixos] = await Promise.all([
                    axios.get(`/api/dias-disponiveis/${ano}/${mes}?funcionario_id=${mgmFid()}`),
                    axios.get(`/api/dias-fixos/${ano}/${mes}?funcionario_id=${mgmFid()}`),
                ]);
                mgmDiasDisponiveis = resDisponiveis.data; // array de números de dia
                mgmDiasFixos       = resFixos.data;        // dias fixos não liberados
            } catch(e) {
                mgmDiasDisponiveis = [];
                mgmDiasFixos = [];
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

                // Marca dias não liberados mas com agendamento fixo (ex.: plano mensal).
                if (!passado && mgmDiasFixos.includes(d)) {
                    div.classList.add('cal-fixos');
                    div.title = 'Dia não liberado, mas há agendamento fixo (ex.: plano mensal). Abra a semana para incluir o slot.';
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
            mgmAtualizarJanela();

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
                                            ${confirmaLinha(lembreteBadge(c.lembrete_24h, 'Véspera'), c.confirmado_em, 'Confirmou em:', 'bg-success', c.lembrete_24h)}
                                        </div>
                                    </div>
                                </div>
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

    </script>
    @else
    <script>
        // Cancelar plano mensal: copia a URL da assinatura do botão clicado para o
        // form do modal e o abre (a URL vem de route('assinaturas.cancelar') no blade).
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-cancelar-plano');
            if (!btn) return;
            document.getElementById('form-cancelar-plano').action = btn.dataset.url;
            new bootstrap.Modal(document.getElementById('modal-cancelar-plano')).show();
        });

        // Trocar dia/horário do plano: mesma coisa — URL da assinatura + pré-seleciona
        // o dia atual para o cliente partir dele.
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-trocar-dia');
            if (!btn) return;
            document.getElementById('form-trocar-dia').action = btn.dataset.url;
            const selDia = document.getElementById('troca_dia_semana');
            if (selDia && btn.dataset.diaAtual) selDia.value = btn.dataset.diaAtual;
            new bootstrap.Modal(document.getElementById('modal-trocar-dia')).show();
        });

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

    {{-- Cards recolhíveis: mantém abertos/fechados após reload via ?abrir=id1,id2 na URL.
         A URL é atualizada com replaceState (sem recarregar); sem o parâmetro, valem os
         defaults do servidor (ex.: Avisos abre sozinho quando há avisos). --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const param = new URLSearchParams(location.search).get('abrir');

            // Fonte de verdade: a URL quando o parâmetro existe; senão o DOM atual.
            let abertos;
            if (param !== null) {
                abertos = new Set(param === '' ? [] : param.split(',').filter(Boolean));
                document.querySelectorAll('.collapse[id]').forEach(function (el) {
                    const trigger = document.querySelector('[data-bs-target="#' + el.id + '"]');
                    if (!trigger) return;
                    const abrir = abertos.has(el.id);
                    el.classList.toggle('show', abrir);
                    trigger.setAttribute('aria-expanded', abrir ? 'true' : 'false');
                    trigger.classList.toggle('collapsed', !abrir);
                });
            } else {
                abertos = new Set([...document.querySelectorAll('.collapse[id].show')].map(el => el.id));
            }

            function sincronizarUrl() {
                const q = new URLSearchParams(location.search);
                const lista = [...abertos].filter(id => document.getElementById(id));
                if (lista.length) q.set('abrir', lista.join(',')); else q.delete('abrir');
                const qs = q.toString();
                history.replaceState(null, '', location.pathname + (qs ? '?' + qs : '') + location.hash);
            }

            document.addEventListener('show.bs.collapse', e => {
                if (!e.target.id) return;
                abertos.add(e.target.id);
                sincronizarUrl();
            });
            document.addEventListener('hide.bs.collapse', e => {
                if (!e.target.id) return;
                abertos.delete(e.target.id);
                sincronizarUrl();
            });
        });
    </script>
@endsection
