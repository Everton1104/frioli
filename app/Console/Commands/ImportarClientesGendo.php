<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Importa a base de clientes do sistema antigo (Gendo) a partir do CSV
 * exportado ("clientes gendo.csv", separador ";", encoding Windows-1252").
 * O cliente do site não usa senha — entra por OTP de WhatsApp — então o
 * registro é criado igual ao do fluxo público (PublicRegistrationController):
 * nome + whatsapp + senha aleatória inutilizável. E-mail entra só quando
 * válido e ainda não usado (coluna UNIQUE). Idempotente: telefones já
 * cadastrados são pulados, pode rodar mais de uma vez (inclusive em produção
 * depois do deploy).
 */
class ImportarClientesGendo extends Command
{
    protected $signature = 'clientes:importar-gendo
                            {arquivo : caminho do CSV exportado do Gendo}
                            {--dry-run : apenas simular, sem gravar no banco}';

    protected $description = 'Importa clientes do CSV do Gendo (nome, WhatsApp e e-mail) para a tabela users.';

    public function handle(): int
    {
        $caminho = (string) $this->argument('arquivo');
        if (!is_file($caminho)) {
            $this->error("Arquivo não encontrado: {$caminho}");
            return self::FAILURE;
        }

        $conteudo = file_get_contents($caminho);
        if ($conteudo === false) {
            $this->error("Não foi possível ler: {$caminho}");
            return self::FAILURE;
        }

        // Export do Gendo vem em Windows-1252.
        $encoding = mb_detect_encoding($conteudo, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
        if ($encoding !== 'UTF-8') {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', $encoding ?: 'Windows-1252');
        }

        $linhas = explode("\n", rtrim($conteudo));
        $cabecalho = str_getcsv((string) array_shift($linhas), ';');
        $cabecalho = array_map(fn ($h) => trim((string) $h), $cabecalho);

        $ignorados = ['telefone' => [], 'duplicado' => [], 'existente' => []];
        $vistos = [];      // whatsapp => true (dedup dentro do próprio arquivo)
        $emailsUsados = []; // e-mail => true (coluna é UNIQUE)
        $aCriar = [];

        foreach ($linhas as $i => $linha) {
            if (trim($linha) === '') {
                continue;
            }
            $colunas = array_combine($cabecalho, array_pad(str_getcsv($linha, ';'), count($cabecalho), ''));

            $nome = trim(preg_replace('/\s+/', ' ', (string) ($colunas['Nome'] ?? '')));
            if (mb_strlen($nome) < 2) {
                continue;
            }

            $whatsapp = preg_replace('/\D/', '', ($colunas['DDI'] ?? '') . ($colunas['Telefone'] ?? ''));
            if (strlen($whatsapp) < 12 || strlen($whatsapp) > 13) {
                $ignorados['telefone'][] = sprintf('linha %d: %s (%s)', $i + 2, $nome, ($colunas['DDI'] ?? '') . ' ' . ($colunas['Telefone'] ?? ''));
                continue;
            }

            if (isset($vistos[$whatsapp])) {
                $ignorados['duplicado'][] = sprintf('linha %d: %s (mesmo telefone de %s)', $i + 2, $nome, $vistos[$whatsapp]);
                continue;
            }
            $vistos[$whatsapp] = $nome;

            $email = strtolower(trim((string) ($colunas['E-mail'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($emailsUsados[$email])) {
                $email = null;
            }

            $aCriar[] = [
                'name'      => $nome,
                'whatsapp'  => $whatsapp,
                'email'     => $email,
                'telefoneOriginal' => ($colunas['DDI'] ?? '') . ' ' . ($colunas['Telefone'] ?? ''),
            ];
            if ($email !== null) {
                $emailsUsados[$email] = true;
            }
        }

        // Idempotência contra o banco atual (roda 2x / produção com dados reais).
        $existentes = User::whereIn('whatsapp', array_keys($vistos))
            ->where('excluido', 0)
            ->pluck('name', 'whatsapp');
        $emailsNoBanco = User::whereNotNull('email')->pluck('email')->map(fn ($e) => strtolower($e))->flip();
        $finais = [];
        foreach ($aCriar as $c) {
            if ($existentes->has($c['whatsapp'])) {
                $ignorados['existente'][] = sprintf('%s (%s) já cadastrado como "%s"', $c['name'], $c['whatsapp'], $existentes[$c['whatsapp']]);
                continue;
            }
            if ($c['email'] !== null && $emailsNoBanco->has($c['email'])) {
                $c['email'] = null; // e-mail já pertence a outra conta — não trava o insert (UNIQUE)
            }
            $finais[] = $c;
        }
        $aCriar = $finais;

        $this->info(sprintf(
            '%d clientes no arquivo | %d a importar | %d telefone inválido | %d duplicado no arquivo | %d já cadastrados',
            count($vistos) + count($ignorados['telefone']),
            count($aCriar),
            count($ignorados['telefone']),
            count($ignorados['duplicado']),
            count($ignorados['existente'])
        ));

        foreach (['telefone', 'duplicado'] as $tipo) {
            foreach ($ignorados[$tipo] as $linha) {
                $this->warn("  [ignorado] {$linha}");
            }
        }
        foreach (array_slice($ignorados['existente'], 0, 20) as $linha) {
            $this->line("  [pulado] {$linha}");
        }

        if ($this->option('dry-run')) {
            $this->line('--dry-run: nada foi gravado.');
            return self::SUCCESS;
        }
        if (empty($aCriar)) {
            $this->info('Nada a importar.');
            return self::SUCCESS;
        }

        $confirmar = $this->confirm('Importar ' . count($aCriar) . ' clientes?');
        if (!$confirmar) {
            $this->line('Cancelado.');
            return self::SUCCESS;
        }

        $criados = 0;
        $comEmail = 0;
        foreach ($aCriar as $c) {
            User::create([
                'name'     => $c['name'],
                'whatsapp' => $c['whatsapp'],
                'email'    => $c['email'],
                // Sem login por senha — mesmo padrão do cadastro público por OTP.
                'password' => Hash::make(Str::random(16)),
            ]);
            $criados++;
            if ($c['email'] !== null) {
                $comEmail++;
            }
        }

        $this->info("Importados: {$criados} ({$comEmail} com e-mail).");
        return self::SUCCESS;
    }
}
