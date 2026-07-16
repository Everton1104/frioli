<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GalleryImage extends Model
{
    protected $fillable = ['filename', 'title', 'description', 'ordem'];

    protected static function booted(): void
    {
        // Ao excluir o registro, remove o arquivo físico da galeria.
        static::deleting(function (GalleryImage $img) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete('galeria/' . $img->filename);
        });
    }
}
