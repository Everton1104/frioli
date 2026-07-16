<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageContent extends Model
{
    protected $fillable = ['section', 'key', 'label', 'value', 'type'];

    public static function get(string $section, string $key, string $default = ''): string
    {
        $record = static::where('section', $section)->where('key', $key)->first();
        return $record ? ($record->value ?? $default) : $default;
    }

    /**
     * URL de um conteúdo do tipo imagem. Se houver valor (arquivo em conteudo/),
     * devolve a URL dele; senão devolve o $defaultUrl (ex.: Storage::url de um fixo).
     */
    public static function image(string $section, string $key, string $defaultUrl = ''): string
    {
        $value = static::get($section, $key, '');
        return $value !== ''
            ? \Illuminate\Support\Facades\Storage::disk('public')->url('conteudo/' . $value)
            : $defaultUrl;
    }

    public static function section(string $section): \Illuminate\Support\Collection
    {
        return static::where('section', $section)->get()->keyBy('key');
    }
}
