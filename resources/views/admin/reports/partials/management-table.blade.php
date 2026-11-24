@if ($table['rows'] === [])
    <p class="py-4 text-sm text-text-muted">No records match this report.</p>
@else
    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ $table['title'] }}">
        <table class="min-w-full text-sm">
            <caption class="sr-only">{{ $table['title'] }}</caption>
            <thead><tr>
                @foreach ($table['columns'] as $i => $column)
                    <th scope="col" class="border-b border-border px-3 py-3 font-semibold {{ in_array($table['types'][$i], ['money', 'number']) ? 'text-right' : 'text-left' }}">{{ $column }}</th>
                @endforeach
            </tr></thead>
            <tbody class="divide-y divide-border">
                @foreach ($table['rows'] as $row)
                    <tr>
                        @foreach ($row['cells'] as $i => $cell)
                            <td class="px-3 py-3 {{ in_array($table['types'][$i], ['money', 'number']) ? 'text-right tabular-nums whitespace-nowrap' : 'text-left' }}">
                                @if ($table['types'][$i] === 'money')
                                    <x-amount :value="$cell" />
                                @elseif ($table['types'][$i] === 'number')
                                    {{ number_format($cell) }}
                                @elseif ($table['types'][$i] === 'link')
                                    <a class="rounded font-semibold text-primary underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary" href="{{ $row['url'] }}">{{ $cell }}</a>
                                @else
                                    {{ $cell }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
