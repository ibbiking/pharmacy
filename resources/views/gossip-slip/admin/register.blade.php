@extends('gossip-slip.layouts.auth')

@section('content')
<div class="gs-auth-card">

    {{-- Branding --}}
    <div class="gs-text-center gs-mb-16">
        <div style="font-size:3.5rem; margin-bottom:8px;">🎮</div>
        <div class="gs-title" style="font-size:1.8rem;">Admin Bano!</div>
        <div class="gs-subtitle">Game Master ban ke sab ko hansao 😄</div>
    </div>

    {{-- Alerts --}}
    @if(session('error'))
        <div class="gs-alert gs-alert-error">⚠️ {{ session('error') }}</div>
    @endif
    @if($errors->any())
        @foreach($errors->all() as $err)
            <div class="gs-alert gs-alert-error">⚠️ {{ $err }}</div>
        @endforeach
    @endif

    {{-- Register Form --}}
    <form action="{{ route('gs.admin.register') }}" method="POST">
        @csrf

        <div class="gs-form-group">
            <label class="gs-label">😎 Display Name (jaise logon mein jaane jate ho)</label>
            <input class="gs-input" type="text" name="display_name"
                   value="{{ old('display_name') }}"
                   placeholder="Shazia the Legend" maxlength="100" required>
        </div>

        <div class="gs-form-group">
            <label class="gs-label">🙋 Username (login ke liye)</label>
            <input class="gs-input" type="text" name="username"
                   value="{{ old('username') }}"
                   placeholder="shazia_786" maxlength="50"
                   pattern="[a-zA-Z0-9_-]+" required>
            <small class="gs-text-muted">Sirf letters, numbers, _ aur - chalenge</small>
        </div>

        <div class="gs-form-group">
            <label class="gs-label">🔑 Password</label>
            <input class="gs-input" type="password" name="password"
                   placeholder="••••••••" minlength="6" required>
        </div>

        <div class="gs-form-group">
            <label class="gs-label">🔑 Password Confirm Karo</label>
            <input class="gs-input" type="password" name="password_confirmation"
                   placeholder="••••••••" required>
        </div>

        <button class="gs-btn gs-btn-primary gs-mt-8" type="submit">
            🚀 Game Master Bano!
        </button>
    </form>

    <div class="gs-divider"></div>

    <div class="gs-text-center">
        <p class="gs-text-muted">Pehle se account hai?</p>
        <a href="{{ route('gs.admin.login') }}"
           class="gs-btn gs-btn-outline gs-btn-inline gs-mt-8"
           style="font-size:0.9rem; padding:10px 20px;">
            🔐 Login Karo
        </a>
    </div>

</div>
@endsection
