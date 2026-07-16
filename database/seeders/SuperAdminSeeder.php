<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Cria (ou atualiza) o superadmin da Barbearia Frioli.
 *
 * Rode quando quiser:
 *   php artisan db:seed --class=SuperAdminSeeder
 *
 * Idempotente (procura por e-mail). O WhatsApp já vem verificado
 * (whatsapp_verified_at) pra dispensar o fluxo de verificação no 1º acesso.
 *
 * id=1: o dashboard dá poder extra (gerenciar/excluir outros admins) ao id=1.
 * Para o superadmin ficar com id=1, rode num banco ZERADO (ex.: após
 * `php artisan migrate:fresh`). O seeder atribui id=1 na criação se o slot
 * estiver livre; se ele já existir num banco não-vazio, o id só é realocado
 * para 1 quando o slot 1 estiver vago (sem realocar se houver dados vinculados).
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email    = 'everton.rodrigues1104@gmail.com';
        $whatsapp = '55' . preg_replace('/\D/', '', '11 93255-9542'); // 5511932559542

        $attrs = [
            'name'                     => 'Everton Rodrigues',
            'password'                 => bcrypt('99771122'),
            'whatsapp'                 => $whatsapp,
            'whatsapp_verified_at'     => now(),
            'email_verified_at'        => now(),
            'adm'                      => 1,
            'func'                     => 0,
            'excluido'                 => 0,
            'whatsapp_code'            => null,
            'whatsapp_code_expires_at' => null,
        ];

        $user = User::where('email', $email)->first();

        if ($user) {
            // Já existe: atualiza os dados. Se não for id=1 e o slot 1 estiver
            // livre (e sem agendamentos vinculados que orfanariam), realoca p/ id=1.
            $user->fill($attrs)->save();
            if ($user->id != 1 && !User::find(1) && \DB::table('agendamentos')->where('user_id', $user->id)->doesntExist()) {
                \DB::table('users')->where('id', $user->id)->update(['id' => 1]);
                $user = User::find(1);
            }
        } else {
            // Criação: garante id=1 se o slot estiver livre (banco novo).
            $user = new User();
            $user->fill($attrs);
            if (!User::find(1)) {
                $user->id = 1;
            }
            $user->save();
        }

        $this->command->info("Superadmin pronto: {$user->email} (id={$user->id}, adm={$user->adm}, whatsapp={$user->whatsapp})");
    }
}
