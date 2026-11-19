{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{--
    One artifact, drawn as a realistic screen (spec section 7.2). Text only: the
    content always agrees with the scenario facts. "Open large" uses the URL
    fragment (:target), so it needs no script.
    Types: email, log, ticket, chat, sms, ransom_note, report (and any other type: document).
--}}
@props(['artifact', 'id'])
@php
    $type = strtolower((string) ($artifact['type'] ?? 'report'));
    $title = (string) ($artifact['title'] ?? ucfirst(str_replace('_', ' ', $type)));
    $content = (string) ($artifact['content'] ?? '');
    $lines = preg_split('/\R/', rtrim($content));

    // Email: header lines ("From: ...") until the first empty line.
    $headers = [];
    if ($type === 'email') {
        while ($lines !== [] && preg_match('/^(From|To|Cc|Subject|Date|Sent):\s*(.*)$/i', $lines[0], $m)) {
            $headers[ucfirst(strtolower($m[1]))] = $m[2];
            array_shift($lines);
        }
        if (($lines[0] ?? null) === '') {
            array_shift($lines);
        }
    }
    // Chat and SMS: "Name: message" lines.
    $messages = [];
    if (in_array($type, ['chat', 'sms'], true)) {
        foreach ($lines as $line) {
            $messages[] = preg_match('/^([^:\d][^:]{0,30}):\s+(.+)$/', $line, $m) ? [$m[1], $m[2]] : [null, $line];
        }
    }
@endphp
<figure id="{{ $id }}" class="group mb-3 target:fixed target:inset-0 target:z-50 target:m-0 target:overflow-auto target:bg-zinc-950/80 target:p-4 sm:target:p-10">
    <div class="mx-auto max-w-3xl">
        <figcaption class="mb-1 flex items-center justify-between gap-2 text-xs text-zinc-600 group-target:text-zinc-200 dark:text-zinc-400">
            <span>{{ ucfirst(str_replace('_', ' ', $type)) }}: {{ $title }}</span>
            <a href="#{{ $id }}" class="underline underline-offset-4 group-target:hidden">Open large</a>
            <a href="#" class="hidden underline underline-offset-4 group-target:inline">Close</a>
        </figcaption>

        @switch($type)
            @case('email')
                <div class="overflow-hidden rounded-lg border border-zinc-300 bg-white text-zinc-900 shadow-sm">
                    <div class="border-b border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-base font-semibold">{{ $headers['Subject'] ?? $title }}</p>
                        @foreach (['From', 'To', 'Cc', 'Date', 'Sent'] as $field)
                            @isset($headers[$field])
                                <p class="text-sm"><span class="text-zinc-500">{{ $field }}:</span> {{ $headers[$field] }}</p>
                            @endisset
                        @endforeach
                    </div>
                    <div class="whitespace-pre-line px-4 py-3 text-sm">{{ implode("\n", $lines) }}</div>
                </div>
                @break

            @case('log')
                <pre class="overflow-x-auto rounded-lg bg-zinc-900 p-3 font-mono text-xs leading-relaxed text-green-300 shadow-sm">{{ $content }}</pre>
                @break

            @case('ticket')
                <div class="rounded-lg border-l-4 border-blue-600 bg-white p-4 text-zinc-900 shadow-sm">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-blue-700">Help desk ticket</p>
                    <p class="mb-2 font-semibold">{{ $title }}</p>
                    <div class="whitespace-pre-line text-sm">{{ $content }}</div>
                </div>
                @break

            @case('chat')
            @case('sms')
                <div class="{{ $type === 'sms' ? 'mx-auto max-w-xs rounded-3xl border-8 border-zinc-800 bg-zinc-100 p-3' : 'rounded-lg border border-zinc-300 bg-zinc-50 p-3' }} space-y-2 text-sm text-zinc-900 shadow-sm">
                    @foreach ($messages as [$name, $text])
                        <div class="rounded-2xl bg-white px-3 py-2 shadow-sm">
                            @if ($name)<p class="text-xs font-semibold text-zinc-600">{{ $name }}</p>@endif
                            <p>{{ $text }}</p>
                        </div>
                    @endforeach
                </div>
                @break

            @case('ransom_note')
            @case('ransom')
                <div class="rounded-lg border-4 border-red-700 bg-black p-6 text-center font-mono text-sm text-red-500 shadow-sm">
                    <p class="mb-3 text-lg font-bold uppercase">{{ $title }}</p>
                    <div class="whitespace-pre-line text-left">{{ $content }}</div>
                </div>
                @break

            @default
                <div class="rounded-lg border border-zinc-300 bg-white p-6 text-zinc-900 shadow-sm">
                    <p class="mb-3 border-b border-zinc-200 pb-2 font-serif text-lg font-semibold">{{ $title }}</p>
                    <div class="whitespace-pre-line font-serif text-sm leading-relaxed">{{ $content }}</div>
                </div>
        @endswitch
    </div>
</figure>
