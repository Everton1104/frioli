@forelse($avisos as $aviso)
    <div class="d-flex justify-content-between align-items-start p-2 border-bottom" id="aviso-{{ $aviso->id }}">
        <div>
            @if($aviso->tipo === 'cancelamento')
                @php $cancEhStaff = (bool) $aviso->especial; @endphp
                <strong>{{ ucfirst($aviso->user->name) }}</strong> cancelou a consulta de
                <em>{{ $aviso->servico->descricao }}</em> do dia
                {{ $aviso->data_antiga->format('d/m/Y') }} às {{ $aviso->data_antiga->format('H:i') }}.
                <div class="alert alert-danger d-flex align-items-start gap-2 mt-2 mb-0 py-2 px-3">
                    <span class="badge bg-danger flex-shrink-0">Cancelado pelo cliente</span>
                    <small>
                        O cliente <strong>cancelou</strong> este agendamento.
                        @if($cancEhStaff)
                            Era um <strong>horário especial</strong> — verifique se precisa reorganizar a agenda.
                        @endif
                    </small>
                </div>
            @elseif($aviso->tipo === 'reagendamento')
                <strong>{{ ucfirst($aviso->user->name) }}</strong> reagendou
                <em>{{ $aviso->servico->descricao }}</em>:
                {{ $aviso->data_antiga->format('d/m/Y H:i') }}
                <strong>→</strong>
                {{ $aviso->data_nova->format('d/m/Y H:i') }}
            @elseif($aviso->tipo === 'confirmacao')
                <strong>{{ ucfirst($aviso->user->name) }}</strong> confirmou presença em
                <em>{{ $aviso->servico->descricao }}</em> no dia
                {{ $aviso->data_antiga->format('d/m/Y') }} às {{ $aviso->data_antiga->format('H:i') }}. ✅
            @elseif($aviso->tipo === 'reagendamento_solicitado')
                @php $avisoEhStaff = (bool) $aviso->especial; @endphp
                <strong>{{ ucfirst($aviso->user->name) }}</strong> solicitou reagendamento de
                <em>{{ $aviso->servico->descricao }}</em> marcado para
                {{ $aviso->data_antiga->format('d/m/Y') }} às {{ $aviso->data_antiga->format('H:i') }}. 🔄
                @if($avisoEhStaff)
                    <div class="alert alert-danger d-flex align-items-start gap-2 mt-2 mb-0 py-2 px-3">
                        <span class="badge bg-danger flex-shrink-0">Horário especial</span>
                        <small>Serviço especial — o cliente <strong>não pode</strong> reagendar. Somente o funcionário pode remarcar este horário (entre em contato com o cliente).</small>
                    </div>
                @endif
            @endif
            <br><small class="text-muted">{{ $aviso->created_at->diffForHumans() }}</small>
        </div>
        <button class="btn btn-sm btn-outline-secondary ms-3 flex-shrink-0"
                onclick="dispensarAviso({{ $aviso->id }})">
            Dispensar
        </button>
    </div>
@empty
    <p class="text-muted mb-0" id="avisos-vazio">Nenhum aviso pendente.</p>
@endforelse
