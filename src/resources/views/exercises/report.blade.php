{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- After-action report: edit the draft, make it final, export the evidence (spec sections 4.5, 8.4 and 8.5). --}}
@use('App\Models\Report')
<x-layouts.app :title="'Report: '.$exercise->title">
    <p class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        @include('exercises._status', ['status' => $exercise->status])
        <a href="{{ route('exercises.review', $exercise) }}" class="underline underline-offset-4">Review</a>
        <a href="{{ route('exercises.control', $exercise) }}" class="underline underline-offset-4">Control</a>
    </p>
    @if (session('status'))
        <p role="status" class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm dark:border-green-700 dark:bg-green-950">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p role="alert" class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm dark:border-red-700 dark:bg-red-950">{{ $errors->first() }}</p>
    @endif

    @if ($reports->where('status', 'final')->isNotEmpty())
        <section class="mb-6 text-sm">
            <h2 class="mb-2 text-lg font-semibold">Final versions</h2>
            <ul class="space-y-1">
                @foreach ($reports->where('status', 'final') as $final)
                    <li>
                        Version {{ $final->version }} ({{ $final->tlp }}), approved by {{ $final->approver?->name }} on {{ $final->approved_at?->toDateString() }} ·
                        <a href="{{ route('exercises.report.pdf', [$exercise, $final]) }}" class="underline underline-offset-4">AAR (PDF)</a> ·
                        <a href="{{ route('exercises.report.export', [$exercise, $final]) }}" class="underline underline-offset-4">Evidence package (ZIP)</a>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400">AAR SHA-256 {{ $final->file_hash }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($report->isFinal())
        <form method="POST" action="{{ route('exercises.report.new', $exercise) }}" class="mb-6">
            @csrf
            <p class="mb-2 text-sm">Version {{ $report->version }} is final and does not change. To change the report, start a new version.</p>
            <x-button>Start version {{ $report->version + 1 }}</x-button>
        </form>
    @else
        <form method="POST" action="{{ route('exercises.report.save', $exercise) }}" class="mb-6 rounded border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900">
            @csrf
            <h2 class="mb-2 text-lg font-semibold">Draft version {{ $report->version }}</h2>
            <p class="mb-3 text-zinc-600 dark:text-zinc-400">Write the summary and the phase review. The other parts come from the exercise records (Review screen).</p>
            <label for="tlp" class="mb-1 block font-medium">Traffic Light Protocol label</label>
            <select id="tlp" name="tlp" class="mb-3 rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                @foreach (Report::TLP as $tlp)
                    <option value="{{ $tlp }}" @selected($report->tlp === $tlp)>{{ $tlp }}</option>
                @endforeach
            </select>
            <label for="summary" class="mb-1 block font-medium">Scenario summary</label>
            <textarea id="summary" name="summary" rows="4" class="mb-3 w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">{{ old('summary', $aar['summary']) }}</textarea>
            @foreach ($aar['phases'] as $phase)
                @php($key = strtolower($phase['phase']))
                <label for="phase-{{ $key }}" class="mb-1 block font-medium">Phase review: {{ $phase['phase'] }} ({{ $phase['earned'] }} of {{ $phase['possible'] }} points)</label>
                <textarea id="phase-{{ $key }}" name="phases[{{ $key }}]" rows="3" class="mb-3 w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">{{ old('phases.'.$key, $phase['text']) }}</textarea>
            @endforeach
            <x-button>Save the draft</x-button>
        </form>

        <form method="POST" action="{{ route('exercises.report.final', $exercise) }}" class="mb-6 text-sm">
            @csrf
            <p class="mb-2">Final: you approve the report with your name and today's date. The report and the evidence files of this version do not change after this.</p>
            <button type="submit" class="rounded bg-zinc-900 px-4 py-2 font-medium text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900" @disabled($exercise->status !== 'ended')>Make version {{ $report->version }} final</button>
            @if ($exercise->status !== 'ended')
                <span class="ml-2 text-zinc-600 dark:text-zinc-400">End the session first.</span>
            @endif
        </form>
    @endif

    <article class="aar rounded border border-zinc-200 bg-white p-6 text-sm text-zinc-900 [&_h1]:mb-1 [&_h1]:text-xl [&_h1]:font-bold [&_h2]:mt-6 [&_h2]:mb-2 [&_h2]:border-b [&_h2]:text-base [&_h2]:font-semibold [&_h3]:mt-3 [&_h3]:font-medium [&_li]:ml-5 [&_li]:list-disc [&_table]:my-2 [&_table]:w-full [&_td]:border [&_td]:px-2 [&_td]:py-1 [&_th]:border [&_th]:bg-zinc-50 [&_th]:px-2 [&_th]:py-1 [&_th]:text-left [&_.text]:whitespace-pre-line">
        @include('reports._aar')
    </article>
</x-layouts.app>
