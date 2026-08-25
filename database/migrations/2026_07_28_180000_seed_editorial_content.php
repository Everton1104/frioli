<?php

use App\Models\PageContent;
use Illuminate\Database\Migrations\Migration;

/**
 * Cria (updateOrCreate, migrate-once) as linhas de conteúdo editável do site
 * editorial, a partir do catálogo config/content.php. Depois de rodar, todos
 * esses textos ficam editáveis pelo painel /admin. Não toca nas linhas de
 * imagem (type=image) nem nas chaves antigas que não estão no catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ((array) config('content') as $section => $items) {
            foreach ($items as $key => $meta) {
                PageContent::updateOrCreate(
                    ['section' => $section, 'key' => $key],
                    [
                        'label' => $meta['label'] ?? $key,
                        'value' => $meta['value'] ?? '',
                        'type'  => 'text',
                    ]
                );
            }
        }

        // Fotos das seções (type=image, value vazio → a home usa o fallback em
        // storage/app/public/site/ até o admin subir uma pelo painel). Chaves
        // NOVAS (não reaproveita as antigas branding.logo/home.bg/sobre.foto,
        // que podem ter valores velhos em produção).
        $imagens = [
            ['home',      'hero',     'Hero — foto (protagonista no holofote)'],
            ['sobre',     'interior', 'Sobre — foto do salão'],
            ['espaco',    'salao',    'Espaço — foto ampla do salão'],
            ['espaco',    'cadeira',  'Espaço — cadeira'],
            ['espaco',    'toalha',   'Espaço — toalha quente'],
            ['barbeiros', 'b1_foto',  'Barbeiro 1 — retrato (Frioli)'],
            ['barbeiros', 'b2_foto',  'Barbeiro 2 — retrato (Tiago)'],
            ['trabalhos', 'g1',       'Trabalhos — foto 1'],
            ['trabalhos', 'g2',       'Trabalhos — foto 2'],
            ['trabalhos', 'g3',       'Trabalhos — foto 3'],
            ['trabalhos', 'g4',       'Trabalhos — foto 4'],
        ];
        foreach ($imagens as [$section, $key, $label]) {
            PageContent::updateOrCreate(
                ['section' => $section, 'key' => $key],
                ['label' => $label, 'type' => 'image', 'value' => '']
            );
        }
    }

    public function down(): void
    {
        foreach ((array) config('content') as $section => $items) {
            PageContent::where('section', $section)
                ->whereIn('key', array_keys($items))
                ->delete();
        }
        PageContent::where('type', 'image')
            ->whereIn('section', ['home', 'sobre', 'espaco', 'barbeiros', 'trabalhos'])
            ->delete();
    }
};
