<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AIController;
use Illuminate\Support\Facades\Route;

// ── Public ────────────────────────────────────────────────────────────────────

Route::get('/', fn() => view('welcome'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register',        [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',       [AuthController::class, 'register']);
    Route::get('/login',           [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',          [AuthController::class, 'login']);
    
    // Google OAuth
    Route::get('/auth/google',          [AuthController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

// ── Authenticated ─────────────────────────────────────────────────────────────

Route::middleware('auth')->group(function () {

    // Auth
    Route::post('/logout',         [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile',         [AuthController::class, 'profile'])->name('profile');
    Route::post('/profile',        [AuthController::class, 'updateProfile'])->name('profile.update');

    // Chat
    Route::get('/chat',                                   [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/u/{user}',                          [ChatController::class, 'showUserChat'])->name('chat.user');
    Route::get('/chat/{conversation}',                    [ChatController::class, 'showConversation'])->name('chat.conversation');
    Route::post('/chat/send',                             [ChatController::class, 'send'])->name('chat.send');
    Route::get('/chat/{conversation}/poll',               [ChatController::class, 'poll'])->name('chat.poll');
    Route::post('/chat/{conversation}/typing',             [ChatController::class, 'setTyping'])->name('chat.typing');
    Route::post('/chat/{conversation}/exit',               [ChatController::class, 'exitGroup'])->name('group.exit');
    Route::delete('/chat/{conversation}/clear',          [ChatController::class, 'clearConversation'])->name('chat.clear');

    Route::get('/chat/{conversation}/export',             [ChatController::class, 'exportConversation'])->name('chat.export');
    Route::delete('/message/{message}/delete-for-me',     [ChatController::class, 'deleteForMe'])->name('message.delete');
    Route::put('/message/{message}',                      [ChatController::class, 'updateMessage'])->name('message.update');
    Route::post('/message/{message}/star',                [ChatController::class, 'toggleStar'])->name('message.star');
    Route::post('/message/{message}/pin',                 [ChatController::class, 'togglePin'])->name('message.pin');
    Route::post('/message/{message}/favorite',            [ChatController::class, 'toggleFavorite'])->name('message.favorite');
    Route::get('/message/{message}/info',                 [ChatController::class, 'messageInfo'])->name('message.info');
    Route::post('/message/{message}/forward',             [ChatController::class, 'forwardMessage'])->name('message.forward');
    Route::post('/message/{message}/react',               [ChatController::class, 'react'])->name('message.react');
    Route::post('/groups',                                [ChatController::class, 'createGroup'])->name('group.create');
    Route::put('/groups/{conversation}',                  [ChatController::class, 'updateGroup'])->name('group.update');
    Route::post('/chat/u/{user}/toggle-block',            [ChatController::class, 'toggleBlock'])->name('user.block');
    Route::get('/users/search',                           [ChatController::class, 'searchUsers'])->name('users.search');



    // Contacts
    Route::post('/contacts',                              [\App\Http\Controllers\ContactController::class, 'store'])->name('contacts.store');
    Route::put('/contacts/{contact}',                     [\App\Http\Controllers\ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}',                  [\App\Http\Controllers\ContactController::class, 'destroy'])->name('contacts.destroy');

    // AI (rate-limited: 10 requests/minute)
    Route::post('/convert-tone',   [AIController::class, 'convertTone'])->name('ai.convert')->middleware('throttle:10,1');
    Route::post('/translate',      [AIController::class, 'translate'])->name('ai.translate')->middleware('throttle:20,1');
});
