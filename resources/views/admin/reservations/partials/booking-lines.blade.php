<div class="space-y-3">
    @foreach ($lines as $line)
        <div>
            <div class="flex flex-wrap items-center gap-2"><span class="font-medium">{{ $line->facility->name }}</span><x-badge :status="$line->status" :label="$line->frontDeskStatusLabel()" /></div>
            <div class="mt-1 flex flex-wrap gap-x-1 text-xs text-text-muted tabular-nums"><span>{{ $line->check_in->format('M j, Y g:i A') }}</span><span>to {{ $line->check_out->format('M j, Y g:i A') }}</span></div>
            <p class="mt-1 text-xs text-text-muted">{{ $line->guest_count }} guests @if ($lines->count() > 1) · {{ $line->reservation_code }} @endif</p>
        </div>
    @endforeach
</div>
