@extends('layouts.auth')
@section('title', 'Connectify — Modern Messaging')

@section('content')
<div class="home-page-wrapper">
    <!-- Abstract background -->
    <div class="home-illustration"></div>

    <header class="home-header">
        <a href="{{ url('/') }}" class="home-logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            Connectify
        </a>
        <nav class="home-header-nav">
            @auth
                <a href="{{ route('chat.index') }}" class="btn-primary" style="margin-top:0;">Open App</a>
            @else
                <a href="{{ route('login') }}" class="btn-ghost">Sign In</a>
                <a href="{{ route('register') }}" class="btn-header">Get Started</a>
            @endauth
        </nav>
    </header>

    <main class="home-hero">
        <div class="hero-pill">✨ The new standard in team messaging</div>
        <h1>Communicate with<br>Crystal Clarity</h1>
        <p class="home-hero-desc">
            Experience lightning-fast, real-time messaging designed for modern teams and communities. Stay connected completely secure, without the clutter.
        </p>
        
        <div class="hero-actions">
            @auth
                <a href="{{ route('chat.index') }}" class="btn-primary btn-large" style="margin-top:0;">Open Web App</a>
            @else
                <a href="{{ route('register') }}" class="btn-primary btn-large" style="margin-top:0;">Start for free</a>
                <a href="{{ route('login') }}" class="btn-secondary btn-large">Login to account</a>
            @endauth
        </div>
    </main>
</div>
@endsection
