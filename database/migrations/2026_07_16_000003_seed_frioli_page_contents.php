<?php

use App\Models\PageContent;
use Illuminate\Database\Migrations\Migration;

/**
 * Popula page_contents com os textos padrão da landing da Barbearia Frioli.
 * Idempotente (updateOrCreate) — seguro em produção. Editáveis pelo /admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        $contents = [
            // Home / hero
            ['section' => 'home', 'key' => 'tagline', 'label' => 'Hero — frase de destaque', 'value' => 'Estilo, precisão e atendimento de respeito.'],

            // Sobre
            ['section' => 'sobre', 'key' => 'titulo', 'label' => 'Sobre — título', 'value' => 'Sobre nós'],
            ['section' => 'sobre', 'key' => 'texto', 'label' => 'Sobre — texto', 'value' => 'Na <strong>Barbearia Frioli</strong>, unimos técnica e atendimento de qualidade para você sair sempre com o visual impecável. Cortes clássicos e modernos, barba na régua e um ambiente acolhedor para o seu momento de cuidado. Agende pelo painel e receba lembretes pelo WhatsApp.'],

            // Serviços
            ['section' => 'servicos', 'key' => 'titulo', 'label' => 'Serviços — título', 'value' => 'Serviços'],
            ['section' => 'servicos', 'key' => 'nota',  'label' => 'Serviços — rodapé', 'value' => 'Outros serviços e pacotes podem ser consultados pelo WhatsApp.'],
            ['section' => 'servicos', 'key' => 'card1_titulo', 'label' => 'Serviços — Card 1 (título)', 'value' => 'Corte'],
            ['section' => 'servicos', 'key' => 'card1_desc',   'label' => 'Serviços — Card 1 (descrição)', 'value' => 'Corte masculino personalizado, adaptado ao seu estilo e formato de rosto. Acabamento e finalização inclusos.'],
            ['section' => 'servicos', 'key' => 'card2_titulo', 'label' => 'Serviços — Card 2 (título)', 'value' => 'Barba'],
            ['section' => 'servicos', 'key' => 'card2_desc',   'label' => 'Serviços — Card 2 (descrição)', 'value' => 'Toalha quente, navalha e hidratação para uma barba alinhada e bem cuidada.'],
            ['section' => 'servicos', 'key' => 'card3_titulo', 'label' => 'Serviços — Card 3 (título)', 'value' => 'Combo Corte + Barba'],
            ['section' => 'servicos', 'key' => 'card3_desc',   'label' => 'Serviços — Card 3 (descrição)', 'value' => 'O pacote completo para renovar o visual do começo ao fim, com prioridade no agendamento.'],

            // Contato
            ['section' => 'contato', 'key' => 'titulo',          'label' => 'Contato — título', 'value' => 'Entre em Contato'],
            ['section' => 'contato', 'key' => 'subtitulo',       'label' => 'Contato — subtítulo', 'value' => 'Agende seu horário ou tire suas dúvidas:'],
            ['section' => 'contato', 'key' => 'whatsapp_numero', 'label' => 'WhatsApp — somente números (com 55)', 'value' => '5511975712377'],
            ['section' => 'contato', 'key' => 'whatsapp_label',  'label' => 'WhatsApp — texto do botão', 'value' => 'Falar no WhatsApp'],
        ];

        foreach ($contents as $item) {
            PageContent::updateOrCreate(
                ['section' => $item['section'], 'key' => $item['key']],
                ['label' => $item['label'], 'value' => $item['value']]
            );
        }
    }

    public function down(): void
    {
        PageContent::whereIn('section', ['home', 'sobre', 'servicos', 'contato'])->delete();
    }
};
