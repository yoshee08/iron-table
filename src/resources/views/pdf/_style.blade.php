{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Print style for the PDF files (dompdf). The TLP label is on every page. --}}
<style>
    @page { margin: 2cm 1.6cm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; }
    h1 { font-size: 16pt; margin: 0 0 4pt; }
    h2 { font-size: 13pt; margin: 14pt 0 4pt; border-bottom: 1px solid #999; }
    h3 { font-size: 11pt; margin: 8pt 0 2pt; }
    table { width: 100%; border-collapse: collapse; margin: 4pt 0 8pt; }
    th, td { border: 1px solid #bbb; padding: 3pt 4pt; text-align: left; vertical-align: top; }
    th { background: #eee; }
    .text { white-space: pre-line; }
    .tlp { margin: 0; }
    header { position: fixed; top: -1.4cm; right: 0; font-weight: bold; }
    footer { position: fixed; bottom: -1.4cm; left: 0; right: 0; font-size: 8pt; color: #555; }
</style>
<header>{{ $aar['tlp'] }}</header>
<footer>{{ $aar['tlp'] }} · {{ $aar['title'] }} · AAR version {{ $aar['version'] }}</footer>
