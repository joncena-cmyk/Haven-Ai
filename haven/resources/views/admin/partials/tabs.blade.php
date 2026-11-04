{{-- Admin section navigation now lives in the dedicated admin navbar; this partial only renders flash messages. --}}
@if(session('success') || session('status'))
    <div style="background:var(--h-green-bg);border:1px solid var(--h-green);border-radius:var(--h-radius);padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.8rem;color:var(--h-green);font-weight:600;">{{ session('success') ?? session('status') }}</div>
@endif
@if(session('error'))
    <div style="background:var(--h-red-bg);border:1px solid var(--h-red);border-radius:var(--h-radius);padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.8rem;color:var(--h-red);font-weight:600;">{{ session('error') }}</div>
@endif
