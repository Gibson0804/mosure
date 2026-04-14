<?php

namespace App\Helper;

class AiJsonParser
{
    public static function parseLenientJson(string $text): ?array
    {
        $direct = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($direct)) {
            return $direct;
        }

        // 处理 ```json 起始但无闭合 ``` 的情况
        $pos = stripos($text, '```json');
        if ($pos !== false) {
            $block = substr($text, $pos + 7);
            $blockSan = self::stripJsonComments($block);
            $candidate = self::extractFirstJson($blockSan);
            if (is_string($candidate)) {
                $candidate = self::stripTrailingCommas($candidate);
                $arr = json_decode($candidate, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($arr)) {
                    return $arr;
                }
            }
            $salvaged = self::salvageFromFirstBracket($blockSan);
            $arrS = json_decode(self::stripTrailingCommas($salvaged), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($arrS)) {
                return $arrS;
            }
        }

        $matches = [];
        if (preg_match_all('/```(?:json)?\s*([\s\S]*?)\s*```/i', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $candidate = self::stripJsonComments($block);
                $candidate = self::stripTrailingCommas($candidate);
                $arr = json_decode($candidate, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($arr)) {
                    return $arr;
                }
            }
        }

        $candidate0 = self::extractFirstJson($text);
        if (is_string($candidate0)) {
            $arr0 = json_decode(self::stripTrailingCommas($candidate0), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($arr0)) {
                return $arr0;
            }
        }

        $sanitized = self::stripJsonComments($text);
        $extracted = self::extractFirstJson($sanitized);
        if (is_string($extracted)) {
            $extracted = self::stripTrailingCommas($extracted);
            $arr = json_decode($extracted, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($arr)) {
                return $arr;
            }
        }

        $sanitized2 = self::stripTrailingCommas($sanitized);
        $arr2 = json_decode($sanitized2, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($arr2)) {
            return $arr2;
        }

        return null;
    }

    private static function extractFirstJson(string $text): ?string
    {
        $len = strlen($text);
        $inStr = false;
        $str = '';
        $start = -1;
        $stack = [];
        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];
            if ($inStr) {
                if ($ch === '\\') {
                    $i++;

                    continue;
                }
                if ($ch === $str) {
                    $inStr = false;
                    $str = '';
                }

                continue;
            } else {
                if ($ch === '"' || $ch === "'") {
                    $inStr = true;
                    $str = $ch;

                    continue;
                }
                if ($ch === '{' || $ch === '[') {
                    if ($start === -1) {
                        $start = $i;
                        $stack = [$ch];
                    } else {
                        $stack[] = $ch;
                    }

                    continue;
                }
                if ($ch === '}' || $ch === ']') {
                    if (empty($stack)) {
                        continue;
                    }
                    $open = array_pop($stack);
                    if (($open === '{' && $ch !== '}') || ($open === '[' && $ch !== ']')) {
                        continue;
                    }
                    if (empty($stack) && $start !== -1) {
                        $end = $i;

                        return substr($text, $start, $end - $start + 1);
                    }
                }
            }
        }

        return null;
    }

    private static function salvageFromFirstBracket(string $text): string
    {
        $posArr = strpos($text, '[');
        $posObj = strpos($text, '{');
        if ($posArr === false && $posObj === false) {
            return '[]';
        }
        $start = ($posArr !== false && ($posObj === false || $posArr < $posObj)) ? $posArr : $posObj;
        $chunk = substr($text, $start);

        $len = strlen($chunk);
        $inStr = false;
        $str = '';
        $stack = [];
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $ch = $chunk[$i];
            $out .= $ch;
            if ($inStr) {
                if ($ch === '\\') {
                    if ($i + 1 < $len) {
                        $out .= $chunk[++$i];
                    }

continue;
                }
                if ($ch === $str) {
                    $inStr = false;
                    $str = '';
                }

                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $inStr = true;
                $str = $ch;

                continue;
            }
            if ($ch === '[' || $ch === '{') {
                $stack[] = $ch;

                continue;
            }
            if ($ch === ']' || $ch === '}') {
                if (! empty($stack)) {
                    array_pop($stack);
                }

                continue;
            }
        }
        $out = rtrim($out);
        if (substr($out, -1) === ',') {
            $out = rtrim(substr($out, 0, -1));
        }
        while (! empty($stack)) {
            $open = array_pop($stack);
            $out .= ($open === '[') ? ']' : '}';
        }

        return $out;
    }

    private static function stripTrailingCommas(string $text): string
    {
        $out = '';
        $len = strlen($text);
        $inStr = false;
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];
            if ($inStr) {
                $out .= $ch;
                if ($ch === '\\') {
                    if ($i + 1 < $len) {
                        $out .= $text[++$i];
                    }

continue;
                }
                if ($ch === $str) {
                    $inStr = false;
                    $str = '';
                }

                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $inStr = true;
                $str = $ch;
                $out .= $ch;

                continue;
            }
            if ($ch === ',') {
                $j = $i + 1;
                while ($j < $len && ctype_space($text[$j])) {
                    $j++;
                }
                if ($j < $len && ($text[$j] === '}' || $text[$j] === ']')) {
                    continue;
                }
            }
            $out .= $ch;
        }

        return $out;
    }

    private static function stripJsonComments(string $text): string
    {
        $out = '';
        $len = strlen($text);
        $inStr = false;
        $strChar = '';
        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];

            if ($inStr) {
                $out .= $ch;
                if ($ch === '\\') {
                    if ($i + 1 < $len) {
                        $out .= $text[++$i];
                    }

                    continue;
                }
                if ($ch === $strChar) {
                    $inStr = false;
                    $strChar = '';
                }

                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $inStr = true;
                $strChar = $ch;
                $out .= $ch;

                continue;
            }

            if ($ch === '/' && $i + 1 < $len && $text[$i + 1] === '/') {
                $i += 2;
                while ($i < $len && $text[$i] !== "\n") {
                    $i++;
                }
                if ($i < $len) {
                    $out .= "\n";
                }

                continue;
            }

            if ($ch === '/' && $i + 1 < $len && $text[$i + 1] === '*') {
                $i += 2;
                while ($i + 1 < $len && ! ($text[$i] === '*' && $text[$i + 1] === '/')) {
                    $i++;
                }
                $i++;

                continue;
            }

            $out .= $ch;
        }

        return $out;
    }
}
