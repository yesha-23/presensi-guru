{{--
    Bantuan kesalahan untuk formulir pegawai (tindak lanjut uji usabilitas, temuan no. 4):
    1. keterangan "* wajib diisi" dan ringkasan kesalahan di atas formulir;
    2. kolom yang salah diberi bingkai merah, lalu halaman digulir ke kolom pertama yang salah;
    3. kolom bertanda data-wajib="pesan" diperiksa sebelum formulir dikirim.

    Parameter:
    - $form   : id elemen <form>
    - $judul  : kata pembuka ringkasan, mis. "Pengajuan" atau "Usulan"
    - $berkas : true bila formulir memiliki unggahan berkas (berkas hilang setelah halaman dimuat ulang)
--}}
@php
    $judul = $judul ?? 'Pengajuan';
    $berkas = $berkas ?? false;
@endphp

<p class="text-xs text-gray-500"><span class="font-semibold text-merah">*</span> wajib diisi</p>

<div data-ringkasan-galat role="alert" tabindex="-1"
     class="{{ $errors->any() ? '' : 'hidden' }} rounded-2xl border border-red-200 bg-merah-muda p-4 text-sm text-merah-tua outline-none">
    <div class="flex items-start gap-2">
        <i data-lucide="triangle-alert" class="mt-0.5 h-4 w-4 shrink-0"></i>
        <div>
            <p class="font-semibold" data-judul-galat>
                {{ $judul }} belum terkirim. Perbaiki {{ count($errors->all()) }} isian bertanda merah di bawah ini.
            </p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5" data-daftar-galat>
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
            @if ($berkas && $errors->any())
                <p class="mt-2 text-xs">Berkas yang sudah dipilih sebelumnya perlu dipilih ulang sebelum mengirim.</p>
            @endif
        </div>
    </div>
</div>

@push('script')
<script>
    (() => {
        const form = document.getElementById(@json($form));
        if (!form) return;

        const ringkasan = form.querySelector('[data-ringkasan-galat]');
        const judul = ringkasan.querySelector('[data-judul-galat]');
        const daftar = ringkasan.querySelector('[data-daftar-galat]');
        const kata = @json($judul);

        // Input berkas tersembunyi (sr-only), jadi yang diberi bingkai merah adalah kotak unggahnya
        const sasaran = (input) => (input.type === 'file' ? input.closest('label') ?? input : input);

        function tandai(input, salah) {
            const el = sasaran(input);
            salah ? el.setAttribute('aria-invalid', 'true') : el.removeAttribute('aria-invalid');
        }

        function fokus(input) {
            sasaran(input).scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (input.type !== 'file') input.focus({ preventScroll: true });
        }

        // 1) Kesalahan dari server: tandai kolomnya dan arahkan pengguna ke kolom pertama
        const salahServer = @json(array_keys($errors->getMessages()))
            .map((nama) => form.elements[nama])
            .filter(Boolean)
            .map((el) => (el instanceof RadioNodeList ? el[0] : el));

        salahServer.forEach((input) => tandai(input, true));
        if (salahServer.length) fokus(salahServer[0]);
        else if (!ringkasan.classList.contains('hidden')) ringkasan.focus();

        // Bingkai merah dan pesan tambahan hilang begitu kolom diperbaiki
        const bersihkan = (e) => {
            if (!e.target.name) return;
            tandai(e.target, false);
            sasaran(e.target).parentElement.querySelectorAll('[data-galat-klien]').forEach((p) => p.remove());
        };
        form.addEventListener('input', bersihkan);
        form.addEventListener('change', bersihkan);

        // 2) Periksa kolom wajib sebelum dikirim, supaya pengguna tidak perlu menunggu jawaban server:
        //    kosong (data-wajib) atau isinya kurang dari batas minimal (data-min + data-pesan-min)
        form.addEventListener('submit', (e) => {
            form.querySelectorAll('[data-galat-klien]').forEach((p) => p.remove());

            const salah = [];
            form.querySelectorAll('[data-wajib]').forEach((input) => {
                if (input.type === 'file') {
                    if (input.files.length === 0) salah.push({ input, pesan: input.dataset.wajib });
                    return;
                }
                const isi = input.value.trim();
                if (!isi) {
                    salah.push({ input, pesan: input.dataset.wajib });
                } else if (input.dataset.min && isi.length < Number(input.dataset.min)) {
                    salah.push({ input, pesan: input.dataset.pesanMin });
                }
            });
            if (!salah.length) return;

            e.preventDefault();

            salah.forEach(({ input, pesan }) => {
                tandai(input, true);
                const el = sasaran(input);
                const sudahAda = [...el.parentElement.children].some((c) => c.textContent.trim() === pesan);
                if (sudahAda) return;

                const span = document.createElement('span');
                span.dataset.galatKlien = '';
                span.className = 'mt-1 block text-xs font-normal text-merah';
                span.textContent = pesan;
                el.insertAdjacentElement('afterend', span);
            });

            judul.textContent = `${kata} belum terkirim. Perbaiki ${salah.length} isian bertanda merah di bawah ini.`;
            daftar.replaceChildren(...salah.map(({ pesan }) => {
                const li = document.createElement('li');
                li.textContent = pesan;
                return li;
            }));
            ringkasan.classList.remove('hidden');
            fokus(salah[0].input);
        });
    })();
</script>
@endpush
