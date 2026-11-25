{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Review screen (spec section 4.1): the consistency check, then the scenario with the values of the current IR plan. --}}
@use('App\Services\ScenarioChecker')
<x-layouts.app :title="$scenario->title" :refresh="$refresh">
    <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
        <span class="font-mono">{{ $scenario->slug }}</span> · version {{ $scenario->version }}
        @if (is_scalar($filled['meta']['duration_minutes'] ?? null)) · {{ $filled['meta']['duration_minutes'] }} minutes @endif ·
        <a href="{{ route('scenarios.edit', $scenario) }}" class="underline underline-offset-4">Edit the YAML</a> ·
        <a href="{{ route('scenarios.download', $scenario) }}" class="underline underline-offset-4">Download</a> ·
        <a href="{{ route('scenarios.index') }}" class="underline underline-offset-4">All scenarios</a>
    </p>

    @if (session('status'))
        <p role="status" class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm dark:border-green-700 dark:bg-green-950">{{ session('status') }}</p>
    @endif
    @if (session('ai_error'))
        <p role="alert" class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm dark:border-red-700 dark:bg-red-950">{{ session('ai_error') }}</p>
    @endif
    @can('use-ai')
        <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">The AI buttons write one part of an inject. They save the YAML again, so comments in the YAML are removed.</p>
    @endcan

    <section aria-labelledby="check" class="mb-8">
        <h2 id="check" class="mb-2 text-lg font-semibold">Consistency check</h2>
        <p class="mb-2 text-sm">
            @if ($plan)
                Values and plan concepts come from IR plan version {{ $plan->version }}
                (<a href="{{ route('plans.profile.edit', $plan) }}" class="underline underline-offset-4">organization profile</a>).
            @else
                There is no IR plan. <a href="{{ route('plans.create') }}" class="underline underline-offset-4">Upload the plan</a> first.
            @endif
        </p>
        @if ($problems === [])
            <p class="rounded border border-green-300 bg-green-50 p-3 text-sm dark:border-green-700 dark:bg-green-950">The check found no errors.</p>
        @else
            <div class="rounded border border-red-300 bg-red-50 p-3 text-sm dark:border-red-700 dark:bg-red-950">
                <p class="mb-2 font-medium">The check found {{ count($problems) }} {{ count($problems) === 1 ? 'error' : 'errors' }}. Correct all errors before a session can start.</p>
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($problems as $problem)
                        <li>{{ $problem }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    @if (! empty($filled['objectives']))
        <h2 class="mb-2 text-lg font-semibold">Objectives</h2>
        <ul class="mb-8 list-disc pl-5 text-sm">
            @foreach ($filled['objectives'] as $objective)
                <li>{{ $objective }}</li>
            @endforeach
        </ul>
    @endif

    @if ($facts !== [])
        <h2 class="mb-2 text-lg font-semibold">Facts</h2>
        <table class="mb-8 w-full text-left text-sm">
            <tbody>
                @foreach ($facts as $name => $value)
                    <tr class="border-b border-zinc-200 dark:border-zinc-800">
                        <th scope="row" class="py-1 pr-4 font-mono text-xs font-normal">{{ $name }}</th>
                        <td class="py-1">{{ $value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @isset($filled['briefing'])
        <h2 class="mb-2 text-lg font-semibold">Briefing</h2>
        <p class="mb-8 whitespace-pre-line text-sm">{{ $filled['briefing'] }}</p>
    @endisset

    <h2 class="mb-2 text-lg font-semibold">Injects</h2>
    <ol class="space-y-4">
        @foreach ($filled['injects'] as $n => $inject)
            <li id="{{ $inject['id'] }}" class="rounded border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="mb-1 font-semibold">{{ $inject['title'] }}</h3>
                <p class="mb-2 text-zinc-600 dark:text-zinc-400">
                    <span class="font-mono">{{ $inject['id'] }}</span> ·
                    @isset($inject['day']) day {{ $inject['day'] }} @endisset {{ $inject['at'] }}
                    @isset($inject['audience']) · for {{ implode(', ', $inject['audience']) }} @endisset
                </p>
                @isset($inject['body'])
                    <p class="mb-2 whitespace-pre-line">{{ $inject['body'] }}</p>
                @endisset

                @foreach ($inject['artifacts'] ?? [] as $a => $artifact)
                    <div class="mb-2">
                        <p class="font-medium">{{ $artifact['title'] ?? 'Artifact' }}@isset($artifact['type']) ({{ $artifact['type'] }})@endisset</p>
                        @if (($artifact['type'] ?? null) === 'image')
                            @include('scenarios._picture')
                        @else
                            <pre class="overflow-x-auto rounded bg-zinc-100 p-2 text-xs dark:bg-zinc-800">{{ $artifact['content'] ?? '' }}</pre>
                        @endif
                    </div>
                @endforeach

                <details class="mb-2 text-sm">
                    <summary class="cursor-pointer">Add a picture</summary>
                    <form method="POST" action="{{ route('scenarios.pictures.add', [$scenario, $inject['id']]) }}" enctype="multipart/form-data" class="mt-2 max-w-lg rounded border border-zinc-200 p-3 dark:border-zinc-800">
                        @csrf
                        <label for="title-{{ $inject['id'] }}" class="mb-1 block text-sm font-medium">Title (for example: Server room door)</label>
                        <input id="title-{{ $inject['id'] }}" name="title" required maxlength="200" class="mb-3 w-full rounded border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
                        <label for="prompt-{{ $inject['id'] }}" class="mb-1 block text-sm font-medium">AI prompt: describe the photo (no readable text, no logos)</label>
                        <textarea id="prompt-{{ $inject['id'] }}" name="prompt" rows="2" class="mb-3 w-full rounded border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950" placeholder="An office door propped open with a chair at night, seen from a security camera"></textarea>
                        <label for="file-{{ $inject['id'] }}" class="mb-1 block text-sm font-medium">Or upload a picture (PNG, JPEG or WebP, 5 MB maximum)</label>
                        <input id="file-{{ $inject['id'] }}" name="file" type="file" accept=".png,.jpg,.jpeg,.webp" class="mb-3 block text-sm">
                        <p class="mb-3 text-xs text-zinc-600 dark:text-zinc-400">
                            @can('use-images') With a prompt and no upload, the image service makes the picture. A local picture takes about 5 to 10 minutes. @else The image service is off (AI_IMAGE_PROVIDER), so upload a picture or keep the prompt for later. @endcan
                        </p>
                        <x-button>Add the picture</x-button>
                    </form>
                </details>

                @if (! empty($inject['expected_plan']))
                    <p class="font-medium">Expected plan sections</p>
                    <ul class="mb-2 list-disc pl-5">
                        @foreach ($inject['expected_plan'] as $concept)
                            <li>
                                <span class="font-mono text-xs">{{ $concept }}</span>:
                                @forelse ($sections[$concept] ?? [] as $section)
                                    <a href="{{ route('plans.show', [$plan, 'q' => $section->title]) }}" class="underline underline-offset-4">{{ $section->number ? $section->number.' ' : '' }}{{ $section->title }}</a>@if (! $loop->last), @endif
                                @empty
                                    not in the plan
                                @endforelse
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (! empty($inject['questions']))
                    <p class="font-medium">Questions</p>
                    <ul class="mb-2 list-disc pl-5">
                        @foreach ($inject['questions'] as $question)
                            <li>{{ $question }}</li>
                        @endforeach
                    </ul>
                @endif

                @if (! empty($filled['rubric'][$inject['id']]))
                    <p class="font-medium">Rubric</p>
                    <ul class="mb-2 list-disc pl-5">
                        @foreach ($filled['rubric'][$inject['id']] as $item)
                            <li>{{ $item['item'] }} ({{ $item['points'] }} {{ $item['points'] === 1 ? 'point' : 'points' }})</li>
                        @endforeach
                    </ul>
                @endif

                @can('use-ai')
                    <div class="mb-2 flex flex-wrap gap-2">
                        @foreach (['inject' => 'Write the text with AI', 'artifact' => 'Add an artifact with AI', 'picture' => 'Add a picture prompt with AI', 'rubric' => 'Write the rubric with AI'] as $step => $label)
                            <form method="POST" action="{{ route('scenarios.ai', [$scenario, $inject['id'], $step]) }}">
                                @csrf
                                <button type="submit" class="rounded border border-zinc-300 px-2 py-1 text-xs hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">{{ $label }}</button>
                            </form>
                        @endforeach
                    </div>
                @endcan

                <p class="text-zinc-600 dark:text-zinc-400">
                    @if (! empty($inject['branches']))
                        Branches:
                        @foreach ($inject['branches'] as $branch)
                            {{ $branch['when'] }} → <a href="#{{ $branch['goto'] }}" class="font-mono underline underline-offset-4">{{ $branch['goto'] }}</a>@if (! $loop->last); @endif
                        @endforeach
                    @else
                        @forelse (ScenarioChecker::following($filled['injects'], $n) as [$next])
                            Next: <a href="#{{ $next }}" class="font-mono underline underline-offset-4">{{ $next }}</a>
                        @empty
                            End of the scenario.
                        @endforelse
                    @endif
                </p>
            </li>
        @endforeach
    </ol>

    <details class="mt-8">
        <summary class="cursor-pointer text-sm text-red-700 dark:text-red-400">Delete this scenario version</summary>
        <form method="POST" action="{{ route('scenarios.destroy', $scenario) }}" class="mt-2">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded bg-red-700 px-3 py-1 text-sm text-white hover:bg-red-600">Delete the scenario</button>
        </form>
    </details>
</x-layouts.app>
