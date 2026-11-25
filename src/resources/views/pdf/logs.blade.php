{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- The three logs (spec section 8.2) with both times on each entry (spec section 8.1). --}}
@use('App\Models\LogEntry')
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Logs: {{ $aar['title'] }}</title></head>
<body>
    @include('pdf._style')
    <h1>Logs: {{ $aar['title'] }}</h1>
    @foreach (['communication', 'action', 'validation'] as $type)
        <h2>{{ LogEntry::TYPES[$type]['label'] }}</h2>
        @php($entries = $logs->where('type', $type))
        @if ($entries->isEmpty())
            <p>No entries.</p>
        @else
            <table>
                <tr>
                    <th>Times</th>
                    @foreach (LogEntry::TYPES[$type]['fields'] as $field)
                        <th>{{ is_array($field) ? $field[0] : $field }}</th>
                    @endforeach
                    <th>Recorded by</th>
                </tr>
                @foreach ($entries as $entry)
                    <tr>
                        <td>Simulated {{ $entry->sim_day > 1 ? 'day '.$entry->sim_day.' ' : '' }}{{ $entry->sim_time }} (real {{ $entry->created_at->format('H:i, j M Y') }})</td>
                        @foreach (array_keys(LogEntry::TYPES[$type]['fields']) as $name)
                            <td>{{ $entry->fields[$name] ?? '' }}</td>
                        @endforeach
                        <td>{{ $entry->recorder?->name }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    @endforeach
</body>
</html>
