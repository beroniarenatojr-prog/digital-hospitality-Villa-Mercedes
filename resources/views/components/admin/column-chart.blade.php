@props([
    'points' => [],
    'format' => 'number',
    'ariaLabel' => 'Chart',
])

@php
    /** @var list<array{period?: string, label: string, value: int|float}> $points */
    $values = array_map(static fn (array $point): float => (float) $point['value'], $points);
    $rawMax = max(0, ...($values ?: [0]));
    $niceMax = $rawMax <= 0 ? 1 : (int) max(1, ceil($rawMax / 4) * 4);
    if ($rawMax > 0 && $niceMax < $rawMax) {
        $niceMax = (int) ceil($rawMax);
    }
    $ticks = [$niceMax, (int) round($niceMax * 0.75), (int) round($niceMax * 0.5), (int) round($niceMax * 0.25), 0];
    $ticks = array_values(array_unique($ticks));

    $formatValue = static function (float $value) use ($format): string {
        if ($value <= 0) {
            return '';
        }

        if ($format === 'currency') {
            if ($value >= 1000) {
                $thousands = $value / 1000;

                return '₱'.(abs($thousands - round($thousands)) < 0.05
                    ? number_format($thousands, 0)
                    : number_format($thousands, 1)).'k';
            }

            return '₱'.number_format($value, 0);
        }

        return number_format($value);
    };
@endphp

<div {{ $attributes->class(['admin-vchart']) }} role="img" aria-label="{{ $ariaLabel }}">
    <div class="admin-vchart-y" aria-hidden="true">
        @foreach($ticks as $tick)
            <span>{{ $format === 'currency' && $tick > 0 ? '₱'.number_format($tick) : number_format($tick) }}</span>
        @endforeach
    </div>

    <div class="admin-vchart-plot">
        <div class="admin-vchart-grid" aria-hidden="true">
            @foreach($ticks as $tick)
                @if($tick > 0)
                    <div class="admin-vchart-gridline" @style(['bottom: '.(($tick / $niceMax) * 100).'%'])></div>
                @endif
            @endforeach
        </div>

        <div class="admin-vchart-bars">
            @foreach($points as $index => $point)
                @php
                    $value = (float) $point['value'];
                    $height = $value > 0 ? max(6, (int) round(($value / $niceMax) * 100)) : 0;
                    $title = ($point['period'] ?? $point['label']).': '.($format === 'currency'
                        ? '₱'.number_format($value, 2)
                        : number_format($value));
                @endphp
                <div class="admin-vchart-col" title="{{ $title }}">
                    <div class="admin-vchart-track">
                        @if($height > 0)
                            <span
                                class="admin-vchart-value"
                                @style(['--chart-h: '.$height.'%', '--chart-delay: '.($index * 45).'ms'])
                            >{{ $formatValue($value) }}</span>
                            <div
                                class="admin-vchart-fill"
                                @style(['--chart-h: '.$height.'%', '--chart-delay: '.($index * 45).'ms'])
                            ></div>
                        @endif
                    </div>
                    <span class="admin-vchart-label">{{ $point['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
