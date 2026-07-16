@props(['servicos', 'isAdm' => false])

<style>
    #calendario-container {
        width: 100%;
        max-width: 420px;
        margin: auto;
        font-family: Arial, sans-serif;
    }

    .cal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .cal-header button {
        padding: 6px 12px;
        cursor: pointer;
    }

    .cal-semana, .cal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        text-align: center;
        gap: 4px;
    }

    .cal-semana div {
        font-weight: bold;
        padding: 6px 0;
    }

    .cal-dia {
        padding: 10px 0;
        border-radius: 4px;
        background: #f1f1f1;
    }

    .cal-dia.disabled {
        opacity: 0.4;
        cursor: not-allowed !important;
    }

    .cal-fds {
        background: #dbeafe !important;
        color: #1e3a8a !important;
    }

    .cal-disponivel {
        background: #e9f7ef !important;
        color: #1e7e34 !important;
        cursor: pointer;
    }

    .cal-bloqueado {
        background: #f8d7da !important;
        color: #842029 !important;
    }

    .cal-selecionado {
        background: #3e8e41 !important;
        color: white !important;
    }

    .hora-item {
        padding: 8px;
        margin-bottom: 6px;
        border-radius: 4px;
        font-weight: 500;
    }

    .hora-livre {
        background: #e9f7ef;
        color: #1e7e34;
    }

    .hora-ocupado {
        background: #f8d7da;
        color: #842029;
        opacity: 0.6;
        cursor: not-allowed;
    }
</style>


<div id="calendario-container">
    <div class="cal-header">
        <span id="cal-prev" role="button">◀</span>
        <h3 id="cal-mes-ano"></h3>
        <span id="cal-next" role="button">▶</span>
    </div>
    <div class="cal-header" style="justify-content: center; margin-bottom: 10px;">
        <span id="cal-today" class="btn btn-sm btn-primary">Hoje</span>
    </div>

    <div class="cal-semana">
        <div>Dom</div>
        <div>Seg</div>
        <div>Ter</div>
        <div>Qua</div>
        <div>Qui</div>
        <div>Sex</div>
        <div>Sab</div>
    </div>

    <div id="cal-dias" class="cal-grid"></div>
</div>

<div class="container">
    <p class="fs-5 mt-3">Horários disponíveis</p>
    <p class="text-danger fs-3" id="horarios-erro"></p>
    <div id="horarios"></div>
</div>

<script>
    const isAdm = @json($isAdm);

    let diasDisponiveis = []; // array de números de dia com pelo menos 1 slot disponível
    let dataAtual = new Date();

    const calStorageKey = 'cal_mes_ano';
    const savedMes = sessionStorage.getItem(calStorageKey);
    if (savedMes) {
        const [ano, mes] = savedMes.split('-').map(Number);
        dataAtual = new Date(ano, mes, 1);
    }

    @if(old('dia_selecionado'))
        dataAtual = new Date("{{ old('dia_selecionado') }}");
    @endif

    document.getElementById('servico_id').addEventListener('change', function () {
        document.getElementById('horarios-erro').textContent = "";
        document.getElementById('horarios').innerHTML = "";
        document.querySelectorAll(".cal-dia").forEach(d => d.classList.remove("cal-selecionado"));
        document.getElementById('dia_selecionado').value = "";
        document.getElementById('hora_selecionada').value = "";
        document.getElementById('data_inicio').value = "";
        document.getElementById('data_fim').value = "";
    });

    async function carregarMes() {
        const ano = dataAtual.getFullYear();
        const mes = dataAtual.getMonth() + 1;
        sessionStorage.setItem(calStorageKey, `${ano}-${dataAtual.getMonth()}`);
        try {
            const res = await axios.get(`/api/dias-disponiveis/${ano}/${mes}`);
            diasDisponiveis = res.data;
        } catch (e) {
            diasDisponiveis = {};
        }
        gerarCalendario();
    }

    function gerarCalendario() {
        const ano = dataAtual.getFullYear();
        const mes = dataAtual.getMonth();

        const nomeMes = dataAtual.toLocaleString('pt-BR', { month: 'long' });
        document.getElementById("cal-mes-ano").textContent =
            `${nomeMes.charAt(0).toUpperCase() + nomeMes.slice(1)} ${ano}`;

        const primeiroDia = new Date(ano, mes, 1);
        const ultimoDia  = new Date(ano, mes + 1, 0);
        const inicioSemana = primeiroDia.getDay();
        const totalDias    = ultimoDia.getDate();

        const grid = document.getElementById("cal-dias");
        grid.innerHTML = "";

        // Dias do mês anterior (desabilitados)
        const ultimoDiaMesAnterior = new Date(ano, mes, 0).getDate();
        for (let i = inicioSemana - 1; i >= 0; i--) {
            const dia = ultimoDiaMesAnterior - i;
            const div = document.createElement("div");
            div.classList.add("cal-dia", "disabled");
            div.textContent = dia;
            const ds = new Date(ano, mes - 1, dia).getDay();
            if (ds === 0 || ds === 6) div.classList.add("cal-fds");
            grid.appendChild(div);
        }

        const hoje = new Date();
        hoje.setHours(0, 0, 0, 0);

        for (let d = 1; d <= totalDias; d++) {
            const div = document.createElement("div");
            div.classList.add("cal-dia");
            div.textContent = d;

            const dataDiv   = new Date(ano, mes, d);
            const diaSemana = dataDiv.getDay();

            if (dataDiv < hoje) {
                div.classList.add("disabled");
                if (diaSemana === 0 || diaSemana === 6) div.classList.add("cal-fds");
                grid.appendChild(div);
                continue;
            }

            if (diaSemana === 0 || diaSemana === 6) div.classList.add("cal-fds");

            // Verificar disponibilidade
            if (diasDisponiveis.includes(d)) {
                div.classList.add("cal-disponivel");
                div.onclick = function () {
                    document.getElementById('horarios-erro').textContent = "";
                    document.querySelectorAll(".cal-dia").forEach(el => el.classList.remove("cal-selecionado"));
                    div.classList.add("cal-selecionado");

                    const mesFormatado = String(mes + 1).padStart(2, '0');
                    const diaFormatado = String(d).padStart(2, '0');
                    const dataCompleta = `${ano}-${mesFormatado}-${diaFormatado}`;

                    document.getElementById('dia_selecionado').value = dataCompleta;
                    getHoras(dataCompleta);
                };
            } else {
                div.classList.add("cal-bloqueado", "disabled");
                div.title = "Dia não disponível";
            }

            grid.appendChild(div);
        }

        // Dias do próximo mês (desabilitados)
        const totalCelulas = inicioSemana + totalDias;
        const resto = totalCelulas % 7;
        if (resto !== 0) {
            for (let d = 1; d <= 7 - resto; d++) {
                const div = document.createElement("div");
                div.classList.add("cal-dia", "disabled");
                div.textContent = d;
                const ds = new Date(ano, mes + 1, d).getDay();
                if (ds === 0 || ds === 6) div.classList.add("cal-fds");
                grid.appendChild(div);
            }
        }

        // Restaurar seleção do dia após erro de validação
        @if(old('dia_selecionado'))
            const oldDate = "{{ old('dia_selecionado') }}";
            const [anoOld, mesOld, diaOld] = oldDate.split('-');
            if (anoOld == dataAtual.getFullYear() && mesOld == String(dataAtual.getMonth() + 1).padStart(2, '0')) {
                document.querySelectorAll('.cal-dia').forEach(div => {
                    if (parseInt(div.textContent) === Number(diaOld) && !div.classList.contains('disabled')) {
                        div.classList.add('cal-selecionado');
                        document.getElementById('dia_selecionado').value = oldDate;
                    }
                });
            }
        @endif
    }

    document.getElementById("cal-prev").addEventListener("click", () => {
        dataAtual.setMonth(dataAtual.getMonth() - 1);
        carregarMes();
    });

    document.getElementById("cal-next").addEventListener("click", () => {
        dataAtual.setMonth(dataAtual.getMonth() + 1);
        carregarMes();
    });

    document.getElementById("cal-today").addEventListener("click", () => {
        dataAtual = new Date();
        sessionStorage.removeItem(calStorageKey);
        carregarMes();
    });

    function getHoras(data) {
        const servico_id = document.getElementById('servico_id').value;
        // No form do staff o servico_id é um campo oculto alimentado pelo select
        // visível #servico_sel; o feedback de inválido vai no elemento visível.
        const campoServico = document.getElementById('servico_sel') ?? document.getElementById('servico_id');

        if (!servico_id) {
            campoServico.classList.add('is-invalid');
            document.querySelectorAll(".cal-dia").forEach(d => d.classList.remove("cal-selecionado"));
            return;
        }

        campoServico.classList.remove('is-invalid');
        document.getElementById('horarios').innerHTML = "<p>Carregando...</p>";

        // Especial (encaixe): pode sobrepor outros agendamentos e ter duração
        // personalizada. Os campos só existem no formulário do staff.
        const chkEsp = document.getElementById('chk_especial');
        const especial = chkEsp && chkEsp.checked ? 1 : 0;
        const durEsp = document.getElementById('duracao_especial');
        const duracao_min = (especial && durEsp && durEsp.value) ? durEsp.value : null;

        axios.get('/api/horarios/' + data, {
            params: {
                servico_id,
                ignore_id: document.getElementById('agendamento_id')?.value ?? null,
                especial,
                duracao_min,
            }
        })
        .then(response => mostrarHorarios(response.data))
        .catch(() => {
            document.getElementById('horarios').innerHTML = "<p>Falha ao carregar os horários</p>";
        });
    }

    function mostrarHorarios(lista) {
        const container = document.getElementById('horarios');
        container.innerHTML = "";

        if (!lista || lista.length === 0) {
            container.innerHTML = "<p class='text-muted'>Nenhum horário disponível neste dia.</p>";
            return;
        }

        const horaSelecionadaOld = "{{ old('hora_selecionada') }}";
        const diaSelecionadoOld  = "{{ old('dia_selecionado') }}";

        lista.forEach(item => {
            const div = document.createElement("div");
            div.classList.add("hora-item");

            if (item.ocupado) {
                div.classList.add("hora-ocupado");
                // Clientes veem apenas "Indisponível"; adm/func veem o motivo
                const label = isAdm ? (item.motivo ?? 'Indisponível') : 'Indisponível';
                div.textContent = item.hora + " — " + label;
            } else {
                div.classList.add("hora-livre");
                div.textContent = item.hora + " — Disponível";
                div.style.cursor = "pointer";

                div.onclick = function () {
                    document.getElementById('horarios-erro').textContent = "";
                    document.querySelectorAll(".hora-item").forEach(h => h.classList.remove("cal-selecionado"));
                    div.classList.add("cal-selecionado");

                    document.getElementById('hora_selecionada').value = item.hora;
                    const dataSelecionada = document.getElementById('dia_selecionado').value;
                    const dataInicio = `${dataSelecionada} ${item.hora}`;
                    document.getElementById('data_inicio').value = dataInicio;
                    calcularDataFim(dataInicio);
                };
            }

            // Restaurar seleção após erro de validação
            if (horaSelecionadaOld && item.hora === horaSelecionadaOld) {
                div.classList.add("cal-selecionado");
                const dataInicio = `${diaSelecionadoOld} ${item.hora}`;
                document.getElementById('hora_selecionada').value = item.hora;
                document.getElementById('data_inicio').value = dataInicio;
                calcularDataFim(dataInicio);
            }

            container.appendChild(div);
        });
    }

    function calcularDataFim(dataInicio) {
        const servico_id = document.getElementById('servico_id').value;
        const servico = SERVICOS.find(s => s.id == servico_id);

        // Especial com duração personalizada usa o valor digitado; senão, a do serviço.
        const chkEsp = document.getElementById('chk_especial');
        const durEsp = document.getElementById('duracao_especial');
        let minutos;
        if (chkEsp && chkEsp.checked && durEsp && durEsp.value) {
            minutos = parseInt(durEsp.value, 10);
        } else {
            if (!servico) return;
            const [h, m] = servico.duracao.split(':').map(Number);
            minutos = h * 60 + m;
        }
        if (!minutos || minutos <= 0) return;

        const inicio = new Date(dataInicio.replace(' ', 'T'));
        if (isNaN(inicio)) return;

        const fim = new Date(inicio.getTime() + minutos * 60000);

        const pad = n => String(n).padStart(2, '0');
        document.getElementById('data_fim').value =
            `${fim.getFullYear()}-${pad(fim.getMonth() + 1)}-${pad(fim.getDate())} ${pad(fim.getHours())}:${pad(fim.getMinutes())}`;
    }

    const SERVICOS = @json($servicos);

    document.addEventListener("DOMContentLoaded", function () {
        carregarMes();

        @if(old('dia_selecionado') && old('hora_selecionada'))
            carregarMes().then(() => {
                const dia = "{{ old('dia_selecionado') }}";
                getHoras(dia);
            });
        @endif
    });
</script>

@error('data_inicio')
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.getElementById('horarios-erro').textContent = "{{ $message }}";
        });
    </script>
@enderror
