{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Moderator console (spec section 7.1): all live data on one screen. The part in #live reloads on each change. --}}
<x-layouts.app :title="'Control: '.$exercise->title">
    <div id="live" data-live-channel="exercise.{{ $exercise->id }}">
        <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
            @include('exercises._status', ['status' => $exercise->status])
            @include('exercises._clock')
            <a href="{{ route('exercises.live', $exercise) }}" class="underline underline-offset-4">Live screen</a>
            <a href="{{ route('exercises.display', $exercise) }}" target="_blank" class="underline underline-offset-4">Shared display</a>
            <a href="{{ route('exercises.review', $exercise) }}" class="underline underline-offset-4">Review</a>
            <a href="{{ route('exercises.setup', $exercise) }}" class="underline underline-offset-4">Participants ({{ $participants->count() }})</a>
            <span>Roles confirmed: <strong>{{ $participants->whereNotNull('confirmed_at')->count() }} of {{ $participants->count() }}</strong>@if ($waiting = $participants->whereNull('confirmed_at')->map(fn ($p) => $p->user->name)->implode(', ')) (waiting: {{ $waiting }})@endif</span>
        </div>

        @if (session('status'))
            <p role="status" class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm dark:border-green-700 dark:bg-green-950">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p role="alert" class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm dark:border-red-700 dark:bg-red-950">{{ $errors->first() }}</p>
        @endif
        @include('exercises._stopped')

        <section aria-label="Session controls" class="mb-6 flex flex-wrap items-end gap-2">
            @foreach (['start' => ['Start the session', ['prepared']], 'pause' => ['Pause', ['running']], 'resume' => ['Resume', ['paused', 'stopped']], 'end' => ['End the session', ['running', 'paused', 'stopped']]] as $action => [$label, $from])
                @if (in_array($exercise->status, $from, true))
                    <form method="POST" action="{{ route('exercises.session', [$exercise, $action]) }}">
                        @csrf
                        <x-button>{{ $label }}</x-button>
                    </form>
                @endif
            @endforeach
            @if (in_array($exercise->status, ['running', 'paused'], true))
                <form method="POST" action="{{ route('exercises.session', [$exercise, 'stop']) }}">
                    @csrf
                    <button type="submit" class="rounded bg-red-700 px-4 py-2 text-sm font-bold text-white hover:bg-red-600">STOP (real-world event)</button>
                </form>
            @endif
            @if ($exercise->status !== 'prepared' && $exercise->status !== 'ended')
                <form method="POST" action="{{ route('exercises.clock', $exercise) }}" class="flex items-end gap-2">
                    @csrf
                    <div>
                        <label for="sim_day" class="block text-xs">Day</label>
                        <input id="sim_day" name="sim_day" type="number" min="1" max="30" value="{{ $exercise->sim_day }}" class="w-16 rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                    </div>
                    <div>
                        <label for="sim_time" class="block text-xs">Simulated time</label>
                        <input id="sim_time" name="sim_time" type="time" value="{{ $exercise->sim_time }}" class="rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                    </div>
                    <button type="submit" class="rounded border border-zinc-300 px-3 py-1 text-sm dark:border-zinc-700">Move the clock forward</button>
                </form>
            @endif
        </section>

        @if ($exercise->status === 'running')
            <details id="write-inject" class="mb-6 rounded border border-zinc-200 bg-white text-sm dark:border-zinc-800 dark:bg-zinc-900">
                <summary class="cursor-pointer px-3 py-2 font-medium">Write a new inject (for an unexpected response)</summary>
                <form method="POST" action="{{ route('exercises.write', $exercise) }}" class="border-t border-zinc-200 p-3 dark:border-zinc-800">
                    @csrf
                    <label for="write-title" class="mb-1 block text-xs font-medium">Title</label>
                    <input id="write-title" name="title" required maxlength="255" data-keep class="mb-2 w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                    <label for="write-body" class="mb-1 block text-xs font-medium">Text that participants see</label>
                    <textarea id="write-body" name="body" rows="3" required data-keep class="mb-2 w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950"></textarea>
                    <div class="mb-2 grid gap-2 sm:grid-cols-2">
                        <div>
                            <label for="write-audience" class="mb-1 block text-xs font-medium">Only for (optional: plan roles or exercise roles, with commas)</label>
                            <input id="write-audience" name="audience" maxlength="255" data-keep placeholder="CISO, Legal" class="w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                        </div>
                        <div>
                            <label for="write-timer" class="mb-1 block text-xs font-medium">Timer in minutes (optional)</label>
                            <input id="write-timer" name="timer_minutes" type="number" min="1" max="240" data-keep class="w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                        </div>
                    </div>
                    <x-button>Release the new inject</x-button>
                </form>
            </details>
        @endif

        @if ($coverage)
            <section aria-labelledby="coverage" class="mb-6 rounded border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                <h2 id="coverage" class="mb-1 font-semibold">Plan coverage: {{ $released->first()->inject_key }} {{ $released->first()->title }}</h2>
                <p>Used: <strong>{{ count($coverage['used']) }}</strong>@if ($coverage['used']) ({{ implode(', ', $coverage['used']) }})@endif
                    · Missed: <strong>{{ count($coverage['missed']) }}</strong>@if ($coverage['missed']) ({{ implode(', ', $coverage['missed']) }})@endif
                    · No plan coverage: <strong>{{ $coverage['gaps'] }}</strong> of {{ $coverage['responses'] }} responses</p>
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <section aria-labelledby="timeline">
                <h2 id="timeline" class="mb-2 text-lg font-semibold">Timeline</h2>
                <ol class="space-y-2 text-sm">
                    @foreach ($injects as $inject)
                        @php
                            $isReleased = in_array($inject['id'], $releasedKeys, true);
                            $isNext = ! $isReleased && in_array($inject['id'], $suggested, true);
                        @endphp
                        <li class="rounded border p-3 {{ $isNext ? 'border-blue-500' : 'border-zinc-200 dark:border-zinc-800' }} {{ $isReleased ? 'opacity-70' : '' }}">
                            <p>
                                <span class="font-mono text-xs">{{ $inject['id'] }}</span> ·
                                {{ isset($inject['day']) ? 'day '.$inject['day'].' ' : '' }}{{ $inject['at'] }} ·
                                <strong>{{ $isReleased ? 'Released' : ($isNext ? 'Suggested next' : 'Not released') }}</strong>
                            </p>
                            <p class="font-medium">{{ $inject['title'] }}</p>
                            @if (! empty($inject['expected_plan']))
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">Expected plan: {{ implode(', ', $inject['expected_plan']) }}</p>
                            @endif
                            @if (! empty($inject['audience']) && array_intersect(['all', 'everyone'], array_map('strtolower', $inject['audience'])) === [])
                                <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">Only for: {{ implode(', ', $inject['audience']) }}</p>
                            @endif
                            @isset($inject['timer'])
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">Timer: {{ $inject['timer'] }} min</p>
                            @endisset
                            @if (! empty($inject['branches']))
                                <ul class="text-xs text-zinc-600 dark:text-zinc-400">
                                    @foreach ($inject['branches'] as $branch)
                                        <li>If {{ $branch['when'] }} → {{ $branch['goto'] }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if (! empty($exercise->scenario['rubric'][$inject['id']]))
                                <details class="text-xs">
                                    <summary class="cursor-pointer">Rubric (moderator only)</summary>
                                    <ul class="list-disc pl-5">
                                        @foreach ($exercise->scenario['rubric'][$inject['id']] as $item)
                                            <li>{{ $item['item'] }} ({{ $item['points'] }})</li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                            @if (! $isReleased && $exercise->status === 'running')
                                <form method="POST" action="{{ route('exercises.release', $exercise) }}" class="mt-2">
                                    @csrf
                                    <input type="hidden" name="inject" value="{{ $inject['id'] }}">
                                    <button type="submit" class="rounded border border-zinc-300 px-2 py-1 text-xs hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">Release {{ $inject['id'] }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>

            <section aria-labelledby="feed">
                <h2 id="feed" class="mb-2 text-lg font-semibold">Responses</h2>
                @forelse ($released as $release)
                    <div class="mb-4">
                        <p class="text-sm font-medium">{{ $release->inject_key }}: {{ $release->title }} <span class="text-xs text-zinc-600 dark:text-zinc-400">(released at simulated {{ $release->sim_time }}@unless ($release->isForAll()); only for {{ implode(', ', $release->audience) }}@endunless)</span></p>
                        @include('exercises._timer')
                        @forelse ($release->responses as $response)
                            <div class="mt-2 rounded border border-zinc-200 bg-white p-2 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $response->user?->name ?? 'Deleted user' }}@if ($response->plan_role) ({{ $response->plan_role }})@endif · simulated {{ $response->sim_time }} · real {{ $response->created_at->format('H:i') }}</p>
                                <p class="whitespace-pre-line">{{ $response->body }}</p>
                                <p class="text-xs">
                                    @if ($response->citations->isNotEmpty())
                                        Cites: {{ $response->citations->map(fn ($section) => trim($section->number.' '.$section->title))->implode('; ') }}
                                    @endif
                                    @if ($response->no_coverage)
                                        <strong>No plan coverage</strong>
                                    @endif
                                </p>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">No responses yet.</p>
                        @endforelse
                    </div>
                @empty
                    <p class="text-sm">No inject is released yet.</p>
                @endforelse
            </section>
        </div>

        <section aria-labelledby="logs" class="mt-8 grid gap-6 lg:grid-cols-2">
            <div>
                <h2 id="logs" class="mb-2 text-lg font-semibold">Notes and logs</h2>
                @if (in_array($exercise->status, \App\Models\Exercise::LIVE, true))
                    @include('exercises._log-forms', ['types' => ['note', 'parking', 'communication', 'action', 'validation']])
                @endif
            </div>
            <div class="lg:pt-9">
                @include('exercises._log-feed')
            </div>
        </section>
    </div>
</x-layouts.app>
