{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Review screen (spec sections 4.4 and 8.3, backlog B4, B5, B11): the debrief. --}}
@use('App\Models\Finding')
@use('App\Models\ObjectiveRating')
@use('App\Models\RubricMark')
<x-layouts.app :title="'Review: '.$exercise->title">
    <p class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        @include('exercises._status', ['status' => $exercise->status])
        <a href="{{ route('exercises.control', $exercise) }}" class="underline underline-offset-4">Control</a>
        <a href="{{ route('exercises.setup', $exercise) }}" class="underline underline-offset-4">Participants</a>
        <a href="{{ route('exercises.report', $exercise) }}" class="underline underline-offset-4">After-action report</a>
    </p>
    @if (session('status'))
        <p role="status" class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm dark:border-green-700 dark:bg-green-950">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p role="alert" class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm dark:border-red-700 dark:bg-red-950">{{ $errors->first() }}</p>
    @endif

    <section id="summary" class="mb-8 rounded border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900">
        <h2 class="mb-2 text-lg font-semibold">Score</h2>
        <p class="mb-2 text-2xl font-bold">{{ $scores['percent'] === null ? 'No rubric' : $scores['percent'].'%' }} <span class="text-sm font-normal">({{ $scores['earned'] }} of {{ $scores['possible'] }} points; items without a mark count as not met)</span></p>
        <ul class="list-disc pl-5">
            @foreach ($scores['phases'] as $phase => $score)
                <li>{{ ucfirst($phase) }}: {{ $score['earned'] }} of {{ $score['possible'] }} points</li>
            @endforeach
        </ul>
    </section>

    @if (! empty($exercise->scenario['objectives']))
        <section id="objectives" class="mb-8">
            <h2 class="mb-2 text-lg font-semibold">Objectives (HSEEP rating)</h2>
            @foreach ($exercise->scenario['objectives'] as $n => $objective)
                @php($rating = $ratings->get($n))
                <form method="POST" action="{{ route('exercises.review.rate', [$exercise, $n]) }}" class="mb-3 rounded border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                    @csrf
                    <p class="mb-2 font-medium">{{ $n + 1 }}. {{ $objective }}</p>
                    <div class="grid gap-2 sm:grid-cols-[16rem_1fr_auto] sm:items-end">
                        <div>
                            <label for="rating-{{ $n }}" class="mb-1 block text-xs">Rating</label>
                            <select id="rating-{{ $n }}" name="rating" required class="w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                                <option value="">—</option>
                                @foreach (ObjectiveRating::RATINGS as $value => $label)
                                    <option value="{{ $value }}" @selected($rating?->rating === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="justification-{{ $n }}" class="mb-1 block text-xs">Justification (required)</label>
                            <input id="justification-{{ $n }}" name="justification" required maxlength="2000" value="{{ $rating?->justification }}" class="w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                        </div>
                        <button type="submit" class="rounded border border-zinc-300 px-3 py-1 dark:border-zinc-700">Save</button>
                    </div>
                </form>
            @endforeach
        </section>
    @endif

    <section id="scores" class="mb-8">
        <h2 class="mb-2 text-lg font-semibold">Rubric</h2>
        <form method="POST" action="{{ route('exercises.review.marks', $exercise) }}">
            @csrf
            @foreach ($exercise->scenario['injects'] as $inject)
                @continue(empty($exercise->scenario['rubric'][$inject['id']]))
                <fieldset class="mb-3 rounded border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <legend class="px-1 font-medium">{{ $inject['id'] }}: {{ $inject['title'] }}@isset($inject['phase']) ({{ $inject['phase'] }})@endisset</legend>
                    @foreach ($exercise->scenario['rubric'][$inject['id']] as $i => $item)
                        @php($mark = $marks->get($inject['id'].'#'.$i))
                        <div class="mb-2 grid gap-2 sm:grid-cols-[1fr_9rem_5rem_1fr] sm:items-end">
                            <p>{{ $item['item'] }} <span class="text-xs text-zinc-600 dark:text-zinc-400">({{ $item['points'] }} points)</span></p>
                            <div>
                                <label for="mark-{{ $inject['id'] }}-{{ $i }}" class="block text-xs">Result</label>
                                <select id="mark-{{ $inject['id'] }}-{{ $i }}" name="marks[{{ $inject['id'] }}][{{ $i }}][result]" class="w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">—</option>
                                    @foreach (RubricMark::RESULTS as $value => $label)
                                        <option value="{{ $value }}" @selected($mark?->result === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="points-{{ $inject['id'] }}-{{ $i }}" class="block text-xs">Points</label>
                                <input id="points-{{ $inject['id'] }}-{{ $i }}" name="marks[{{ $inject['id'] }}][{{ $i }}][points]" type="number" min="0" max="{{ $item['points'] }}" value="{{ $mark?->points }}" placeholder="auto" class="w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                            </div>
                            <div>
                                <label for="reason-{{ $inject['id'] }}-{{ $i }}" class="block text-xs">Reason (if you change the points)</label>
                                <input id="reason-{{ $inject['id'] }}-{{ $i }}" name="marks[{{ $inject['id'] }}][{{ $i }}][reason]" maxlength="1000" value="{{ $mark?->reason }}" class="w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                            </div>
                        </div>
                    @endforeach
                </fieldset>
            @endforeach
            <x-button>Save the scores</x-button>
        </form>
    </section>

    <section id="coverage" class="mb-8 text-sm">
        <h2 class="mb-2 text-lg font-semibold">Plan coverage</h2>
        @foreach (['used' => 'Used (expected and cited)', 'missed' => 'Missed (expected, not cited)', 'extra' => 'Extra (cited, not expected)'] as $key => $label)
            <p><strong>{{ $label }}:</strong> {{ collect($coverage[$key])->map(fn ($section) => trim($section->number.' '.$section->title))->implode('; ') ?: 'none' }}</p>
        @endforeach
        <p><strong>Plan gaps (responses without plan coverage):</strong> {{ $coverage['gaps'] }}</p>
    </section>

    <section id="parking" class="mb-8 text-sm">
        <h2 class="mb-2 text-lg font-semibold">Parking lot</h2>
        <ul class="list-disc pl-5">
            @forelse ($parking as $entry)
                <li>{{ $entry->fields['topic'] ?? '' }}</li>
            @empty
                <li>No topics.</li>
            @endforelse
        </ul>
    </section>

    <section id="findings" class="mb-8">
        <h2 class="mb-2 text-lg font-semibold">Findings and corrective actions</h2>
        @foreach ($exercise->findings as $finding)
            <div class="mb-2 rounded border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p><strong>{{ Finding::TYPES[$finding->type] }}:</strong> {{ $finding->text }}</p>
                @if ($finding->action)
                    <p class="mt-1">Action: {{ $finding->action }}@if ($finding->control) (control or process: {{ $finding->control }})@endif · Owner: {{ $finding->owner }} · Due: {{ $finding->due_date?->toDateString() }}@if ($finding->documents) · Update: {{ $finding->documents }}@endif</p>
                    <p class="mt-1">Status: <strong>{{ $finding->status === 'done' ? 'Done' : 'Open' }}</strong>@if ($finding->isOverdue()) <strong class="text-red-700 dark:text-red-400">(overdue)</strong>@endif</p>
                @endif
                <div class="mt-2 flex gap-3">
                    @if ($finding->action)
                        <form method="POST" action="{{ route('exercises.findings.update', [$exercise, $finding]) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="status" value="{{ $finding->status === 'done' ? 'open' : 'done' }}">
                            <button type="submit" class="underline underline-offset-4">{{ $finding->status === 'done' ? 'Open again' : 'Mark as done' }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('exercises.findings.destroy', [$exercise, $finding]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-700 underline underline-offset-4 dark:text-red-400">Delete</button>
                    </form>
                </div>
            </div>
        @endforeach

        <details class="mt-3 rounded border border-zinc-200 bg-white text-sm dark:border-zinc-800 dark:bg-zinc-900" @if ($errors->hasAny(['type', 'text', 'owner', 'due_date'])) open @endif>
            <summary class="cursor-pointer px-3 py-2 font-medium">Add a finding</summary>
            <form method="POST" action="{{ route('exercises.findings.store', $exercise) }}" class="grid gap-2 border-t border-zinc-200 p-3 sm:grid-cols-2 dark:border-zinc-800">
                @csrf
                <div>
                    <label for="finding-type" class="mb-1 block text-xs font-medium">Type</label>
                    <select id="finding-type" name="type" class="w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                        @foreach (Finding::TYPES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="finding-text" class="mb-1 block text-xs font-medium">Finding</label>
                    <textarea id="finding-text" name="text" rows="2" required class="w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">{{ old('text') }}</textarea>
                </div>
                <p class="text-xs text-zinc-600 sm:col-span-2 dark:text-zinc-400">Corrective action (optional). An action needs an owner and a due date.</p>
                @foreach (['control' => 'Control or process (for example 3.6.1)', 'action' => 'Action', 'owner' => 'Owner', 'due_date' => 'Due date', 'documents' => 'Documents to update (for example IR plan section 5.2)'] as $name => $label)
                    <div class="{{ $name === 'action' ? 'sm:col-span-2' : '' }}">
                        <label for="finding-{{ $name }}" class="mb-1 block text-xs font-medium">{{ $label }}</label>
                        <input id="finding-{{ $name }}" name="{{ $name }}" type="{{ $name === 'due_date' ? 'date' : 'text' }}" value="{{ old($name) }}" class="w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                    </div>
                @endforeach
                <div class="sm:col-span-2"><x-button>Save the finding</x-button></div>
            </form>
        </details>
    </section>
</x-layouts.app>
