<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TtdController extends Controller
{
    public function simpan(Request $request)
    {
        $request->validate([
            'ttd' => ['required', 'file', 'mimes:png', 'max:1024'],
        ], [
            'ttd.required' => 'Pilih gambar tanda tangan.',
            'ttd.mimes'    => 'Tanda tangan harus berformat PNG (sebaiknya berlatar transparan).',
            'ttd.max'      => 'Ukuran gambar tanda tangan maksimal 1 MB.',
        ]);

        $user = $request->user();

        if ($user->ttd_path) {
            Storage::delete($user->ttd_path);
        }

        $user->update([
            'ttd_path' => $request->file('ttd')->storeAs('ttd', "ttd_{$user->id}_".now()->timestamp.'.png'),
        ]);

        return back()->with('sukses', 'Gambar tanda tangan tersimpan.');
    }

    public function tampil(Request $request)
    {
        $path = $request->user()->ttd_path;
        abort_unless($path && Storage::exists($path), 404);

        return Storage::response($path);
    }
}