<?php

namespace App\Http\Controllers;

use App\Models\PengajuanIzin;
use App\Models\Role;
use App\Services\PersetujuanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BuktiPengajuanController extends Controller
{
    /** SR-NF04: bukti hanya untuk pemilik, kepala sekolah penyetuju, dan administrator. */
    public function __invoke(Request $request, PengajuanIzin $pengajuan, PersetujuanService $persetujuan)
    {
        $user = $request->user();

        $boleh = $pengajuan->user_id === $user->id
            || $user->hasRole(Role::ADMIN)
            || ($pengajuan->user && $persetujuan->bolehMenyetujui($user, $pengajuan->user));

        abort_unless($boleh && $pengajuan->bukti_path && Storage::exists($pengajuan->bukti_path), 404);

        return Storage::response($pengajuan->bukti_path);
    }
}