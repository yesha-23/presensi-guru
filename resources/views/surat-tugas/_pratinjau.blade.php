{{-- Pratinjau kertas A4: diperkecil otomatis (lihat app.js) agar selalu muat di kolomnya --}}
<div class="min-w-0 rounded-3xl border border-garis bg-gray-100 p-3 sm:p-4">
    <div data-pratinjau-wadah class="overflow-hidden">
        <div data-kertas class="origin-top-left shadow-md" style="width: 21cm;">
            @include('surat-tugas._isi', $dataSurat)
        </div>
    </div>
</div>