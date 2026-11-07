<?php

namespace App\Support;

/**
 * Turns the plain text of a case study into safe, structured HTML.
 *
 * The admin form is a plain textarea, so the text is whatever was typed. Understood without any markup lessons:
 *  - "Key: value" lines at the very top become fact chips (Stack, Role, Year...)
 *  - "# Title" / "## Title" lines, a short line ending in ":" and a short stand-alone line without a full stop become headings
 *  - lines starting with "-", "*", "•" or "1." become lists
 *  - blank lines separate paragraphs; **bold**, `code` and bare links work inside text
 * Everything is escaped first, so the result is safe to print unescaped.
 */
class CaseStudy
{
    /** @return array{facts: list<array{0:string,1:string}>, html: string} */
    public static function parse(?string $text): array
    {
        $lines = preg_split('/\R/u', trim((string) $text)) ?: [];
        $lines = array_map(fn ($l) => rtrim($l), $lines);
        $count = count($lines);

        $facts = [];
        $i = 0;

        // Leading "Key: value" lines.
        while ($i < $count && preg_match('/^\s*([\p{L}][\p{L}\p{N} &\/+.\-]{0,24}):\s+(\S.*)$/u', $lines[$i], $m) && self::looksLikeFact($m[1], $m[2])) {
            $facts[] = [trim($m[1]), trim($m[2])];
            $i++;
        }

        $html = '';
        $para = [];
        $list = null;   // 'ul' | 'ol'

        $flushPara = function () use (&$para, &$html) {
            if ($para) {
                $html .= '<p>'.self::inline(implode(' ', $para)).'</p>';
                $para = [];
            }
        };
        $flushList = function () use (&$list, &$html) {
            if ($list !== null) {
                $html .= '</'.$list.'>';
                $list = null;
            }
        };

        for (; $i < $count; $i++) {
            $line = trim($lines[$i]);

            if ($line === '') {
                $flushPara();
                $flushList();

                continue;
            }

            if (preg_match('/^(#{1,4})\s+(.+)$/u', $line, $m)) {
                $flushPara();
                $flushList();
                $tag = strlen($m[1]) <= 2 ? 'h2' : 'h3';
                $html .= "<{$tag}>".self::inline(rtrim($m[2], ': ')).'</'.$tag.'>';

                continue;
            }

            if (preg_match('/^(?:[-*•–]|\d{1,2}[.)])\s+(.+)$/u', $line, $m)) {
                $flushPara();
                $type = preg_match('/^\d/', $line) ? 'ol' : 'ul';

                if ($list !== $type) {
                    $flushList();
                    $html .= '<'.$type.'>';
                    $list = $type;
                }

                $html .= '<li>'.self::inline($m[1]).'</li>';

                continue;
            }

            $prevBlank = $i === 0 || trim($lines[$i - 1]) === '' || $list !== null || $html === '';

            if (mb_strlen($line) <= 70 && $prevBlank && ! $para && self::isHeadingLike($line)) {
                $flushList();
                $html .= '<h2>'.self::inline(rtrim($line, ': ')).'</h2>';

                continue;
            }

            $flushList();
            $para[] = $line;
        }

        $flushPara();
        $flushList();

        return ['facts' => $facts, 'html' => $html];
    }

    /** "Stack: Laravel" is a fact. "Note: this took a while, and then some." is a sentence. */
    private static function looksLikeFact(string $key, string $value): bool
    {
        return mb_strlen($value) <= 120 && ! preg_match('/[.!?]$/u', $value);
    }

    private static function isHeadingLike(string $line): bool
    {
        if (str_ends_with($line, ':')) {
            return true;
        }

        return ! preg_match('/[.!?;,]$/u', $line);
    }

    private static function inline(string $text): string
    {
        $out = e($text);

        $out = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $out) ?? $out;
        $out = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $out) ?? $out;
        $out = preg_replace(
            '~(?<![">=])\b(https?://[^\s<]+[^\s<.,;:!?)])~u',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
            $out
        ) ?? $out;

        return $out;
    }
}
