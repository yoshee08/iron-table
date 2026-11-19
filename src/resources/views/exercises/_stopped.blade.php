{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@if ($exercise->status === 'stopped')
    <p role="alert" class="mb-4 rounded border-2 border-red-700 bg-red-50 p-4 text-base font-semibold text-red-900 dark:bg-red-950 dark:text-red-100">
        EXERCISE STOPPED. This is not part of the exercise: a real event needs attention. Wait for the moderator.
    </p>
@endif
