<?php

declare(strict_types=1);

namespace App\Core;

final class Str
{
    public static function slug(string $text): string
    {
        $text = trim($text);
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = $transliterated !== false && $transliterated !== '' ? $transliterated : $text;
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');

        return $text === '' ? 'item' : $text;
    }
}
