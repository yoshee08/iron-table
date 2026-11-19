{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app title="Upload a plan">
    <form method="POST" action="{{ route('plans.store') }}" enctype="multipart/form-data" class="max-w-md">
        @csrf
        <p class="mb-4 text-sm">Use a PDF with selectable text, a DOCX file or a Markdown file. Maximum size: 20 MB. Each upload makes a new version. Exercises keep the version that they used.</p>
        <x-field name="title" label="Title (optional, for example: Incident Response Plan, January 2026)" />
        <x-field name="file" label="Plan file" type="file" accept=".pdf,.docx,.md" required />
        <x-button>Upload and read the plan</x-button>
    </form>
</x-layouts.app>
