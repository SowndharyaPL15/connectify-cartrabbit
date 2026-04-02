<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // ── Register ──────────────────────────────────────────────────────────────

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users',
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);

        return redirect()->route('chat.index')->with('success', 'Welcome to ChatApp!');
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            Auth::user()->update(['status' => 'online', 'last_seen' => now()]);
            return redirect()->intended(route('chat.index'));
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function logout(Request $request)
    {
        Auth::user()->update(['status' => 'offline', 'last_seen' => now()]);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    // ── Profile ───────────────────────────────────────────────────────────────

    public function profile()
    {
        return view('auth.profile', ['user' => Auth::user()]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name'    => 'required|string|max:100',
            'about'   => 'nullable|string|max:200',
            'photo'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            // Delete old photo
            if ($user->profile_photo) {
                @unlink(public_path('uploads/profiles/' . $user->profile_photo));
            }
            $filename = time() . '_' . $user->id . '.' . $request->file('photo')->getClientOriginalExtension();
            $request->file('photo')->move(public_path('uploads/profiles'), $filename);
            $user->profile_photo = $filename;
        }

        $user->name  = $validated['name'];
        $user->about = $validated['about'] ?? $user->about;
        $user->save();

        return back()->with('success', 'Profile updated successfully.');
    }

    // ── Google OAuth ──────────────────────────────────────────────────────────

    public function redirectToGoogle()
    {
        return \Laravel\Socialite\Facades\Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = \Laravel\Socialite\Facades\Socialite::driver('google')->user();
            
            $user = User::where('email', $googleUser->email)
                ->orWhere('google_id', $googleUser->id)
                ->first();

            if (!$user) {
                // Create a new user if they don't exist
                $user = User::create([
                    'name'          => $googleUser->name,
                    'email'         => $googleUser->email,
                    'google_id'     => $googleUser->id,
                    'google_token'  => $googleUser->token,
                    'password'      => \Illuminate\Support\Str::random(16), // Dummy password
                    'status'        => 'online',
                    'last_seen'     => now(),
                ]);
            } else {
                // Update existing user with Google ID if not already set
                $user->update([
                    'google_id'    => $googleUser->id,
                    'google_token' => $googleUser->token,
                    'status'       => 'online',
                    'last_seen'    => now(),
                ]);
            }

            Auth::login($user);
            session()->regenerate();

            return redirect()->route('chat.index');

        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['email' => 'Google authentication failed.']);
        }
    }
}
