<x-guest-layout title="Administrator Sign In">

    <div style="margin-bottom:1.25rem;">
        <span class="h-badge h-badge-teal" style="font-size:0.65rem;">ADMINISTRATOR ACCESS</span>
    </div>

    <h2 class="haven-auth-title">Admin Console</h2>
    <p class="haven-auth-desc">Restricted area. Administrator accounts are separate from driver accounts and are provisioned by the system owner only.</p>

    <form method="POST" action="{{ route('admin.login.store') }}" id="admin-login-form">
        @csrf

        <div style="margin-bottom:1.25rem;">
            <label class="haven-label" for="email">Administrator email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   autocomplete="username"
                   class="haven-input {{ $errors->has('email') ? 'haven-input-error' : '' }}"
                   placeholder="admin@example.com">
            @error('email')
                <p class="haven-error-msg">{{ $message }}</p>
            @enderror
        </div>

        <div style="margin-bottom:1.5rem;">
            <label class="haven-label" for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="haven-input {{ $errors->has('password') ? 'haven-input-error' : '' }}"
                   placeholder="••••••••">
            @error('password')
                <p class="haven-error-msg">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="haven-btn-primary">
            <span>Sign In to Admin Console</span>
        </button>

        <p style="text-align:center;margin-top:1.5rem;font-size:0.8rem;color:var(--h-text3);">
            Not an administrator?
            <a class="haven-link" href="{{ route('login') }}">Driver sign in</a>
        </p>
    </form>

</x-guest-layout>

