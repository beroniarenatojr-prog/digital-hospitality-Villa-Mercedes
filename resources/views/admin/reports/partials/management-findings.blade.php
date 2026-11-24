<section aria-labelledby="report-findings" class="mb-6 rounded-xl border border-border bg-surface p-5">
    <h2 id="report-findings" class="text-base font-semibold text-text">{{ $reportView === 'balances' ? 'What needs attention' : 'What this period shows' }}</h2>
    @if ($report['comparison'])
        <p class="mt-2 text-sm text-text-muted">Compared with {{ $report['comparison']['from'] }} to {{ $report['comparison']['until'] }} ({{ $report['comparison']['days'] }} days), using the same filters. Statuses reflect records now, not historical snapshots.</p>
    @endif
    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-relaxed text-text">
        @foreach ($report['findings'] as $finding)
            <li>{{ $finding }}</li>
        @endforeach
    </ul>
</section>
