<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReporteController;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;



Route::middleware('auth')->group(function () {
    Route::get('/', fn() => view('reporte'));
    Route::get('/reporte', [ReporteController::class, 'index'])->name('reporte.index');
    Route::get('/reporte/filters', [ReporteController::class, 'filters'])->name('reporte.filters');
    Route::get('/reporte/data', [ReporteController::class, 'data'])->name('reporte.data');
});

// Login OIDC (Nextcloud)
Route::get('/auth/redirect', fn() => Socialite::driver('nextcloud')->redirect())->name('login');
Route::get('/auth/callback', function () {
    $oidcUser = Socialite::driver('nextcloud')->user();

    $user = User::updateOrCreate(
        ['email' => $oidcUser->getEmail() ?: ($oidcUser->getId().'@oidc.local')],
        ['name'  => $oidcUser->getName() ?? $oidcUser->getNickname() ?? 'Usuario OIDC']
    );

    Auth::login($user, true);
    return redirect()->intended('/reporte'); // vuelve a donde querías ir
});

// Logout local (opcional)
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout');

