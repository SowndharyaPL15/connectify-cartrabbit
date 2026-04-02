@extends('layouts.auth')
@section('title', 'Register')

@section('content')
<div class="auth-split-wrapper">
    <!-- Left Pattern/Illustration Area -->
    <div class="auth-split-left">
        <div class="auth-hero-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
        </div>
        <h1>Join Connectify Today</h1>
        <p>Create your account in seconds and connect with your important people instantly. Secure, simple, and fast messaging awaits.</p>
    </div>

    <!-- Right Form Area -->
    <div class="auth-split-right">
        <div class="mobile-brand">
            <div class="mobile-logo-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
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

            <h2 class="auth-title">Create account</h2>
            <p class="auth-subtitle">Join Connectify today</p>

            <!-- DO NOT CHANGE THIS FORM LOGIC -->
            <form action="{{ route('register') }}" method="POST" class="auth-form">
                @csrf
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}"
                           placeholder="John Doe" required autocomplete="name">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                           placeholder="you@example.com" required autocomplete="email">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           placeholder="Min. 6 characters" required>
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           placeholder="Repeat password" required>
                </div>
                <button type="submit" class="btn-primary btn-full">Create Account</button>

                <div class="or-divider">OR</div>

                <a href="{{ route('auth.google') }}" class="btn-google">
                    <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google" class="google-icon">
                    Continue with Google
                </a>
            </form>

            <p class="auth-footer">
                Already have an account? <a href="{{ route('login') }}">Sign in</a>
            </p>
        </div>
    </div>
</div>
@endsection
