{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Participant live screen (spec section 7.2). Scribes and observers see it without the response form. --}}
<x-layouts.app :title="$exercise->title">
    <div id="live" data-live-channel="exercise.{{ $exercise->id }}">
        <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
            @include('exercises._status', ['status' => $exercise->status])
            @include('exercises._clock')
            <span class="whitespace-nowrap">Your role: <strong>{{ $participant?->plan_role ?? $participant?->role->label() ?? 'Moderator' }}</strong></span>
            @if ($exercise->ir_plan_id)
                <a href="{{ route('plans.show', $exercise->ir_plan_id) }}" target="_blank" class="underline underline-offset-4">Open the IR plan</a>
            @endif
        </div>

        @if (session('status'))
            <p role="status" class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm dark:border-green-700 dark:bg-green-950">{{ session('status') }}</p>
        @endif
        @include('exercises._stopped')

        @if ($exercise->status === 'prepared')
            <p class="mb-6">The session has not started. This page updates when the moderator starts it.</p>
        @else
            @isset($exercise->scenario['briefing'])
                <details class="mb-6 rounded border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-800 dark:bg-zinc-900" @if ($releases->isEmpty()) open @endif>
                    <summary class="cursor-pointer font-medium">Briefing</summary>
                    <p class="mt-2 whitespace-pre-line">{{ $exercise->scenario['briefing'] }}</p>
                </details>
            @endisset

            @forelse ($releases as $release)
                <article id="release-{{ $release->id }}" class="mb-6 rounded border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="text-xs text-zinc-600 dark:text-zinc-400">Simulated {{ $release->sim_day > 1 ? 'day '.$release->sim_day.' ' : '' }}{{ $release->sim_time }}@if ($loop->first) · <strong>Newest</strong>@endif</p>
                    <h2 class="mb-2 text-lg font-semibold">{{ $release->title }}</h2>
                    @if ($release->body !== '')
                        <p class="mb-3 whitespace-pre-line">{{ $release->body }}</p>
                    @endif
                    @foreach ($release->artifacts as $n => $artifact)
                        <x-artifact :artifact="$artifact" :id="'artifact-'.$release->id.'-'.$n" />
                    @endforeach
                    @if ($release->questions !== [])
                        <ul class="mb-3 list-disc pl-5 text-sm">
                            @foreach ($release->questions as $question)
                                <li>{{ $question }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @foreach ($release->responses as $response)
                        <div class="mb-2 rounded bg-zinc-50 p-2 text-sm dark:bg-zinc-800">
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">Your response · simulated {{ $response->sim_time }}</p>
                            <p class="whitespace-pre-line">{{ $response->body }}</p>
                            @if ($response->citations->isNotEmpty())
                                <p class="text-xs">Cites: {{ $response->citations->map(fn ($section) => trim($section->number.' '.$section->title))->implode('; ') }}</p>
                            @endif
                        </div>
                    @endforeach

                    @if ($canRespond)
                        <form method="POST" action="{{ route('exercises.responses.store', $exercise) }}" class="mt-3 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                            @csrf
                            <input type="hidden" name="inject_release_id" value="{{ $release->id }}">
                            <label for="body-{{ $release->id }}" class="mb-1 block text-sm font-medium">Your response (decision, action or answer)</label>
                            <textarea id="body-{{ $release->id }}" name="body" rows="3" required data-keep class="mb-2 w-full rounded border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950"></textarea>
                            @if ($sections->isNotEmpty())
                                <label for="sections-{{ $release->id }}" class="mb-1 block text-sm font-medium">Cite plan sections (hold Ctrl or Cmd to select more than one)</label>
                                <select id="sections-{{ $release->id }}" name="sections[]" multiple size="5" data-keep class="mb-2 w-full rounded border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    @foreach ($sections as $section)
                                        <option value="{{ $section->id }}">{{ trim($section->number.' '.$section->title) }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <label class="mb-2 flex items-center gap-2 text-sm">
                                <input type="hidden" name="no_coverage" value="0">
                                <input id="no_coverage-{{ $release->id }}" type="checkbox" name="no_coverage" value="1" data-keep>
                                The plan does not cover this (no plan coverage)
                            </label>
                            <x-button>Send</x-button>
                        </form>
                    @endif
                </article>
            @empty
                <p>No inject yet. This page updates when the moderator releases one.</p>
            @endforelse
        @endif
    </div>
</x-layouts.app>
