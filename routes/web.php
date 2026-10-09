<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\DocumentVerifyController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\SpaController;

Route::get('/', [SpaController::class, 'index'])->name('dashboard');

Route::get('/login', [SpaController::class, 'index'])->name('login');

Route::post('/login', [LoginController::class, 'login'])->middleware(['throttle:auth_login', \App\Http\Middleware\VerifyRecaptcha::class]);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/forensic', [SpaController::class, 'index']);

Route::get('/forensic/login', [SpaController::class, 'index']);

// Sanctum SPA auth routes (need session middleware from web group)
Route::get('/api/auth/captcha', \App\Http\Controllers\Auth\CaptchaController::class)->middleware('throttle:60,1');
Route::post('/api/login', [LoginController::class, 'apiLogin'])->middleware(['throttle:auth_login', \App\Http\Middleware\VerifyRecaptcha::class]);
Route::post('/api/forensic/login', [LoginController::class, 'apiForensicLogin'])->middleware(['throttle:auth_login', \App\Http\Middleware\VerifyRecaptcha::class]);
Route::post('/api/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:sensitive');
Route::post('/api/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:sensitive');
Route::post('/api/logout', [LoginController::class, 'apiLogout'])->middleware('auth:sanctum');
Route::get('/api/user', function (Request $r) {
    return $r->user()->load('roles', 'zone', 'circle', 'permissions');
})->middleware(['auth:sanctum', 'account.security']);

Route::middleware(['auth:sanctum', 'account.security', 'throttle:sensitive'])->prefix('api/auth/mfa')->group(function () {
    Route::get('/status', [MfaController::class, 'status']);
    Route::post('/setup', [MfaController::class, 'setup']);
    Route::post('/confirm', [MfaController::class, 'confirm']);
    Route::post('/recovery-codes', [MfaController::class, 'regenerateRecoveryCodes']);
    Route::delete('/', [MfaController::class, 'disable']);
});

Route::put('/api/user/profile', function (Request $r) {
    $user = $r->user();
    if (!$r->filled('password')) {
        $r->request->remove('password');
        $r->json()->remove('password');
    }
    $data = $r->validate([
        'name' => 'sometimes|string|max:255',
        'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
        'password' => array_merge(['sometimes'], \App\Rules\StrongPassword::rules(false, true)),
    ]);
    $sensitiveChange = isset($data['password']) || (isset($data['email']) && strtolower($data['email']) !== $user->email);
    if (isset($data['email'])) {
        $data['email'] = strtolower($data['email']);
    }
    if ($sensitiveChange) {
        $r->validate(['current_password' => ['required', 'string', 'max:1024']]);
    }
    $user = \Illuminate\Support\Facades\DB::transaction(function () use ($r, $data, $sensitiveChange) {
        $locked = \App\Models\User::query()->lockForUpdate()->findOrFail($r->user()->id);
        abort_unless((int) $locked->security_version === (int) $r->session()->get('security_version'), 409, 'Your account changed. Please sign in again.');
        if ($sensitiveChange) {
            abort_unless(\Illuminate\Support\Facades\Hash::check($r->input('current_password'), $locked->password), 422, 'Current password is incorrect.');
        }
        $locked->update($data);
        return $locked;
    }, 3);
    if ($sensitiveChange) {
        $r->session()->regenerate();
        \App\Services\MfaService::markSession($r, $user, $user->mfa_enabled);
    }
    return response()->json(['message' => 'Profile updated', 'user' => $user->fresh()->load('roles', 'zone', 'circle')]);
})->middleware(['auth:sanctum', 'account.security', 'throttle:sensitive']);

Route::post('/api/user/upload-signature', function (Request $r) {
    $user = $r->user();
    $r->validate(['signature' => 'required|file|mimes:jpg,jpeg,png|max:1024']);
    $path = $r->file('signature')->store('signatures', 'public');
    if ($user->signature) {
        \Illuminate\Support\Facades\Storage::disk('public')->delete($user->signature);
    }
    $user->update(['signature' => $path]);
    return response()->json(['message' => 'Signature uploaded', 'user' => $user->fresh()->load('roles', 'zone', 'circle')]);
})->middleware(['auth:sanctum', 'account.security', 'throttle:sensitive']);

// PDF download (needs auth but Laravel handles via React link)
Route::get('verifications/reports/{report}/pdf', [VerificationController::class, 'downloadPdf'])
    ->name('verifications.reports.pdf')
    ->middleware(['auth', 'account.security']);

// Force logout - no auth required, destroys any lingering session
Route::get('/force-logout', function (Illuminate\Http\Request $r) {
    if (auth()->check()) {
        \Illuminate\Support\Facades\Cache::forget('user_online_' . auth()->id());
    }
    Auth::logout();
    $r->session()->flush();
    $r->session()->invalidate();
    $r->session()->regenerateToken();
    $domain = config('session.domain');
    $secure = config('session.secure', false);
    $sameSite = config('session.same_site', 'lax');
    $sessionCookie = config('session.cookie');
    return redirect('/login')
        ->withCookie(cookie($sessionCookie, null, -2628000, '/', $domain, $secure, true, false, $sameSite))
        ->withCookie(cookie('XSRF-TOKEN', null, -2628000, '/', $domain, $secure, false, false, $sameSite));
});

// Public notice / complaint slip / document verification (scanned via QR code)
Route::get('/verify/notice/{token}', [EnquiryController::class, 'verifyNotice'])->name('notice.verify');
Route::get('/verify/complaint/{id}/{token}', [ComplaintController::class, 'verifySlip'])->name('complaint.slip.verify');
Route::get('/verify/doc/{type}/{id}/{token}', [DocumentVerifyController::class, 'show'])->name('document.verify');

// React SPA - serve React app for all frontend routes
Route::get('/{any?}', [SpaController::class, 'index'])->where('any', '^(?!api/|sanctum/).*');
