<?php

use App\Models\PageContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona coluna `type` (text|image) a page_contents e cria os registros das
 * imagens editáveis pelo painel. O `value` começa vazio — a landing usa fallback
 * até o admin subir uma imagem (em storage/app/public/conteudo/).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_contents', function (Blueprint $table) {
            $table->string('type')->default('text')->after('label');
        });

        $imagens = [
            ['section' => 'branding', 'key' => 'logo',        'label' => 'Logo (cabeçalho, login e e-mail)'],
            ['section' => 'home',     'key' => 'bg',          'label' => 'Hero — foto de fundo (aparece desfocada)'],
            ['section' => 'home',     'key' => 'logo',        'label' => 'Hero — logo sobre o fundo'],
            ['section' => 'sobre',    'key' => 'foto',        'label' => 'Sobre — foto do barbeiro'],
            ['section' => 'servicos', 'key' => 'card1_foto',  'label' => 'Card 1 — foto'],
            ['section' => 'servicos', 'key' => 'card2_foto',  'label' => 'Card 2 — foto'],
            ['section' => 'servicos', 'key' => 'card3_foto',  'label' => 'Card 3 — foto'],
        ];

        foreach ($imagens as $item) {
            PageContent::updateOrCreate(
                ['section' => $item['section'], 'key' => $item['key']],
                ['label' => $item['label'], 'type' => 'image', 'value' => '']
            );
        }
    }

    public function down(): void
    {
        PageContent::where('type', 'image')->delete();
        Schema::table('page_contents', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
