<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // 1. Terisi lengkap?
        $data = $request->validate([
            'identitas' => ['required', 'string', 'max:100'],
            'password'  => ['required', 'string'],
        ], [
            'identitas.required' => 'NIP atau email wajib diisi.',
            'password.required'  => 'Kata sandi wajib diisi.',
        ]);

        $kunci = 'login:'.Str::lower($data['identitas']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            throw ValidationException::withMessages([
                'identitas' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.',
            ]);
        }

        // 2. Akun & kata sandi cocok?
        $user = User::where(fn ($q) => $q
                ->where('nip', $data['identitas'])
                ->orWhere('email', $data['identitas']))
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($kunci, 60);

            throw ValidationException::withMessages([
                'identitas' => 'NIP/email atau kata sandi salah.',
            ]);
        }

        // 3. Akun aktif?
        if ($user->status !== 'Aktif') {
            throw ValidationException::withMessages([
                'identitas' => 'Akun tidak aktif. Hubungi Kepala Bagian Administrasi Umum.',
            ]);
        }

        if (! $user->peranUtama()) {
            throw ValidationException::withMessages([
                'identitas' => 'Akun belum memiliki peran. Hubungi administrator.',
            ]);
        }

        RateLimiter::clear($kunci);

        // 4. Buat sesi (berakhir 30 menit tidak aktif)
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put([
            'jenis_login'    => 'manual',
            'terakhir_aktif' => now()->timestamp,
        ]);

        // 5. Arahkan ke halaman awal sesuai peran
        return redirect()->route($user->rutePeran());
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('beranda');
    }
}