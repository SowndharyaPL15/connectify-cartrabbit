@extends('layouts.auth')
@section('title', 'Login')

@section('content')
<div class="auth-split-wrapper">
    <!-- Left Pattern/Illustration Area -->
    <div class="auth-split-left">
        <div class="auth-hero-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
        </div>
        <h1>Welcome back to Connectify</h1>
        <p>Experience seamless, real-time messaging with your team and friends. Log in to pick up where you left off.</p>
    </div>

    <!-- Right Form Area -->
    <div class="auth-split-right">
        <div class="mobile-brand">
            <div class="mobile-logo-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
            </div>
            <h2>Connectify</h2>
        </div>

        <div class="auth-card">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <h2 class="auth-title">Welcome back</h2>
            <p class="auth-subtitle">Sign in to your account</p>

            <!-- DO NOT CHANGE THIS FORM LOGIC -->
            <form action="{{ route('login') }}" method="POST" class="auth-form">
                @csrf
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                           placeholder="you@example.com" required autocomplete="email">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           placeholder="••••••••" required autocomplete="current-password">
                </div>
                <div class="form-check">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>
                <button type="submit" class="btn-primary btn-full">Sign In</button>

                <div class="or-divider">OR</div>

                <a href="{{ route('auth.google') }}" class="btn-google">
                    <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google" class="google-icon">
                    Continue with Google
                </a>
            </form>

            <p class="auth-footer">
                Don't have an account? <a href="{{ route('register') }}">Create one</a>
            </p>
        </div>
    </div>
</div>
@endsection
