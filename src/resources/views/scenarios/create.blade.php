{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app title="Import a scenario">
    <form method="POST" action="{{ route('scenarios.store') }}" enctype="multipart/form-data" class="max-w-md">
        @csrf
        <p class="mb-4 text-sm">A scenario is one YAML file (maximum 1 MB). The format is in the design specification, section 6. Start with the <a href="{{ route('scenarios.example') }}" class="underline underline-offset-4">example scenario</a>. To import a new version, change <code>meta.version</code> in the file.</p>
        <x-field name="file" label="Scenario file" type="file" accept=".yaml,.yml" required />
        <x-button>Import the scenario</x-button>
    </form>
</x-layouts.app>
