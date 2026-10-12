<div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl bg-merah p-5 text-white">
        <div class="text-sm text-white/80">Sudah hadir</div>
        <div class="mt-1 text-4xl font-extrabold">{{ $ringkas['hadir'] }} <span class="text-xl font-bold text-white/70">/ {{ $ringkas['total'] }}</span></div>
        <div class="mt-1 text-sm text-white/80">{{ $ringkas['terlambat'] }} di antaranya terlambat</div>
    </div>
    <div class="rounded-2xl border border-garis bg-white p-5">
        <div class="text-sm text-gray-500">Belum presensi</div>
        <div class="mt-1 text-4xl font-extrabold text-merah">{{ $ringkas['belum'] }}</div>
        <div class="mt-1 text-sm text-gray-500">Pengingat dikirim pukul {{ str_replace(':', '.', substr($jamPengingat, 0, 5)) }}</div>
    </div>
    <div class="rounded-2xl border border-garis bg-white p-5">
        <div class="text-sm text-gray-500">Izin / Sakit</div>
        <div class="mt-1 text-4xl font-extrabold">{{ $ringkas['izin_sakit'] }}</div>
        <div class="mt-1 text-sm text-gray-500">Disetujui penyetuju</div>
    </div>
    <div class="rounded-2xl border border-garis bg-white p-5">
        <div class="text-sm text-gray-500">Tugas khusus</div>
        <div class="mt-1 text-4xl font-extrabold">{{ $ringkas['tugas'] }}</div>
        <div class="mt-1 text-sm text-gray-500">Sesuai surat tugas</div>
    </div>
</div>