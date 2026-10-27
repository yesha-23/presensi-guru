import * as faceapi from '@vladmandic/face-api';

const akar = document.getElementById('halaman-presensi');
if (akar) {
    jalankan(akar);
}

async function jalankan(akar) {
    const $ = (id) => document.getElementById(id);
    const video = akar.querySelector('video');
    const oval = $('oval');
    const tombolUlang = $('tombol-scan'); // berfungsi sebagai tombol "Coba lagi"
    const popup = $('popup');
    const isiPopup = $('isi-popup');
    const url = akar.dataset;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const lokasi = { lat: Number(url.lat), lng: Number(url.lng), radius: Number(url.radius) };

    // 224: cepat, dipakai selama menunggu kedip (wajah biasanya dekat ke kamera HP)
    const opsiKedip = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.4 });
    // 320: cadangan bila wajah tidak terdeteksi dengan 224 (mis. webcam laptop, wajah agak jauh)
    const opsiCepat = new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.4 });
    // 416: lebih teliti, dipakai sekali untuk menghitung descriptor wajah
    const opsiAkurat = new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });
    // maximumAge 10 detik: posisi yang baru saja didapat boleh dipakai ulang -> GPS jauh lebih cepat
    const opsiGps = { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 };
    const modeDebug = new URLSearchParams(location.search).has('debug');

    let posisi = null;
    let galatGps = null;
    let idGps = null;
    let berjalan = false;
    let modelSiap = false;
    let janjiModel = null;

    const tidur = (ms) => new Promise((selesai) => setTimeout(selesai, ms));
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

    // ================= Pencatat waktu (untuk tabel pengujian Bab IV) =================
    let t0 = performance.now();
    const catatanWaktu = {};
    const catatWaktu = (tahap) => {
        if (!(tahap in catatanWaktu)) catatanWaktu[tahap] = Math.round(performance.now() - t0);
    };
    const totalDetik = () => ((performance.now() - t0) / 1000).toFixed(1).replace('.', ',');

    // Popup tidak boleh ditutup dengan tombol Esc
    popup.addEventListener('cancel', (e) => e.preventDefault());

    function petunjuk(judul, sub = '') {
        $('petunjuk').textContent = judul;
        $('sub-petunjuk').textContent = sub;
    }

    function aturOval(terdeteksi) {
        oval.classList.toggle('border-kuning', !terdeteksi);
        oval.classList.toggle('border-emerald-400', terdeteksi);
    }

    // Label langkah di atas kamera: 'tunggu' | 'ok' | 'gagal'
    function tandai(nama, keadaan) {
        const el = akar.querySelector(`[data-langkah="${nama}"]`);
        if (!el || el.dataset.keadaan === keadaan) return;
        el.dataset.keadaan = keadaan;
        el.classList.toggle('bg-white/90', keadaan === 'tunggu');
        el.classList.toggle('text-gray-700', keadaan === 'tunggu');
        el.classList.toggle('bg-emerald-500', keadaan === 'ok');
        el.classList.toggle('bg-red-600', keadaan === 'gagal');
        el.classList.toggle('text-white', keadaan !== 'tunggu');
        el.querySelector('[data-ikon]').textContent = keadaan === 'ok' ? '✓' : keadaan === 'gagal' ? '!' : '•';
    }

    function siapUlang(judul, sub) {
        berjalan = false;
        petunjuk(judul, sub);
        aturOval(false);
        tombolUlang.classList.remove('hidden');
        tombolUlang.disabled = false;
    }

    // ================= 1. Model (dimuat paralel dengan kamera & GPS) =================
    async function muatModel() {
        try {
            await faceapi.tf.setBackend('webgl');
        } catch {
            // backend bawaan dipakai jika WebGL tidak tersedia
        }
        await faceapi.tf.ready();
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(url.urlModel),
            faceapi.nets.faceLandmark68Net.loadFromUri(url.urlModel),
            faceapi.nets.faceRecognitionNet.loadFromUri(url.urlModel),
        ]);
        catatWaktu('model dimuat');

        // Pemanasan: inferensi pertama di WebGL lambat karena menyiapkan shader.
        // Dilakukan sekarang (saat kamera masih menyala) supaya deteksi wajah pertama langsung cepat.
        try {
            const kosong = document.createElement('canvas');
            kosong.width = kosong.height = 160;
            await faceapi.detectSingleFace(kosong, opsiKedip);
            await faceapi.detectFaceLandmarks(kosong);
            await faceapi.computeFaceDescriptor(kosong);
        } catch {
            // pemanasan boleh gagal, tidak memengaruhi presensi
        }
        modelSiap = true;
        catatWaktu('model siap (setelah pemanasan)');
    }

    // ================= 2. GPS (berjalan di belakang, tidak menghalangi kamera) =================
    function jarakMeter(lat, lng) {
        const rad = (d) => (d * Math.PI) / 180;
        const dLat = rad(lat - lokasi.lat);
        const dLng = rad(lng - lokasi.lng);
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(rad(lokasi.lat)) * Math.cos(rad(lat)) * Math.sin(dLng / 2) ** 2;
        return 6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function perbaruiJarak() {
        if (!posisi) return;
        const jarak = Math.round(jarakMeter(posisi.latitude, posisi.longitude));
        const bar = $('bar-jarak');

        $('teks-jarak').textContent = `${jarak} / ${lokasi.radius} m`;
        $('teks-akurasi').textContent = `±${Math.round(posisi.accuracy)} m`;
        bar.style.width = `${Math.min(100, (jarak / lokasi.radius) * 100)}%`;
        bar.classList.toggle('bg-merah', jarak > lokasi.radius);
        bar.classList.toggle('bg-blue-700', jarak <= lokasi.radius);
    }

    function mulaiGps() {
        if (idGps !== null) return;
        galatGps = null;
        tandai('lokasi', posisi ? 'ok' : 'tunggu');

        idGps = navigator.geolocation.watchPosition(
            (p) => {
                posisi = p.coords;
                galatGps = null;
                perbaruiJarak();
                tandai('lokasi', 'ok');
                catatWaktu('lokasi didapat');
            },
            (e) => {
                galatGps = e;
                if (!posisi) tandai('lokasi', 'gagal');
            },
            opsiGps
        );
    }

    // ================= 3. Kamera =================
    async function nyalakanKamera() {
        if (video.srcObject) return true;
        tandai('kamera', 'tunggu');

        try {
            video.srcObject = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false,
            });
            await video.play();
            tandai('kamera', 'ok');
            catatWaktu('kamera menyala');
            return true;
        } catch (e) {
            console.error(e);
            tandai('kamera', 'gagal');
            return e;
        }
    }

    function hentikan() {
        berjalan = false;
        video.srcObject?.getTracks().forEach((t) => t.stop());
        video.srcObject = null;
        if (idGps !== null) navigator.geolocation.clearWatch(idGps);
        idGps = null;
    }

    // ================= Alur utama: semuanya otomatis sejak halaman dibuka =================
    async function mulai() {
        tutupPopup();
        tombolUlang.classList.add('hidden');
        tombolUlang.disabled = true;
        aturOval(false);
        tandai('wajah', 'tunggu');
        tandai('kedip', 'tunggu');

        t0 = performance.now();
        Object.keys(catatanWaktu).forEach((k) => delete catatanWaktu[k]);

        if (!navigator.mediaDevices?.getUserMedia || !navigator.geolocation) {
            siapUlang('Browser tidak mendukung', 'Buka lewat https:// di Google Chrome');
            return;
        }

        petunjuk(
            modelSiap ? 'Menyalakan kamera…' : 'Menyiapkan kamera dan pengenalan wajah…',
            'Izinkan akses kamera dan lokasi jika diminta'
        );

        // Ketiganya dijalankan BERSAMAAN, tidak saling menunggu
        mulaiGps();
        janjiModel ??= muatModel();
        const kamera = await nyalakanKamera();

        if (kamera !== true) {
            hentikan();
            const ditolak = kamera?.name === 'NotAllowedError';
            siapUlang(
                'Kamera tidak dapat dibuka',
                ditolak
                    ? 'Ketuk ikon di samping alamat situs, izinkan Kamera dan Lokasi, lalu tekan Coba lagi.'
                    : 'Tutup aplikasi lain yang memakai kamera, lalu tekan Coba lagi.'
            );
            return;
        }

        if (!modelSiap) petunjuk('Menyiapkan pengenalan wajah…', 'Hanya lama pada pemakaian pertama');

        try {
            await janjiModel;
        } catch (e) {
            console.error(e);
            janjiModel = null; // supaya "Coba lagi" memuat ulang model
            hentikan();
            siapUlang('Model pengenalan wajah gagal dimuat', 'Periksa koneksi internet, lalu tekan Coba lagi');
            return;
        }

        await deteksiKedip();
    }

    tombolUlang.addEventListener('click', mulai);
    window.addEventListener('pagehide', hentikan);
    window.addEventListener('beforeunload', hentikan);

    // ================= 4. Cek keaktifan: kedip mata (EAR) =================
    const BATAS_PEJAM = 0.92;      // EAR < 92% EAR normal  -> mata dianggap terpejam
    const BATAS_BUKA = 0.97;       // EAR > 97% EAR normal  -> mata dianggap terbuka lagi
    const BATAS_GERAK = 0.10;      // gerak hidung per bingkai; dilonggarkan agar goyangan tangan tidak membatalkan kedip
    const BINGKAI_KALIBRASI = 5;   // cukup 5 bingkai mata terbuka untuk menentukan EAR normal
    const MAKS_TERPEJAM_MS = 3000; // terpejam lebih lama dari ini dianggap bukan kedip

    // Jarak antara dua titik
    const titik = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);

    // Eye Aspect Ratio: tinggi mata dibagi lebar mata (6 titik landmark per mata)
    const rasioMata = (p) => (titik(p[1], p[5]) + titik(p[2], p[4])) / (2 * titik(p[0], p[3]));

    // Titik tengah sekumpulan titik (dipakai untuk pusat mata)
    const tengah = (kumpulan) => ({
        x: kumpulan.reduce((s, p) => s + p.x, 0) / kumpulan.length,
        y: kumpulan.reduce((s, p) => s + p.y, 0) / kumpulan.length,
    });

    // Nilai tengah (median), lebih tahan terhadap lonjakan dibanding rata-rata
    const median = (arr) => {
        const urut = [...arr].sort((a, b) => a - b);
        return urut[Math.floor(urut.length / 2)];
    };

    // Bekukan satu bingkai video ke kanvas lebar 480 px
    function ambilBingkai() {
        const kanvas = document.createElement('canvas');
        kanvas.width = 480;
        kanvas.height = Math.round((video.videoHeight * 480) / video.videoWidth);
        kanvas.getContext('2d').drawImage(video, 0, 0, kanvas.width, kanvas.height);
        return kanvas;
    }

    // Descriptor + foto wajah yang disiapkan SEBELUM kedip,
    // sehingga setelah kedip terdeteksi data bisa langsung dikirim
    async function siapkanBekal() {
        const kanvas = ambilBingkai();
        const d = await faceapi.detectSingleFace(kanvas, opsiAkurat).withFaceLandmarks().withFaceDescriptor();
        if (!d) return null;
        catatWaktu('descriptor disiapkan');
        return { descriptor: Array.from(d.descriptor), foto: kanvas.toDataURL('image/jpeg', 0.8) };
    }

    async function deteksiKedip() {
        berjalan = true;

        const riwayat = [];      // EAR mata terbuka saat kepala diam (patokan "normal")
        let terpejam = false;
        let terpejamSejak = 0;
        let hilang = 0;          // jumlah bingkai berturut-turut tanpa wajah
        let hidungSebelum = null;
        let berkedip = false;
        let bekal = null;        // descriptor + foto yang sudah disiapkan
        let opsiLoop = opsiKedip;
        const mulaiPada = Date.now();

        petunjuk('Lihat ke kamera', 'Tahan sebentar');

        while (berjalan) {
            if (video.readyState < 2) {
                await tidur(30);
                continue;
            }

            const awal = performance.now();
            const d = await faceapi.detectSingleFace(video, opsiLoop).withFaceLandmarks();
            const ms = Math.round(performance.now() - awal);

            if (!berjalan) return; // kamera dihentikan dari luar

            // ---------- Wajah tidak terdeteksi ----------
            if (!d) {
                hilang += 1;

                // Detektor 224 tidak menemukan wajah -> pakai 320 (wajah kecil / jauh)
                if (hilang >= 3) opsiLoop = opsiCepat;

                // Baru dianggap hilang setelah ±6 bingkai berturut-turut,
                // supaya wajah yang hilang sesaat saat mata terpejam tidak mereset kedip
                if (hilang >= 6) {
                    aturOval(false);
                    tandai('wajah', 'tunggu');
                    petunjuk('Arahkan wajah ke dalam bingkai', 'Pastikan wajah terlihat jelas dan cahaya cukup');
                    riwayat.length = 0;
                    terpejam = false;
                    hidungSebelum = null;
                    bekal = null; // keamanan: wajah hilang -> descriptor lama dibuang
                }

                await tidur(30);
                continue;
            }

            hilang = 0;
            aturOval(true);
            tandai('wajah', 'ok');
            catatWaktu('wajah terdeteksi');

            // ---------- Hitung EAR ----------
            const lm = d.landmarks;
            const mataKiri = lm.getLeftEye();
            const mataKanan = lm.getRightEye();
            const ear = (rasioMata(mataKiri) + rasioMata(mataKanan)) / 2;

            // ---------- Hitung gerak kepala ----------
            // Pergeseran ujung hidung (titik 30) dibanding bingkai sebelumnya,
            // dibagi jarak antar-mata agar tidak bergantung jauh-dekatnya wajah
            const jarakMata = titik(tengah(mataKiri), tengah(mataKanan));
            const hidung = lm.positions[30];
            const gerak = hidungSebelum ? titik(hidung, hidungSebelum) / jarakMata : 0;
            hidungSebelum = hidung;

            const dasar = riwayat.length ? median(riwayat) : ear;
            const rasio = ear / dasar;
            const sudahKalibrasi = riwayat.length >= BINGKAI_KALIBRASI;

            // ---------- Siapkan descriptor saat kalibrasi (mata terbuka, kepala diam) ----------
            // Dilakukan sekali, sebelum pegawai diminta berkedip, jadi tidak mengganggu deteksi kedip
            if (!bekal && !terpejam && gerak <= BATAS_GERAK && riwayat.length >= 2) {
                bekal = await siapkanBekal();
                hidungSebelum = null; // ada jeda karena proses di atas -> gerak bingkai berikut tidak dihitung
                continue;
            }

            // ---------- Logika kedip ----------
            if (gerak > BATAS_GERAK) {
                // Bingkai goyang dilewati saja; kedipan yang sedang berlangsung TIDAK dibatalkan
            } else if (!terpejam) {
                if (sudahKalibrasi && bekal && rasio < BATAS_PEJAM) {
                    // EAR turun cukup jauh -> mata terpejam
                    terpejam = true;
                    terpejamSejak = Date.now();
                } else if (!sudahKalibrasi || rasio > 0.95) {
                    // Hanya bingkai mata terbuka yang menjadi patokan "normal"
                    riwayat.push(ear);
                    if (riwayat.length > 20) riwayat.shift();
                    if (riwayat.length === BINGKAI_KALIBRASI) catatWaktu('kalibrasi selesai');
                }
            } else if (rasio > BATAS_BUKA) {
                // Terpejam lalu terbuka lagi = satu kedipan
                berkedip = true;
                break;
            } else if (Date.now() - terpejamSejak > MAKS_TERPEJAM_MS) {
                // Terpejam terlalu lama (misalnya menunduk), mulai ulang
                terpejam = false;
            }

            // ---------- Petunjuk di layar ----------
            if (modeDebug) {
                petunjuk(
                    terpejam ? 'Mata terpejam…' : sudahKalibrasi && bekal ? 'Kedipkan mata' : 'Kalibrasi…',
                    `EAR ${ear.toFixed(3)} · rasio ${rasio.toFixed(2)} · gerak ${gerak.toFixed(3)} · ${ms} ms · ${opsiLoop === opsiKedip ? '224' : '320'}`
                );
            } else if (!sudahKalibrasi || !bekal) {
                petunjuk('Lihat ke kamera', 'Tahan sebentar');
            } else {
                petunjuk(
                    'Kedipkan mata',
                    Date.now() - mulaiPada > 8000 ? 'Pejamkan mata sebentar, lalu buka kembali' : 'untuk verifikasi wajah'
                );
            }
        }

        if (berkedip) {
            berjalan = false;
            tandai('kedip', 'ok');
            catatWaktu('kedip terdeteksi');
            await kirim(bekal);
        }
    }

    // ================= 5. Kirim ke server + GPS =================
    async function kirim(bekal = null, percobaan = 1) {
        try {
            petunjuk('Mencocokkan wajah…', 'Mohon tunggu sebentar');

            // Biasanya lokasi sudah ada karena GPS berjalan sejak awal
            const batasTunggu = Date.now() + 10000;
            while (!posisi && galatGps?.code !== 1 && Date.now() < batasTunggu) await tidur(200);

            if (!posisi) {
                hentikan();
                popupGagal({
                    alasan: galatGps?.code === 1 ? 'Izin lokasi ditolak' : 'Lokasi belum ditemukan',
                    wajah: '-',
                    waktu: '-',
                    keterangan: galatGps?.code === 1 ? 'Izinkan Lokasi di pengaturan situs' : 'Aktifkan GPS',
                });
                return;
            }

            // Cadangan: bila descriptor belum sempat disiapkan, ambil sekarang
            if (!bekal) {
                bekal = await siapkanBekal();
                if (!bekal) {
                    if (percobaan < 3) {
                        await tidur(200);
                        return kirim(null, percobaan + 1);
                    }
                    hentikan();
                    popupGagal({ alasan: 'Wajah tidak terdeteksi saat foto diambil', wajah: '-', waktu: '-', keterangan: 'Hadapkan wajah ke kamera' });
                    return;
                }
            }

            const posisiKirim = posisi;
            hentikan();

            const hasil = await kirimJson(url.urlScan, {
                descriptor: bekal.descriptor,
                lat: posisiKirim.latitude,
                lng: posisiKirim.longitude,
                akurasi: posisiKirim.accuracy,
                foto: bekal.foto,
            });
            catatWaktu('jawaban server');

            console.log(`Waktu presensi: ${totalDetik()} detik`);
            console.table(catatanWaktu);

            tampilkan(hasil);
        } catch (e) {
            console.error(e);
            hentikan();
            popupGagal({ alasan: 'Terjadi kesalahan di perangkat', wajah: '-', waktu: '-', keterangan: String(e?.message ?? e).slice(0, 60) });
        }
    }

    async function kirimJson(alamat, isi = {}) {
        const pengendali = new AbortController();
        const pewaktu = setTimeout(() => pengendali.abort(), 20000);

        try {
            const r = await fetch(alamat, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(isi),
                signal: pengendali.signal,
            });

            if (r.status === 429) return { hasil: 'gagal', alasan: 'Terlalu banyak percobaan', keterangan: 'Tunggu 1 menit' };
            if (r.status === 419) return { hasil: 'gagal', alasan: 'Halaman kedaluwarsa', keterangan: 'Muat ulang halaman' };
            if (r.status >= 500) return { hasil: 'gagal', alasan: 'Terjadi kesalahan di server', keterangan: `Kode ${r.status}` };

            const data = await r.json();
            return r.ok ? data : { hasil: 'gagal', alasan: data.message ?? 'Terjadi kesalahan', keterangan: 'Coba lagi' };
        } catch (e) {
            console.error(e);
            return e.name === 'AbortError'
                ? { hasil: 'gagal', alasan: 'Server terlalu lama merespons', keterangan: 'Periksa koneksi, lalu coba lagi' }
                : { hasil: 'gagal', alasan: 'Tidak terhubung ke server', keterangan: 'Periksa koneksi internet' };
        } finally {
            clearTimeout(pewaktu);
        }
    }

    // ================= 6. Popup =================
    const IKON = {
        centang: '<path d="M20 6 9 17l-5-5"/>',
        jam: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        silang: '<path d="M18 6 6 18M6 6l12 12"/>',
        info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    };
    const WARNA = {
        baik: 'bg-blue-50 text-blue-800',
        peringatan: 'bg-orange-100 text-orange-800',
        gagal: 'bg-red-100 text-red-800',
        info: 'bg-gray-100 text-gray-700',
    };

    const kepala = (ikon, warna, judul, sub) => `
        <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full ${WARNA[warna]}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-9 w-9">${IKON[ikon]}</svg>
        </div>
        <h2 class="mt-4 text-center text-2xl font-extrabold">${esc(judul)}</h2>
        <p class="mt-1 text-center text-gray-600">${esc(sub)}</p>`;
    const baris = (label, nilaiHtml) => `
        <div class="flex items-center justify-between gap-4 border-b border-garis py-3 last:border-0">
            <span class="text-gray-500">${esc(label)}</span>
            <span class="text-right font-bold tabular-nums">${nilaiHtml}</span>
        </div>`;
    const tabel = (isi) => `<div class="mt-5 rounded-2xl bg-krem px-5">${isi}</div>`;
    const lencana = (teks, warna) => `<span class="rounded-full px-3 py-1 text-sm font-semibold ${WARNA[warna]}">${esc(teks)}</span>`;
    const teksJarak = (d) => (d.jarak == null ? '-' : `${d.jarak} / ${d.radius} m`);
    const tombol = (id, teks, utama = false) =>
        `<button type="button" id="${id}" class="w-full rounded-2xl py-3.5 font-bold disabled:opacity-50 ${utama ? 'bg-merah text-white hover:bg-merah-tua' : 'border border-garis bg-white hover:bg-gray-50'}">${esc(teks)}</button>`;
    const tautan = (href, teks, utama = false) =>
        `<a href="${esc(href)}" class="block w-full rounded-2xl py-3.5 text-center font-bold ${utama ? 'bg-merah text-white hover:bg-merah-tua' : 'border border-garis bg-white hover:bg-gray-50'}">${esc(teks)}</a>`;

    function bukaPopup(html) {
        isiPopup.innerHTML = html;
        if (!popup.open) popup.showModal();
    }

    function tutupPopup() {
        if (popup.open) popup.close();
    }

    function tampilkan(d) {
        if (d.hasil === 'berhasil') return popupBerhasil(d);
        if (d.hasil === 'konfirmasi_pulang') return popupKonfirmasi(d);
        if (d.hasil === 'info') return popupInfo(d);
        return popupGagal(d);
    }

    function masuk() {
        popup.querySelectorAll('button').forEach((b) => (b.disabled = true));
        $('form-masuk').submit();
    }

    // Hasil berhasil + hitung mundur 5 detik
    function popupBerhasil(d) {
        // Lewat batas = merah, terlambat / pulang cepat = oranye, tepat waktu = biru
        const warna = d.lewat_batas ? 'gagal' : d.peringatan ? 'peringatan' : 'baik';

        bukaPopup(
            kepala(d.peringatan ? 'jam' : 'centang', warna, d.judul, d.nama) +
            tabel(
                baris('Presensi', esc(d.jenis)) +
                baris('Waktu', esc(d.waktu)) +
                baris('Status', lencana(d.status, warna)) +
                baris('Keterangan', esc(d.keterangan)) +
                baris('Jarak', esc(teksJarak(d))) +
                (modeDebug ? baris('Waktu proses', `${esc(totalDetik())} detik`) : '')
            ) +
            (d.catatan ? `<p class="mt-3 rounded-2xl bg-red-50 p-3 text-center text-sm text-red-800">${esc(d.catatan)}</p>` : '') + `
            <div class="mt-5 flex justify-between text-sm">
                <span class="text-gray-600">Masuk dashboard otomatis</span>
                <span class="font-bold"><span id="sisa-detik">5</span> detik</span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-200">
                <div id="bar-hitung" class="h-full w-full bg-merah transition-[width] duration-1000 ease-linear"></div>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-3">
                ${tombol('tombol-bukan-saya', 'Bukan saya')}${tombol('tombol-masuk', 'Masuk sekarang', true)}
            </div>`
        );

        let sisa = 5;
        const pewaktu = setInterval(() => {
            sisa -= 1;
            $('sisa-detik').textContent = sisa;
            $('bar-hitung').style.width = `${sisa * 20}%`;
            if (sisa <= 0) {
                clearInterval(pewaktu);
                masuk();
            }
        }, 1000);

        $('tombol-masuk').onclick = () => {
            clearInterval(pewaktu);
            masuk();
        };

        $('tombol-bukan-saya').onclick = async () => {
            clearInterval(pewaktu);
            popup.querySelectorAll('button').forEach((b) => (b.disabled = true));
            const r = await kirimJson(url.urlBukanSaya);
            tutupPopup();

            if (r.hasil === 'gagal') {
                siapUlang(r.alasan, r.keterangan);
                return;
            }
            mulai(); // langsung scan ulang otomatis
        };
    }

    // Konfirmasi pulang lebih awal
    function popupKonfirmasi(d) {
        bukaPopup(
            kepala('jam', 'peringatan', 'Pulang lebih awal?', `${d.nama} · wajah dikenali`) +
            tabel(
                baris('Waktu', esc(d.waktu)) +
                baris('Jadwal pulang', esc(d.jadwal_pulang)) +
                baris('Lebih awal', esc(d.lebih_awal)) +
                baris('Jarak', esc(teksJarak(d)))
            ) + `
            <div class="mt-5 grid grid-cols-2 gap-3">
                ${tombol('tombol-batal-pulang', 'Batal')}${tombol('tombol-ya-pulang', 'Ya, pulang', true)}
            </div>`
        );

        $('tombol-batal-pulang').onclick = async () => {
            popup.querySelectorAll('button').forEach((b) => (b.disabled = true));
            await kirimJson(url.urlBatalPulang);
            tutupPopup();
            siapUlang('Presensi pulang dibatalkan', 'Tekan Coba lagi jika ingin presensi lagi');
        };

        $('tombol-ya-pulang').onclick = async () => {
            popup.querySelectorAll('button').forEach((b) => (b.disabled = true));
            tampilkan(await kirimJson(url.urlKonfirmasi));
        };
    }

    // Gagal: wajah tidak dikenali / di luar radius / akurasi buruk
    function popupGagal(d) {
        bukaPopup(
            kepala('silang', 'gagal', 'Presensi Belum Tercatat', d.alasan) +
            tabel(
                baris('Wajah', esc(d.wajah ?? '-')) +
                baris('Waktu', esc(d.waktu ?? '-')) +
                baris('Status', lencana('Gagal', 'gagal')) +
                baris('Keterangan', esc(d.keterangan ?? '-')) +
                baris('Jarak', esc(teksJarak(d)))
            ) + `
            <div class="mt-5 space-y-3">
                ${tombol('tombol-coba', 'Coba lagi', true)}${tautan(url.urlLogin, 'Login manual')}
            </div>`
        );
        $('tombol-coba').onclick = () => mulai();
    }

    // Info: tidak perlu presensi / sudah lengkap
    function popupInfo(d) {
        bukaPopup(
            kepala('info', 'info', d.judul, d.nama ?? '') +
            `<p class="mt-4 rounded-2xl bg-krem p-4 text-center text-sm">${esc(d.pesan)}</p>` +
            (d.datang ? tabel(baris('Datang', esc(d.datang)) + baris('Pulang', esc(d.pulang))) : '') + `
            <div class="mt-5 grid grid-cols-2 gap-3">
                ${tautan(url.urlBeranda, 'Halaman awal')}${tautan(url.urlLogin, 'Lihat Dashboard', true)}
            </div>`
        );
    }

    // ================= Mulai langsung saat halaman dibuka =================
    mulai();
}