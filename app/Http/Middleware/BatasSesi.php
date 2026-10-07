<?php

namespace App\Http\Middleware;

use App\Models\Pengaturan;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BatasSesi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $sesi = $request->session();
            $otomatis = $sesi->get('jenis_login') === 'otomatis';

            $batasMenit = (int) Pengaturan::ambil(
                $otomatis ? 'sesi_otomatis_menit' : 'sesi_manual_menit',
                $otomatis ? 5 : 30
            );

            $terakhirAktif = $sesi->get('terakhir_aktif');

            if ($terakhirAktif && now()->timestamp - $terakhirAktif > $batasMenit * 60) {
                Auth::logout();
                $sesi->invalidate();
                $sesi->regenerateToken();

                return redirect()->route('beranda')
                    ->with('info', 'Sesi berakhir karena tidak ada aktivitas. Silakan masuk kembali.');
            }

            // Setiap aktivitas mengulang hitungan dari awal
            $sesi->put('terakhir_aktif', now()->timestamp);
        }

        return $next($request);
    }
}