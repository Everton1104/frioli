<?php

namespace Database\Seeders;

use App\Models\GalleryImage;
use App\Models\PageContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Importa as fotos que já estão em storage/app/public (cortes/ + galeria/) para
 * a galeria gerenciável (tabela gallery_images, arquivos em galeria/).
 *
 * Rodar: php artisan db:seed --class=SiteContentSeeder
 * Idempotente (firstOrCreate por filename) e seguro em produção: pula arquivos
 * que não existirem (lá as fotos entram via o painel /admin/fotos).
 */
class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk('public');

        // Os cortes originais estão em cortes/ — copia pra galeria/ (padrão da GalleryImage).
        for ($i = 1; $i <= 8; $i++) {
            $src = "cortes/corte{$i}.jpeg";
            $dst = "galeria/corte{$i}.jpeg";
            if (!$disk->exists($dst) && $disk->exists($src)) {
                $disk->copy($src, $dst);
            }
        }

        $fotos = [
            'corte1', 'corte2', 'corte3', 'corte4', 'corte5', 'corte6', 'corte7', 'corte8',
            'cadeira', 'produto', 'aviso1', 'aviso2', 'aviso4', 'aviso5', 'aviso7',
        ];

        $ordem = 0;
        foreach ($fotos as $nome) {
            $file = "galeria/{$nome}.jpeg";
            if (!$disk->exists($file)) {
                continue; // produção: entra pelo painel
            }
            GalleryImage::firstOrCreate(
                ['filename' => "{$nome}.jpeg"],
                ['title' => null, 'description' => null, 'ordem' => $ordem++]
            );
        }

        $this->command->info('Galeria importada: ' . GalleryImage::count() . ' fotos em storage/app/public/galeria/.');

        // ── Imagens das seções (logo, fundo do hero, foto do sobre, cards) ──
        // Copia os arquivos atuais para conteudo/ e seta o value (só se vazio,
        // pra não sobrescrever upload do admin). Em produção esses arquivos não
        // existem — o admin sobe pelo painel.
        $map = [
            ['section' => 'branding', 'key' => 'logo',       'from' => 'logo/logo-navbar.jpeg', 'to' => 'logo-navbar.jpeg'],
            ['section' => 'home',     'key' => 'bg',         'from' => 'galeria/barbearia.jpeg', 'to' => 'bg.jpeg'],
            ['section' => 'home',     'key' => 'logo',       'from' => 'logo/logo-claro.jpeg',   'to' => 'logo-claro.jpeg'],
            ['section' => 'sobre',    'key' => 'foto',       'from' => 'galeria/perfil.jpeg',    'to' => 'perfil.jpeg'],
            ['section' => 'servicos', 'key' => 'card1_foto', 'from' => 'galeria/corte1.jpeg',    'to' => 'card1.jpeg'],
            ['section' => 'servicos', 'key' => 'card2_foto', 'from' => 'galeria/corte2.jpeg',    'to' => 'card2.jpeg'],
            ['section' => 'servicos', 'key' => 'card3_foto', 'from' => 'galeria/corte3.jpeg',    'to' => 'card3.jpeg'],
        ];

        $importadas = 0;
        foreach ($map as $item) {
            if (!$disk->exists($item['from'])) {
                continue;
            }
            $dst = 'conteudo/' . $item['to'];
            if (!$disk->exists($dst)) {
                $disk->copy($item['from'], $dst);
            }
            $pc = PageContent::where('section', $item['section'])->where('key', $item['key'])->first();
            if ($pc && empty($pc->value)) {
                $pc->update(['value' => $item['to']]);
                $importadas++;
            }
        }
        $this->command->info("Imagens de seção importadas: {$importadas} em storage/app/public/conteudo/.");
    }
}
