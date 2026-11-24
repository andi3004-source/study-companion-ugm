<?php

use App\Http\Controllers\BukuSakuController;
use App\Http\Controllers\VoiceCommandController;
use App\Livewire\Bantuan;
use App\Livewire\Conversation\Chat;
use App\Livewire\Conversation\Picker;
use App\Livewire\PronunciationCheck;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;

Route::get('/', fn () => view('layouts.study', [
    'title' => 'Tuturnusa',
    'slot' => new HtmlString(view('home')->render()),
]))->name('home');
Route::get('/dashboard', fn () => redirect('/', 302));
Route::get('/conversation', Picker::class)->name('conversation');
Route::get('/conversation/{session}', Chat::class)->name('conversation.chat');
Route::get('/pronunciation-check', PronunciationCheck::class)->name('pronunciation-check');
Route::get('/buku-saku', [BukuSakuController::class, 'index'])->name('buku-saku');
Route::get('/buku-saku/{buku}', [BukuSakuController::class, 'book'])->name('buku-saku.book');
Route::get('/buku-saku/{buku}/{bab}', [BukuSakuController::class, 'chapter'])->name('buku-saku.chapter');
Route::get('/bantuan', Bantuan::class)->name('bantuan');
Route::post('/voice/command', VoiceCommandController::class)->middleware('throttle:voice-command')->name('voice.command');
