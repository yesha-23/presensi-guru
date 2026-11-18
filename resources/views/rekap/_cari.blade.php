{{--
    Kotak pencarian nama pegawai untuk tabel rekap / PKG (disaring langsung di browser).
    $tabel : id elemen <table> yang disaring.
    Baris pegawai diberi data-nama (huruf kecil) dan data-grup-baris; baris judul kelompok diberi data-grup.
    Baris "tidak ditemukan" diberi data-cari-kosong dan atribut hidden.
--}}
<label class="flex h-10 w-full items-center gap-2 rounded-xl border border-garis bg-white px-3 text-sm focus-within:border-navy md:w-80">
    <i data-lucide="search" class="h-4 w-4 shrink-0 text-gray-400"></i>
    <span class="sr-only">Cari nama pegawai</span>
    <input type="search" placeholder="Cari nama pegawai…" autocomplete="off" data-cari-tabel="{{ $tabel }}"
           class="h-full w-full min-w-0 border-0 bg-transparent p-0 text-sm outline-none focus:ring-0">
</label>

@once
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-cari-tabel]').forEach(function (input) {
            var tabel = document.getElementById(input.dataset.cariTabel);
            if (!tabel) return;
            var kosong = tabel.querySelector('[data-cari-kosong]');

            input.addEventListener('input', function () {
                var kata = input.value.trim().toLowerCase();
                var jumlah = 0;

                tabel.querySelectorAll('tbody tr[data-nama]').forEach(function (baris) {
                    var cocok = kata === '' || baris.dataset.nama.indexOf(kata) !== -1;
                    baris.hidden = !cocok;
                    if (cocok) jumlah++;
                });

                // Sembunyikan judul kelompok (A. GURU / B. STAF) yang tidak punya baris cocok
                tabel.querySelectorAll('tbody tr[data-grup]').forEach(function (judul) {
                    var adaIsi = tabel.querySelector('tbody tr[data-grup-baris="' + judul.dataset.grup + '"]:not([hidden])');
                    judul.hidden = kata !== '' && !adaIsi;
                });

                if (kosong) kosong.hidden = jumlah > 0;
            });
        });
    });
</script>
@endonce
