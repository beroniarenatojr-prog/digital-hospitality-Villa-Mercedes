@if(session('reservation.selection.name'))
    <div class="mb-5 rounded-xl border border-border bg-background p-4 text-sm text-text">
        Continue with <strong>{{ session('reservation.selection.name') }}</strong> after signing in or creating your account. Your selected visit details are saved.
    </div>
@endif
