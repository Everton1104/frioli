<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\WhatsappPasswordResetController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'sendCode']);

    Route::get('login/senha',  [AuthenticatedSessionController::class, 'showSenha'])->name('login.senha');
    Route::post('login/senha', [AuthenticatedSessionController::class, 'loginComSenha'])->name('login.senha.check');

    Route::get('login/verificar',  [AuthenticatedSessionController::class, 'showVerify'])->name('login.verify');
    Route::post('login/verificar', [AuthenticatedSessionController::class, 'verify'])->name('login.verify.check');
    Route::post('login/verificar/reenviar', [AuthenticatedSessionController::class, 'resend'])->name('login.verify.resend');

    // Passo 1: informar WhatsApp
    Route::get('forgot-password', [WhatsappPasswordResetController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [WhatsappPasswordResetController::class, 'sendCode'])
        ->name('password.email');

    // Passo 2: digitar código recebido no WhatsApp
    Route::get('verificar-codigo-senha', [WhatsappPasswordResetController::class, 'showCodeForm'])
        ->name('password.whatsapp.verify');
    Route::post('verificar-codigo-senha', [WhatsappPasswordResetController::class, 'verifyCode'])
        ->name('password.whatsapp.check');

    // Passo 3: definir nova senha
    Route::get('redefinir-senha', [WhatsappPasswordResetController::class, 'showNewPasswordForm'])
        ->name('password.reset');
    Route::post('redefinir-senha', [WhatsappPasswordResetController::class, 'updatePassword'])
        ->name('password.store');
});

// Registro: apenas adm ou funcionário logado
Route::middleware('auth')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
