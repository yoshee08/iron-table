{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- One picture artifact on the review screen: the chosen picture, the AI prompt, AI pictures and an upload. --}}
@php($made = $pictures[$inject['id'].'#'.$a] ?? collect())
<div class="rounded border border-zinc-200 p-3 dark:border-zinc-800">
    @isset($artifact['image'])
        <img src="{{ route('scenarios.image', [$scenario, $artifact['image']]) }}" alt="{{ $artifact['title'] ?? 'Picture' }}" class="mb-2 max-h-64 rounded">
    @else
        <p class="mb-2 text-sm"><strong>No picture chosen yet.</strong></p>
    @endisset
    @isset($artifact['prompt'])
        <p class="mb-2 text-xs text-zinc-600 dark:text-zinc-400">AI prompt: {{ $artifact['prompt'] }}</p>
    @endisset

    @foreach ($made as $picture)
        <div class="mb-2 flex flex-wrap items-center gap-3 text-xs">
            @if ($picture->status === 'done')
                <img src="{{ route('scenarios.image', [$scenario, $picture->file]) }}" alt="AI picture {{ $picture->id }}" class="h-20 rounded">
                @if (($artifact['image'] ?? null) === $picture->file)
                    <span><strong>In use</strong></span>
                @else
                    <form method="POST" action="{{ route('scenarios.images.choose', [$scenario, $picture]) }}">
                        @csrf
                        <button type="submit" class="rounded border border-zinc-300 px-2 py-1 dark:border-zinc-700">Use this picture</button>
                    </form>
                @endif
            @elseif ($picture->status === 'failed')
                <span class="text-red-700 dark:text-red-400">Failed: {{ $picture->error }}</span>
            @else
                <span>Making the picture ({{ $picture->status }}, asked {{ $picture->created_at->diffForHumans() }}). This page reloads every minute.</span>
            @endif
        </div>
    @endforeach

    <div class="flex flex-wrap items-center gap-3 text-xs">
        @if (isset($artifact['prompt']) && auth()->user()->can('use-images'))
            <form method="POST" action="{{ route('scenarios.pictures.generate', [$scenario, $inject['id'], $a]) }}">
                @csrf
                <button type="submit" class="rounded border border-zinc-300 px-2 py-1 dark:border-zinc-700">{{ $made->isEmpty() ? 'Make the picture with AI' : 'Make another picture' }}</button>
            </form>
        @endif
        <form method="POST" action="{{ route('scenarios.pictures.upload', [$scenario, $inject['id'], $a]) }}" enctype="multipart/form-data" class="flex items-center gap-2">
            @csrf
            <label for="upload-{{ $inject['id'] }}-{{ $a }}">Upload a picture:</label>
            <input id="upload-{{ $inject['id'] }}-{{ $a }}" name="file" type="file" accept=".png,.jpg,.jpeg,.webp" required>
            <button type="submit" class="rounded border border-zinc-300 px-2 py-1 dark:border-zinc-700">Upload</button>
        </form>
    </div>
</div>
