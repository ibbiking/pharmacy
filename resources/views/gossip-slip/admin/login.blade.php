@extends('gossip-slip.layouts.auth')

@section('content')
<div class="gs-auth-card">

    {{-- Branding --}}
    <div class="gs-text-center gs-mb-16">
        <div style="font-size:3.5rem; margin-bottom:8px; animation: bounce 2s ease-in-out infinite;">🎭</div>
        <div class="gs-title" style="font-size:1.8rem;">Gossip Slip</div>
        <div class="gs-subtitle">Fold &amp; Reveal (Who, What, Where)</div>
    </div>

    {{-- Alerts --}}
    @if(session('error'))
        <div class="gs-alert gs-alert-error">⚠️ {{ session('error') }}</div>
    @endif
    @if(session('success'))
        <div class="gs-alert gs-alert-success">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
        @foreach($errors->all() as $err)
            <div class="gs-alert gs-alert-error">⚠️ {{ $err }}</div>
        @endforeach
    @endif

    {{-- Login Form --}}
    <form action="{{ route('gs.admin.login') }}" method="POST">
        @csrf

        <div class="gs-form-group">
            <label class="gs-label">🙋 Username</label>
            <input class="gs-input" type="text" name="username"
                   value="{{ old('username') }}"
                   placeholder="apna_username" autocomplete="username" required>
        </div>

        <div class="gs-form-group">
            <label class="gs-label">🔑 Password</label>
            <input class="gs-input" type="password" name="password"
                   placeholder="••••••••" autocomplete="current-password" required>
        </div>

        <button class="gs-btn gs-btn-primary gs-mt-8" type="submit">
            🔐 Andar Aao!
        </button>
    </form>

    <div class="gs-divider"></div>

    <div class="gs-text-center">
        <p class="gs-text-muted">Naya account? Koi baat nahi!</p>
        <a href="{{ route('gs.admin.register') }}"
           class="gs-btn gs-btn-outline gs-btn-inline gs-mt-8"
           style="font-size:0.9rem; padding:10px 20px;">
            🚀 Naya Account Banao
        </a>
    </div>

</div>
@endsection
