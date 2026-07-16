<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use App\Models\PageContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Painel administrativo: edição de conteúdo da landing (PageContent) e
 * gerenciamento da galeria de fotos (GalleryImage). Padrão replicado do
 * neuroandreaferro. Acesso exclusivo de admins (adm = 1).
 */
class AdminController extends Controller
{
    // Guard de admin fica no middleware 'admin' (alias em bootstrap/app.php),
    // aplicado nas rotas /admin.

    // ── Conteúdo da landing ──────────────────────────────────────────────

    public function index()
    {
        $sections = PageContent::all()->groupBy('section');
        return view('admin.index', compact('sections'));
    }

    public function edit(string $section, string $key)
    {
        $content = PageContent::where('section', $section)->where('key', $key)->firstOrFail();
        return view('admin.edit', compact('content'));
    }

    public function update(Request $request, string $section, string $key)
    {
        $content = PageContent::where('section', $section)->where('key', $key)->firstOrFail();

        // Conteúdo do tipo IMAGEM: upload para storage/app/public/conteudo/.
        if ($content->type === 'image') {
            // "Remover imagem": volta ao fallback (value vazio).
            if ($request->has('remover')) {
                if ($content->value) {
                    Storage::disk('public')->delete('conteudo/' . $content->value);
                }
                $content->value = '';
                $content->save();
                return redirect()->route('admin.index')->with('success', 'Imagem removida (voltou ao padrão).');
            }

            $data = $request->validate(['value' => 'required|image|max:5120']);
            $nome = basename($data['value']->store('conteudo', 'public'));

            // Apaga a imagem anterior se havia uma customizada.
            if ($content->value) {
                Storage::disk('public')->delete('conteudo/' . $content->value);
            }
            $content->value = $nome;
            $content->save();

            return redirect()->route('admin.index')->with('success', 'Imagem atualizada com sucesso!');
        }

        // Conteúdo do tipo TEXTO.
        $data = $request->validate(['value' => 'nullable|string']);
        $content->value = $data['value'];
        $content->save();

        return redirect()->route('admin.index')->with('success', 'Conteúdo atualizado com sucesso!');
    }

    // ── Galeria de fotos ─────────────────────────────────────────────────

    public function fotos()
    {
        $fotos = GalleryImage::orderBy('ordem')->orderBy('id')->get();
        return view('admin.fotos', compact('fotos'));
    }

    public function fotosUpload(Request $request)
    {
        $request->validate([
            'imagens'   => 'required|array|min:1',
            'imagens.*' => 'required|image|max:5120', // 5 MB por foto
        ]);

        $maxOrdem = GalleryImage::max('ordem') ?? -1;

        foreach ($request->file('imagens') as $file) {
            $filename = uniqid('foto_') . '.' . $file->getClientOriginalExtension();
            $file->storeAs('galeria', $filename, 'public');
            GalleryImage::create([
                'filename'    => $filename,
                'title'       => null,
                'description' => null,
                'ordem'       => ++$maxOrdem,
            ]);
        }

        return redirect()->route('admin.fotos')->with('success', 'Foto(s) adicionada(s) com sucesso!');
    }

    public function fotosUpdate(Request $request, GalleryImage $foto)
    {
        $request->validate([
            'title'       => 'nullable|string|max:200',
            'description' => 'nullable|string',
        ]);

        $foto->update($request->only('title', 'description'));

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin.fotos')->with('success', 'Foto atualizada!');
    }

    public function fotosDelete(GalleryImage $foto)
    {
        $foto->delete(); // o boot do model remove o arquivo físico
        return redirect()->route('admin.fotos')->with('success', 'Foto removida!');
    }

    public function fotosReorder(Request $request)
    {
        $request->validate([
            'ordem'   => 'required|array',
            'ordem.*' => 'integer',
        ]);

        foreach ($request->ordem as $id => $pos) {
            GalleryImage::where('id', $id)->update(['ordem' => $pos]);
        }

        return response()->json(['ok' => true]);
    }
}
