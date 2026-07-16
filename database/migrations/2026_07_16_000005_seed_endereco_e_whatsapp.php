<?php

use App\Models\PageContent;
use Illuminate\Database\Migrations\Migration;

/**
 * Semeia a seção "endereco" (título, logradouro, link do mapa e CTA do hero) e
 * atualiza o número de WhatsApp do contato para o da Barbearia Frioli.
 * O logradouro também alimenta o <iframe> do Google Maps (q=endereço), então
 * editar o endereço no /admin move o pin do mapa junto. Idempotente (updateOrCreate).
 */
return new class extends Migration
{
    public function up(): void
    {
        $itens = [
            ['section' => 'endereco', 'key' => 'titulo', 'label' => 'Endereço — título', 'value' => 'Onde estamos'],
            ['section' => 'endereco', 'key' => 'rua',   'label' => 'Endereço — logradouro (também usado no mapa)', 'value' => 'R. do Retiro, 329 - Vila Virginia, Jundiaí - SP, 13201-030'],
            ['section' => 'endereco', 'key' => 'link',  'label' => 'Endereço — link "Como chegar" (Google Maps)', 'value' => 'https://maps.app.goo.gl/YpWyqD8E9KypARkg6'],
            ['section' => 'endereco', 'key' => 'cta',   'label' => 'Endereço — texto do botão do hero', 'value' => 'Como chegar'],

            // Contato — número de WhatsApp da barbearia (só dígitos, com DDI 55).
            ['section' => 'contato', 'key' => 'whatsapp_numero', 'label' => 'Contato — número WhatsApp (só dígitos, com 55)', 'value' => '5511988245815'],
        ];

        foreach ($itens as $item) {
            PageContent::updateOrCreate(
                ['section' => $item['section'], 'key' => $item['key']],
                ['label' => $item['label'], 'value' => $item['value'], 'type' => 'text']
            );
        }
    }

    public function down(): void
    {
        PageContent::where('section', 'endereco')->delete();
        PageContent::where('section', 'contato')->where('key', 'whatsapp_numero')->delete();
    }
};
