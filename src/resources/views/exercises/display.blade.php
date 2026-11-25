{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Shared display (spec section 7.5): for a projector or a large screen in the room. --}}
<x-layouts.app :title="$exercise->title">
    <div id="live" data-live-channel="exercise.{{ $exercise->id }}" class="text-lg">
        <div class="mb-6 flex flex-wrap items-center gap-6 text-xl">
            @include('exercises._status', ['status' => $exercise->status])
            <span>Simulated time: <strong class="text-3xl">{{ $exercise->simClock() }}</strong></span>
        </div>
        @include('exercises._stopped')

        @if ($release)
            <h2 class="mb-4 text-4xl font-bold">{{ $release->title }}</h2>
            @include('exercises._timer')
            @if ($release->body !== '')
                <p class="mb-6 whitespace-pre-line text-2xl leading-relaxed">{{ $release->body }}</p>
            @endif
            @foreach ($release->artifacts as $n => $artifact)
                <x-artifact :artifact="$artifact" :id="'display-'.$release->id.'-'.$n" :url="isset($artifact['image']) ? route('exercises.image', [$exercise, $artifact['image']]) : null" />
            @endforeach
            @if ($release->questions !== [])
                <ul class="mt-6 list-disc pl-8 text-2xl">
                    @foreach ($release->questions as $question)
                        <li>{{ $question }}</li>
                    @endforeach
                </ul>
            @endif
        @elseif ($exercise->status === 'prepared')
            <p class="text-3xl">The exercise starts soon.</p>
        @else
            @isset($exercise->scenario['briefing'])
                <h2 class="mb-4 text-4xl font-bold">Briefing</h2>
                <p class="whitespace-pre-line text-2xl leading-relaxed">{{ $exercise->scenario['briefing'] }}</p>
            @endisset
        @endif
    </div>
</x-layouts.app>
