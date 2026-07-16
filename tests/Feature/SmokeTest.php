<?php

namespace Tests\Feature;

use App\Models\AgendamentoModel;
use App\Models\DisponibilidadeModel;
use App\Models\OrdemPagamento;
use App\Models\PageContent;
use App\Models\ServicosModel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Smoke test ponta-a-ponta do port anavertuan -> frioli.
 * Usa a conexão mysql real (DB já migrado), NÃO sqlite — porque a migration
 * add_retirada (coluna hoje renomeada para 'recorrente') usa ->after() (MySQL-only). Roda contra o banco 'frioli'.
 */
class SmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Força mysql (o phpunit.xml default é sqlite :memory:).
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'frioli']);
    }

    public function test_landing_renderiza_com_branding_da_barbearia(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Barbearia', false);
        $response->assertSee('Frioli', false);
        // Nada da marca/clínica antiga
        $this->assertStringNotContainsStringIgnoringCase('Vertuan', $response->content());
        $this->assertStringNotContainsStringIgnoringCase('Mounjaro', $response->content());
    }

    public function test_login_renderiza(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_dashboard_redireria_quando_deslogado(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_renderiza_para_admin_autenticado(): void
    {
        $admin = User::where('adm', 1)->first()
            ?? User::factory()->create([
                'adm' => 1,
                'whatsapp' => '5511999998888',
                'whatsapp_verified_at' => now(),
            ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
        $this->assertStringNotContainsStringIgnoringCase('Mounjaro', $response->content());
        $this->assertStringNotContainsStringIgnoringCase('anamnese', $response->content());
    }

    public function test_painel_admin_bloqueia_nao_admin(): void
    {
        $cliente = User::factory()->create([
            'adm' => 0,
            'func' => 0,
            'whatsapp' => '5511999990001',
            'whatsapp_verified_at' => now(),
        ]);

        $this->actingAs($cliente)->get('/admin')->assertStatus(403);
    }

    public function test_painel_admin_e_fotos_renderizam_para_admin(): void
    {
        $admin = User::where('adm', 1)->firstOrFail();

        $this->actingAs($admin)->get('/admin')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/fotos')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/sobre/texto/edit')->assertStatus(200);
    }

    public function test_admin_upload_de_imagem_editavel_aparece_na_landing(): void
    {
        $admin = User::where('adm', 1)->firstOrFail();
        Storage::fake('public');

        // Guarda o valor original p/ restaurar depois (não poluir o DB de dev).
        $original = PageContent::where('section', 'home')->where('key', 'logo')->value('value');

        try {
            // Form de edição de uma imagem (type=image) renderiza o campo de arquivo.
            $this->actingAs($admin)->get('/admin/home/logo/edit')
                ->assertStatus(200)
                ->assertSee('Nova imagem', false);

            // Faz upload de uma imagem para o logo do hero (home/logo).
            // Usa um JPEG real do storage (GD não está instalado p/ fake()->image()).
            $orig = storage_path('app/public/logo/logo-claro.jpeg');
            $this->assertFileExists($orig);
            $arquivo = new UploadedFile($orig, 'logo_hero.jpg', 'image/jpeg', null, true);

            $resp = $this->actingAs($admin)->put('/admin/home/logo', [
                'value' => $arquivo,
            ]);
            $resp->assertRedirect('/admin');

            // value salvo no DB + arquivo gravado.
            $rec = PageContent::where('section', 'home')->where('key', 'logo')->first();
            $this->assertNotNull($rec);
            $this->assertNotEmpty($rec->value);
            $this->assertTrue(Storage::disk('public')->exists('conteudo/' . $rec->value));

            // Landing passa a referenciar a imagem enviada (/storage/conteudo/...).
            $landing = $this->get('/');
            $landing->assertStatus(200);
            $this->assertStringContainsString('/storage/conteudo/' . $rec->value, $landing->content());
        } finally {
            PageContent::where('section', 'home')->where('key', 'logo')->update(['value' => $original ?? '']);
        }
    }

    public function test_cliente_reserva_horario_lock_e_cria_ordem_vinculada(): void
    {
        $cliente = User::factory()->create(['adm' => 0, 'func' => 0, 'whatsapp' => '5511988245901', 'whatsapp_verified_at' => now()]);
        $outro   = User::factory()->create(['adm' => 0, 'func' => 0, 'whatsapp' => '5511988245902', 'whatsapp_verified_at' => now()]);
        $servico = ServicosModel::create([
            'descricao' => 'Corte smoke ' . uniqid(), 'duracao' => '00:30:00', 'status' => 1,
            'excluido' => 0, 'visivel_cliente' => 1, 'recorrente' => 0, 'valor' => 50.00,
        ]);
        $data  = now()->addDays(3)->toDateString();
        $slots = [];
        foreach (['09:00', '09:15', '09:30'] as $h) {
            $slots[] = DisponibilidadeModel::create(['data' => $data, 'hora' => $h . ':00']);
        }
        $inicio = $data . ' 09:00';

        try {
            $r = $this->actingAs($cliente)->post('/agendar/reservar', [
                'servico_id'  => $servico->id,
                'data_inicio' => $inicio,
            ]);
            $r->assertRedirect();

            $ag = AgendamentoModel::where('user_id', $cliente->id)->where('servico_id', $servico->id)->first();
            $this->assertNotNull($ag);
            $this->assertEquals(AgendamentoModel::STATUS_AGUARDANDO_PAGAMENTO, $ag->status);

            $ordem = OrdemPagamento::where('agendamento_id', $ag->id)->first();
            $this->assertNotNull($ordem);
            $this->assertEquals(50.00, (float) $ordem->valor);
            $this->assertEquals($cliente->id, $ordem->user_id);

            // Lock do slot: outro cliente no mesmo horário é rejeitado.
            $this->actingAs($outro)->post('/agendar/reservar', [
                'servico_id'  => $servico->id,
                'data_inicio' => $inicio,
            ])->assertSessionHasErrors('data_inicio');
        } finally {
            $agIds = AgendamentoModel::whereIn('user_id', [$cliente->id, $outro->id])->pluck('id');
            OrdemPagamento::whereIn('agendamento_id', $agIds)->delete();
            AgendamentoModel::whereIn('user_id', [$cliente->id, $outro->id])->delete();
            foreach ($slots as $s) { $s->delete(); }
            $servico->delete();
            User::whereIn('id', [$cliente->id, $outro->id])->delete();
        }
    }

    public function test_admin_recusa_agendamento_pago_e_libera_slot(): void
    {
        $admin   = User::where('adm', 1)->firstOrFail();
        // Cliente sem whatsapp => não dispara notificação (sem chamada de rede no teste).
        $cliente = User::factory()->create(['adm' => 0, 'func' => 0]);
        $servico = ServicosModel::create([
            'descricao' => 'Corte recusa ' . uniqid(), 'duracao' => '00:30:00', 'status' => 1,
            'excluido' => 0, 'visivel_cliente' => 1, 'recorrente' => 0, 'valor' => 40.00,
        ]);
        $inicio = now()->addDays(4);
        $ag = AgendamentoModel::create([
            'user_id' => $cliente->id, 'servico_id' => $servico->id,
            'data_inicio' => $inicio, 'data_fim' => (clone $inicio)->addMinutes(30),
            'status' => AgendamentoModel::STATUS_PAGO_AGUARDANDO, 'pre_confirmado_em' => now(),
        ]);

        try {
            $this->actingAs($admin)->post("/agenda/{$ag->id}/recusar")->assertOk();
            $this->assertEquals(AgendamentoModel::STATUS_RECUSADO, $ag->fresh()->status);
            // Slot liberado: recusado não é ocupante.
            $this->assertFalse(
                AgendamentoModel::where('id', $ag->id)->whereIn('status', AgendamentoModel::OCUPANTES)->exists()
            );
        } finally {
            $ag->delete();
            $servico->delete();
            User::where('id', $cliente->id)->delete();
        }
    }
}
