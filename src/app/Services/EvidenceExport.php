<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use App\Models\LogEntry;
use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

/**
 * The evidence package (spec section 8.5). When a report becomes final, the files
 * are written one time. The export is a ZIP of these files and a manifest with
 * the SHA-256 hash of each file.
 */
class EvidenceExport
{
    public const MAPPING = 'mappings/nist-800-171-r2-3.6.yaml';

    /**
     * Write the evidence files of a final report. Returns the SHA-256 of the AAR PDF.
     *
     * @param  array<string, mixed>  $aar  from AarBuilder
     */
    public function store(Report $report, array $aar): string
    {
        $exercise = $report->exercise;
        $logs = $exercise->logEntries()->with('recorder')->whereIn('type', ['communication', 'action', 'validation'])->reorder('id')->get();
        $mapping = Yaml::parseFile(resource_path(self::MAPPING));

        $files = [
            'aar.pdf' => Pdf::loadView('pdf.aar', ['aar' => $aar])->output(),
            'logs.pdf' => Pdf::loadView('pdf.logs', ['aar' => $aar, 'logs' => $logs])->output(),
            'scenario.yaml' => ScenarioChecker::dump($exercise->scenario),
            'mapping.md' => $this->mapping($aar, $mapping),
        ];
        foreach (['communication', 'action', 'validation'] as $type) {
            $files[$type.'.csv'] = $this->csv($type, $logs->where('type', $type)->all());
        }
        foreach ($files as $name => $contents) {
            Storage::put($report->folder().'/'.$name, $contents);
        }

        return hash('sha256', $files['aar.pdf']);
    }

    /**
     * The ZIP file with the evidence files and the manifest. Returns a temporary file path.
     */
    public function zip(Report $report): string
    {
        $manifest = ['exercise' => $report->exercise->title, 'report_version' => $report->version, 'tlp' => $report->tlp,
            'approved_at' => $report->approved_at?->toIso8601String(), 'files' => []];
        $path = (string) tempnam(sys_get_temp_dir(), 'evidence');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach (Storage::files($report->folder()) as $file) {
            $contents = (string) Storage::get($file);
            $zip->addFromString(basename($file), $contents);
            $manifest['files'][basename($file)] = hash('sha256', $contents);
        }
        ksort($manifest['files']);
        $zip->addFromString('manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('SHA256SUMS', implode('', array_map(fn ($name, $hash) => "$hash  $name\n", array_keys($manifest['files']), $manifest['files'])));
        $zip->close();

        return $path;
    }

    /**
     * The framework mapping as Markdown: each control, its requirement, how the exercise
     * supports it and the files of this package that are evidence.
     *
     * @param  array<string, mixed>  $aar
     * @param  array<string, mixed>  $mapping
     */
    private function mapping(array $aar, array $mapping): string
    {
        $lines = ['# '.$mapping['title'], '', $aar['tlp'].' · '.$aar['title'].' · AAR version '.$aar['version'], ''];
        foreach ($mapping['controls'] as $control) {
            $lines[] = '## '.$control['id'];
            $lines[] = '';
            $lines[] = 'Requirement: '.$control['requirement'];
            $lines[] = '';
            $lines[] = 'How this exercise supports it: '.$control['how'];
            $lines[] = '';
            $lines[] = 'Evidence in this package: '.implode(', ', $control['evidence']);
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<LogEntry>  $entries
     */
    private function csv(string $type, array $entries): string
    {
        $fields = array_keys(LogEntry::TYPES[$type]['fields']);
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['real_time', 'simulated_day', 'simulated_time', 'recorded_by', ...$fields], escape: '');
        foreach ($entries as $entry) {
            $row = [$entry->created_at->toIso8601String(), $entry->sim_day, $entry->sim_time, $entry->recorder?->name ?? ''];
            foreach ($fields as $field) {
                $row[] = $entry->fields[$field] ?? '';
            }
            // A cell that starts with = + - @ could run as a formula in a spreadsheet.
            fputcsv($out, array_map(fn ($cell) => preg_match('/^[=+\-@\t\r]/', (string) $cell) ? "'".$cell : $cell, $row), escape: '');
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }
}
