<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('notifikasi.index', [
            'judul'       => 'Notifikasi',
            'notifikasi'  => $user->notifications()->latest()->paginate(15),
            'belumDibaca' => $user->unreadNotifications()->count(),
        ]);
    }

    /** Klik notifikasi: tandai dibaca, lalu buka halaman terkait. */
    public function buka(Request $request, string $id)
    {
        $notifikasi = $request->user()->notifications()->findOrFail($id);
        $notifikasi->markAsRead();

        return redirect($notifikasi->data['url'] ?? route('dashboard'));
    }

    public function bacaSemua(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('sukses', 'Semua notifikasi ditandai sudah dibaca.');
    }
}