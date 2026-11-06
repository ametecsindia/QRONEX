<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    /** Replaces {{var}} placeholders (email) and fills {#var#} slots in order (DLT SMS). */
    public static function render(string $text, array $vars): string
    {
        $text = preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => (string) ($vars[$m[1]] ?? ''), $text);
        $slots = array_values($vars);
        $i = 0;

        return preg_replace_callback('/\{#var#\}/', function () use (&$i, $slots) { return (string) ($slots[$i++] ?? ''); }, $text);
    }
}
