<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AkunController extends Controller
{
    /** Form ganti kata sandi milik pengguna yang sedang login (semua peran). */
    public function kataSandi()
    {
        return view('akun.kata-sandi', ['judul' => 'Ganti Kata Sandi']);
    }

    public function ubahKataSandi(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'kata_sandi_lama' => ['required', 'string', 'current_password'],
            'kata_sandi_baru' => ['required', 'string', 'min:8', 'max:64', 'confirmed', 'different:kata_sandi_lama'],
        ], [
            'kata_sandi_lama.required'         => 'Kata sandi lama wajib diisi.',
            'kata_sandi_lama.current_password' => 'Kata sandi lama salah.',
            'kata_sandi_baru.required'         => 'Kata sandi baru wajib diisi.',
            'kata_sandi_baru.min'              => 'Kata sandi baru minimal 8 karakter.',
            'kata_sandi_baru.max'              => 'Kata sandi baru maksimal 64 karakter.',
            'kata_sandi_baru.confirmed'        => 'Ulangi kata sandi baru tidak sama.',
            'kata_sandi_baru.different'        => 'Kata sandi baru harus berbeda dari kata sandi lama.',
        ]);

        // Disimpan sebagai hash bcrypt (cast 'hashed' pada model User)
        $user->update(['password' => $data['kata_sandi_baru']]);

        // Keluarkan sesi di perangkat lain; perangkat ini tetap masuk dengan ID sesi baru
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
        $request->session()->regenerate(true);

        return redirect()->route('akun.kata-sandi')
            ->with('sukses', 'Kata sandi berhasil diganti. Gunakan kata sandi baru saat masuk berikutnya.');
    }
}