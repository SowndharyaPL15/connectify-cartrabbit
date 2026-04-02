@extends('layouts.app')
@section('title', 'Profile')

@section('content')
<div class="profile-page">
    <div class="profile-header">
        <a href="{{ route('chat.index') }}" class="back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 5l-7 7 7 7"/>
            </svg>
            Back to chats
        </a>
        <h2>Edit Profile</h2>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <div class="profile-card">
        <div class="profile-avatar-section">
            <div class="profile-avatar-wrap">
                <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" id="previewImg" class="profile-avatar-large">
                <label for="photo" class="avatar-edit-btn" title="Change photo">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </label>
            </div>
        </div>

        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="profile-form">
            @csrf
            <input type="file" id="photo" name="photo" accept="image/*" style="display:none" onchange="previewPhoto(this)">

            <div class="form-group">
                <label for="name">Display Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
            </div>
            <div class="form-group">
                <label for="about">About</label>
                <input type="text" id="about" name="about"
                       value="{{ old('about', $user->about) }}"
                       placeholder="Hey there! I am using ChatApp."
                       maxlength="200">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" value="{{ $user->email }}" disabled class="input-disabled">
            </div>
            <button type="submit" class="btn-primary">Save Changes</button>
        </form>
    </div>
</div>
<script>
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('previewImg').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endsection
