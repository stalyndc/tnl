<?php

namespace App\Support;

class ContentFormatter
{
    public static function cleanTitle(string $string): string
    {
        try {
            $replacements = [
                '&#8217;' => "'",
                '&#8216;' => "'",
                '&#8220;' => '"',
                '&#8221;' => '"',
                '&#8211;' => '-',
                '&#8212;' => '--',
                '&apos;' => "'",
                '&quot;' => '"',
                '&amp;' => '&'
            ];

            $string = str_replace(array_keys($replacements), array_values($replacements), $string);
            $string = html_entity_decode($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $string = preg_replace_callback('/&#(\d+);/', function ($matches) {
                return mb_chr((int) $matches[1], 'UTF-8');
            }, $string);

            return $string;
        } catch (\Throwable $e) {
            \Logger::warning('Error cleaning title', [
                'error' => $e->getMessage(),
                'string' => mb_substr($string, 0, 50)
            ]);

            return $string;
        }
    }
}
