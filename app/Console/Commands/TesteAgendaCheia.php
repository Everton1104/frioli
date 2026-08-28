<?php

namespace App\Console\Commands;

use App\Models\AgendamentoModel;
use App\Models\ServicosModel;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/*
 * SIMULAÇÃO: enche o dia de um barbeiro com atendimentos fake encostados
 * (09h–18h), respeitando os agendamentos reais que ele já tem no dia — serve
 * para visualizar o calendário do dia com a agenda cheia.
 *
 * Cria clientes marcados (e-mail @agenda-cheia.test) com atendimentos de tipos
 * variados (confirmado / aguardando confirmação / pagar no local / encaixe
 * especial, alguns sobrepostos de propósito para ver as colunas).
 *
 *   php artisan teste:agenda-cheia                     → hoje, 1º barbeiro
 *   php artisan teste:agenda-cheia --barbeiro=8 --data=2026-08-29
 *   php artisan teste:agenda-cheia --limpar            → remove os fake (do dia/barbeiro)
 *   php artisan teste:agenda-cheia --limpar --data=2026-08-29
 */
class TesteAgendaCheia extends Command
{
    protected $signature   = 'teste:agenda-cheia {--barbeiro= : ID do barbeiro (default: 1º barbeiro ativo)} '
                           . '{--data= : Dia a lotar (Y-m-d; default: hoje)} {--limpar : Remove os atendimentos fake}';
    protected $description = 'Cria (ou remove, com --limpar) atendimentos fake para simular um dia de agenda cheia';

    private const DOMINIO_SEED = '@agenda-cheia.test';

    public function handle(): int
    {
        $barbeiro = $this->option('barbeiro')
            ? User::barbeiros()->find($this->option('barbeiro'))
            : User::barbeiros()->first();
        if (!$barbeiro) {
            $this->error('Barbeiro não encontrado (use --barbeiro=ID).');
            return 1;
        }

        $data = $this->option('data') ? Carbon::parse($this->option('data')) : now();
        $dia  = $data->copy()->startOfDay();
        $diaStr = $dia->toDateString();

        $seedUsers = User::where('email', 'like', '%' . self::DOMINIO_SEED)->pluck('id');

        if ($this->option('limpar')) {
            $n = AgendamentoModel::whereIn('user_id', $seedUsers)
                ->where('funcionario_id', $barbeiro->id)
                ->whereDate('data_inicio', $dia)
                ->delete();
            $this->info("Removidos {$n} atendimentos fake de {$barbeiro->name} em {$dia->format('d/m/Y')}.");
            if ($seedUsers->isNotEmpty() && AgendamentoModel::whereIn('user_id', $seedUsers)->doesntExist()) {
                User::whereIn('id', $seedUsers)->delete();
                $this->info('Clientes fake sem atendimentos também foram removidos.');
            }
            return 0;
        }

        $servicos = ServicosModel::where('excluido', 0)->where('status', 1)
            ->where('recorrente', 0)->where('visivel_cliente', 1)->get();
        if ($servicos->isEmpty()) {
            $this->error('Nenhum serviço ativo visível para simular.');
            return 1;
        }

        // Clientes fake (reusa os do seed anteriores; cria se faltarem).
        $nomes = ['Carlos Mendes', 'Rafael Souza', 'João Pedro Alves', 'Lucas Ferrari', 'Marcos Vinícius', 'Thiago Nunes', 'Pedro Henrique', 'Vitor Hugo', 'Gabriel Ramos', 'Igor Batista', 'Renato Dias', 'Felipe Costa'];
        $clientes = collect();
        $existentes = User::where('email', 'like', '%' . self::DOMINIO_SEED)->get();
        foreach (range(1, 6) as $i) {
            $nome = $nomes[$i - 1];
            $clientes->push(
                $existentes->firstWhere('name', $nome)
                ?? User::create([
                    'name'     => $nome,
                    'email'    => 'cheia' . $i . self::DOMINIO_SEED,
                    'whatsapp' => '5519900000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'password' => Hash::make('cheia'),
                    'excluido' => 0,
                    'whatsapp_verified_at' => now(),
                ])
            );
        }

        // Janelas já ocupadas no dia (agendamentos reais + reservas do barbeiro).
        $ocupados = AgendamentoModel::where('funcionario_id', $barbeiro->id)
            ->whereDate('data_inicio', $dia)
            ->whereIn('status', AgendamentoModel::OCUPANTES)
            ->get(['data_inicio', 'data_fim'])
            ->map(fn($a) => [$a->data_inicio->format('H') * 60 + $a->data_inicio->format('i'), $a->data_fim->format('H') * 60 + $a->data_fim->format('i')]);

        $minIni = 9 * 60;   // 09:00
        $minFim = 18 * 60;  // 18:00
        $tipos  = ['confirmado', 'pago_aguardando', 'pagar_no_local', 'confirmado', 'pago_aguardando'];
        $i = 0;
        $criados = 0;
        $cursor = $minIni;
        while ($cursor < $minFim) {
            $svc = $servicos[$i % $servicos->count()];
            $dur = ((int) substr($svc->duracao, 0, 2)) * 60 + ((int) substr($svc->duracao, 3, 2));

            $fim = $cursor + $dur;
            $colide = $ocupados->contains(fn($j) => $cursor < $j[1] && $fim > $j[0]);
            if ($colide) {
                $cursor += 15; // tenta 15min depois
                continue;
            }

            $cliente = $clientes[$i % $clientes->count()];
            $tipo = $tipos[$i % count($tipos)];
            AgendamentoModel::create([
                'user_id'        => $cliente->id,
                'servico_id'     => $svc->id,
                'funcionario_id' => $barbeiro->id,
                'data_inicio'    => $dia->copy()->addMinutes($cursor),
                'data_fim'       => $dia->copy()->addMinutes($fim),
                'status'         => $tipo === 'confirmado' ? AgendamentoModel::STATUS_CONFIRMADO : AgendamentoModel::STATUS_PAGO_AGUARDANDO,
                'confirmado'     => $tipo === 'confirmado' ? 1 : 0,
                'pagar_no_local' => $tipo === 'pagar_no_local',
            ]);
            $ocupados->push([$cursor, $fim]);
            $criados++;
            $i++;
            $cursor = $fim;
        }

        // Dois encaixes especiais SOBRE horários ocupados (testa as colunas de
        // sobreposição do calendário).
        foreach ([13 * 60 + 15, 16 * 60 + 45] as $iniEsp) {
            if ($iniEsp + 30 > $minFim) continue;
            AgendamentoModel::create([
                'user_id'        => $clientes[$criados % $clientes->count()]->id,
                'servico_id'     => $servicos[0]->id,
                'funcionario_id' => $barbeiro->id,
                'data_inicio'    => $dia->copy()->addMinutes($iniEsp),
                'data_fim'       => $dia->copy()->addMinutes($iniEsp + 30),
                'status'         => AgendamentoModel::STATUS_CONFIRMADO,
                'confirmado'     => 1,
                'especial'       => 1,
            ]);
            $criados++;
        }

        $this->info("Criados {$criados} atendimentos fake para {$barbeiro->name} em {$dia->format('d/m/Y')} (09h–18h).");
        $this->line("Limpar depois: php artisan teste:agenda-cheia --barbeiro={$barbeiro->id} --data={$diaStr} --limpar");
        return 0;
    }
}
