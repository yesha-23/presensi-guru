<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\SuratTugas;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DokumenSuratController extends Controller
{
    public function pdf(Request $request, SuratTugas $surat)
    {
        abort_unless(
            $surat->status === 'Disetujui'
                && $surat->pdf_path
                && Storage::exists($surat->pdf_path)
                && $this->boleh($request->user(), $surat),
            404
        );

        return Storage::download($surat->pdf_path, 'Surat Tugas '.str_replace('/', '-', $surat->nomor_surat).'.pdf');
    }

    public function lampiran(Request $request, SuratTugas $surat)
    {
        abort_unless(
            $surat->lampiran_path
                && Storage::exists($surat->lampiran_path)
                && $this->boleh($request->user(), $surat),
            404
        );

        return Storage::response($surat->lampiran_path);
    }

    private function boleh(User $user, SuratTugas $surat): bool
    {
        return $user->hasRole(Role::ADMIN)
            || $surat->pegawai()->whereKey($user->id)->exists()
            || $surat->persetujuan()->where('penyetuju_id', $user->id)->exists();
    }
}