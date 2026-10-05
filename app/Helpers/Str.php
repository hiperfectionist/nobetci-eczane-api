<?php

namespace App\Helpers;

class Str
{
    /**
     * Convert string to Turkish lowercase
     */
    public static function lowerTr(string $text): string
    {
        $text = str_replace(['I', 'İ'], ['ı', 'i'], $text);
        return mb_strtolower($text, 'UTF-8');
    }

    /**
     * Convert string to Turkish uppercase
     */
    public static function upperTr(string $text): string
    {
        $text = str_replace(['ı', 'i'], ['I', 'İ'], $text);
        return mb_strtoupper($text, 'UTF-8');
    }

    /**
     * Convert string to Turkish title case (e.g. Kadıköy, Çankaya)
     */
    public static function titleTr(string $text): string
    {
        $words = explode(' ', self::lowerTr($text));
        $titled = array_map(function ($word) {
            if ($word === '') {
                return '';
            }
            $first = mb_substr($word, 0, 1, 'UTF-8');
            $rest = mb_substr($word, 1, null, 'UTF-8');
            return self::upperTr($first) . $rest;
        }, $words);

        return implode(' ', $titled);
    }

    /**
     * Normalize Turkish characters to ASCII and strip extra whitespace
     */
    public static function slug(string $text): string
    {
        $search  = ['ç', 'Ç', 'ğ', 'Ğ', 'ı', 'I', 'İ', 'i', 'ö', 'Ö', 'ş', 'Ş', 'ü', 'Ü'];
        $replace = ['c', 'c', 'g', 'g', 'i', 'i', 'i', 'i', 'o', 'o', 's', 's', 'u', 'u'];
        $text = str_replace($search, $replace, $text);
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        return trim($text, '-');
    }

    /**
     * Check if needle is loosely in haystack (case-insensitive & Turkish character normalized)
     */
    public static function contains(?string $haystack, ?string $needle): bool
    {
        if ($haystack === null || $needle === null) {
            return false;
        }
        $hSlug = self::slug($haystack);
        $nSlug = self::slug($needle);
        if ($nSlug === '') {
            return true;
        }
        return strpos($hSlug, $nSlug) !== false;
    }

    /**
     * Clean messy whitespace, tabs, and duplicate newlines
     */
    public static function clean(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }
}
