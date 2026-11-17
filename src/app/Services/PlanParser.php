<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use InvalidArgumentException;
use Smalot\PdfParser\Parser;
use ZipArchive;

/**
 * Reads an IR plan (PDF, DOCX or Markdown) and splits it into numbered
 * sections (spec section 5.1). The moderator checks and corrects the result.
 */
class PlanParser
{
    /**
     * @return list<array{number: ?string, title: string, body: string}>
     */
    public function parse(string $path, string $extension): array
    {
        return $this->split($this->text($path, $extension));
    }

    public function text(string $path, string $extension): string
    {
        $text = match (strtolower($extension)) {
            'md' => (string) file_get_contents($path),
            'docx' => $this->docxText($path),
            'pdf' => (new Parser)->parseFile($path)->getText(),
            default => throw new InvalidArgumentException('Unsupported file type: '.$extension),
        };

        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    /**
     * Split the text at its section headings.
     *
     * A line such as "5.2 Severity table" is only a candidate: lists and
     * tables also start with numbers. The headings are the longest chain of
     * candidates whose numbers follow in order (1, 2, 2.1, 2.2, 3 ...). A
     * candidate with no text after it (for example a table of contents line)
     * counts for little. Markdown headings ("## Scope") are always headings.
     *
     * @return list<array{number: ?string, title: string, body: string}>
     */
    public function split(string $text): array
    {
        $lines = explode("\n", $text);
        $candidates = $this->candidates($lines);
        $headings = $this->headingLines($lines, $candidates);

        $sections = [];
        $current = ['number' => null, 'title' => 'Front matter', 'body' => ''];

        foreach ($lines as $i => $line) {
            if (isset($headings[$i])) {
                $sections[] = $current;
                $current = $headings[$i] + ['body' => ''];

                continue;
            }

            $current['body'] .= $line."\n";
        }

        $sections[] = $current;

        $sections = array_map(fn (array $section) => [
            'number' => $section['number'],
            'title' => $section['title'],
            'body' => trim($section['body']),
        ], $sections);

        return array_values(array_filter(
            $sections,
            fn (array $section) => $section['number'] !== null || $section['body'] !== '',
        ));
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{line: int, key: ?list<int>, number: ?string, title: string, markdown: bool}>
     */
    private function candidates(array $lines): array
    {
        $candidates = [];

        foreach ($lines as $i => $line) {
            $title = trim($line);
            $markdown = preg_match('/^#{1,6}\s+(.+)$/', $title, $match) === 1;

            if ($markdown) {
                $title = trim($match[1]);
            }

            // A table of contents line ends with dot leaders and a page number.
            if (preg_match('/\.{3,}\s*\d+$/', $title) === 1) {
                continue;
            }

            if (preg_match('/^(\d+(?:\.\d+)*)\.?\s+(\p{Lu}.{0,150})$/u', $title, $match) === 1) {
                $key = array_map('intval', explode('.', $match[1]));

                // Section numbers are small. This skips street numbers and years.
                if (max($key) <= 99) {
                    $candidates[] = ['line' => $i, 'key' => $key, 'number' => $match[1], 'title' => rtrim($match[2]), 'markdown' => $markdown];

                    continue;
                }
            }

            if ($markdown) {
                $candidates[] = ['line' => $i, 'key' => null, 'number' => null, 'title' => $title, 'markdown' => true];
            }
        }

        return $candidates;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<array{line: int, key: ?list<int>, number: ?string, title: string, markdown: bool}>  $candidates
     * @return array<int, array{number: ?string, title: string}>
     */
    private function headingLines(array $lines, array $candidates): array
    {
        // Weight 1 when text follows before the next candidate, else 0.1.
        $weights = [];
        foreach ($candidates as $k => $candidate) {
            $end = $candidates[$k + 1]['line'] ?? count($lines);
            $weights[$k] = 0.1;
            for ($j = $candidate['line'] + 1; $j < $end; $j++) {
                if (trim($lines[$j]) !== '') {
                    $weights[$k] = 1.0;
                    break;
                }
            }
        }

        $numbered = array_keys(array_filter($candidates, fn (array $candidate) => $candidate['key'] !== null));

        // The longest weighted chain of numbers in order (dynamic programming).
        $best = [];
        $previous = [];
        foreach ($numbered as $a) {
            $best[$a] = $this->follows($candidates[$a]['key'], null) ? $weights[$a] : null;
            $previous[$a] = null;

            foreach ($numbered as $b) {
                if ($b >= $a) {
                    break;
                }

                if ($best[$b] !== null
                    && $this->follows($candidates[$a]['key'], $candidates[$b]['key'])
                    && ($best[$a] === null || $best[$b] + $weights[$a] > $best[$a])) {
                    $best[$a] = $best[$b] + $weights[$a];
                    $previous[$a] = $b;
                }
            }
        }

        $chain = [];
        $end = null;
        foreach ($best as $k => $score) {
            if ($score !== null && ($end === null || $score > $best[$end])) {
                $end = $k;
            }
        }
        while ($end !== null) {
            $chain[$end] = true;
            $end = $previous[$end];
        }

        $headings = [];
        foreach ($candidates as $k => $candidate) {
            if (isset($chain[$k]) || $candidate['markdown']) {
                $headings[$candidate['line']] = ['number' => $candidate['number'], 'title' => $candidate['title']];
            }
        }

        return $headings;
    }

    /**
     * True when the number $next can come after $last: the next number at some
     * level (3.1 -> 3.2 or 4) or the first sub-number (3.1 -> 3.1.1).
     *
     * @param  list<int>  $next
     * @param  ?list<int>  $last
     */
    private function follows(array $next, ?array $last): bool
    {
        if ($last === null) {
            return $next[0] === 1 && array_filter(array_slice($next, 1), fn (int $part) => $part !== 1) === [];
        }

        if ($next === [...$last, 1]) {
            return true;
        }

        foreach ($last as $i => $part) {
            if ($next === [...array_slice($last, 0, $i), $part + 1]) {
                return true;
            }
        }

        return false;
    }

    private function docxText(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('The DOCX file cannot be opened.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new InvalidArgumentException('The DOCX file has no document text.');
        }

        // One line for each paragraph.
        $xml = str_replace(['</w:p>', '<w:tab/>', '<w:br/>'], ["\n", "\t", "\n"], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
