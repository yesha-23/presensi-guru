import * as faceapi from '@vladmandic/face-api';

const akar = document.getElementById('pendaftaran-wajah');
if (akar) {
    jalankan(akar);
}

async function jalankan(akar) {
    const video = akar.querySelector('video');
    const tirai = document.getElementById('tirai-kamera');
    const oval = document.getElementById('oval');
    const label = document.getElementById('label-foto');
    const statusKamera = document.getElementById('status-kamera');
    const cekSetuju = document.getElementById('cek-setuju');
    const tombolAmbil = document.getElementById('tombol-ambil');
    const tombolUlangi = document.getElementById('tombol-ulangi');
    const tombolSimpan = document.getElementById('tombol-simpan');
    const kotakPesan = document.getElementById('pesan-wajah');
    const slot = [...akar.querySelectorAll('[data-slot]')];

    const ARAHAN = [
        'Foto 1: hadapkan wajah ke depan',
        'Foto 2: hadapkan wajah sedikit ke kiri',
        'Foto 3: hadapkan wajah sedikit ke kanan',
    ];
    const opsiDeteksi = new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });
    const hasil = [null, null, null]; // { descriptor: number[128], gambar: dataURL }

    let modelSiap = false;
    let kameraNyala = false;
    let wajahTerlihat = false;
    let sibuk = false;

    // ---------- Pesan ----------
    function tampil(pesan, galat = false) {
        kotakPesan.textContent = pesan;
        kotakPesan.classList.toggle('hidden', !pesan);
        kotakPesan.className = `rounded-xl px-4 py-3 text-sm ${pesan ? '' : 'hidden'} ${galat ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-900'}`;
    }

    const indeksBerikut = () => hasil.findIndex((h) => h === null);
    const jumlahFoto = () => hasil.filter(Boolean).length;

    // ---------- Tampilan ----------
    function perbaruiTampilan() {
        const berikut = indeksBerikut();

        label.textContent = berikut === -1 ? 'Ketiga foto sudah diambil' : ARAHAN[berikut];
        tirai.classList.toggle('hidden', kameraNyala);

        slot.forEach((el, i) => {
            const img = el.querySelector('img');
            const kosong = el.querySelector('[data-kosong]');
            const status = el.querySelector('[data-status]');

            if (hasil[i]) {
                img.src = hasil[i].gambar;
                img.classList.remove('hidden');
                kosong.classList.add('hidden');
                status.textContent = 'Diambil';
                status.className = 'text-xs font-semibold text-blue-800';
            } else {
                img.classList.add('hidden');
                kosong.classList.remove('hidden');
                status.textContent = i === berikut ? 'Menunggu' : 'Belum';
                status.className = `text-xs font-semibold ${i === berikut ? 'text-merah' : 'text-gray-400'}`;
            }
        });

        tombolAmbil.textContent = berikut === -1 ? 'Foto lengkap' : `Ambil foto ${berikut + 1}`;
        tombolAmbil.disabled = !kameraNyala || !wajahTerlihat || berikut === -1 || sibuk;
        tombolUlangi.disabled = jumlahFoto() === 0 || sibuk;
        tombolSimpan.textContent = `Simpan data wajah (${jumlahFoto()} dari 3 foto)`;
        tombolSimpan.disabled = jumlahFoto() < 3 || !cekSetuju.checked || sibuk;
    }

    // ---------- 1. Muat model ----------
    tampil('Memuat model pengenalan wajah…');
    try {
        try {
            await faceapi.tf.setBackend('webgl');
        } catch {
            // jika WebGL tidak tersedia, face-api memakai backend bawaan
        }
        await faceapi.tf.ready();

        const urlModel = akar.dataset.urlModel;
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(urlModel),
            faceapi.nets.faceLandmark68Net.loadFromUri(urlModel),
            faceapi.nets.faceRecognitionNet.loadFromUri(urlModel),
        ]);
        modelSiap = true;
        tampil('');
    } catch (e) {
        console.error(e);
        tampil('Model pengenalan wajah gagal dimuat. Pastikan folder public/models sudah ada.', true);
        return;
    }

    // ---------- 2. Persetujuan biometrik -> kamera ----------
    async function nyalakanKamera() {
        if (!navigator.mediaDevices?.getUserMedia) {
            tampil('Browser tidak mengizinkan kamera. Buka lewat https:// atau localhost.', true);
            cekSetuju.checked = false;
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false,
            });
            video.srcObject = stream;
            await video.play();
            kameraNyala = true;
            tampil('');
            perbaruiTampilan();
            pantauWajah();
        } catch (e) {
            console.error(e);
            cekSetuju.checked = false;
            tampil('Kamera tidak dapat diakses. Izinkan akses kamera di browser, lalu centang persetujuan lagi.', true);
            perbaruiTampilan();
        }
    }

    function matikanKamera() {
        video.srcObject?.getTracks().forEach((track) => track.stop());
        video.srcObject = null;
        kameraNyala = false;
        wajahTerlihat = false;
        perbaruiTampilan();
    }

    cekSetuju.addEventListener('change', () => (cekSetuju.checked ? nyalakanKamera() : matikanKamera()));

    // ---------- Pantau wajah di kamera (panduan langsung) ----------
    async function pantauWajah() {
        if (!kameraNyala) return;

        if (!sibuk && modelSiap && video.readyState >= 2) {
            const deteksi = await faceapi.detectAllFaces(video, opsiDeteksi);
            wajahTerlihat = deteksi.length === 1;

            statusKamera.textContent =
                deteksi.length === 0 ? 'Wajah belum terdeteksi'
                    : deteksi.length > 1 ? 'Terdeteksi lebih dari satu wajah'
                        : 'Wajah terdeteksi';

            oval.classList.toggle('border-kuning', !wajahTerlihat);
            oval.classList.toggle('border-emerald-400', wajahTerlihat);
            perbaruiTampilan();
        }

        setTimeout(pantauWajah, 400);
    }

    // ---------- 3. Ambil foto -> wajah terdeteksi? ----------
    tombolAmbil.addEventListener('click', async () => {
        const i = indeksBerikut();
        if (i === -1) return;

        sibuk = true;
        perbaruiTampilan();
        tampil('Memproses foto…');

        // Bekukan satu bingkai video ke kanvas (lebar 480 px)
        const kanvas = document.createElement('canvas');
        kanvas.width = 480;
        kanvas.height = Math.round((video.videoHeight * 480) / video.videoWidth);
        kanvas.getContext('2d').drawImage(video, 0, 0, kanvas.width, kanvas.height);

        const deteksi = await faceapi
            .detectAllFaces(kanvas, opsiDeteksi)
            .withFaceLandmarks()
            .withFaceDescriptors();

        sibuk = false;

        if (deteksi.length !== 1) {
            tampil(deteksi.length ? 'Pastikan hanya satu wajah di depan kamera.' : 'Wajah tidak terdeteksi. Ulangi foto.', true);
            perbaruiTampilan();
            return;
        }

        hasil[i] = {
            descriptor: Array.from(deteksi[0].descriptor),
            gambar: kanvas.toDataURL('image/jpeg', 0.85),
        };
        tampil('');
        perbaruiTampilan();
    });

    // Ulangi foto terakhir yang diambil
    tombolUlangi.addEventListener('click', () => {
        for (let i = 2; i >= 0; i--) {
            if (hasil[i]) {
                hasil[i] = null;
                break;
            }
        }
        tampil('');
        perbaruiTampilan();
    });

    // ---------- 4. Simpan ----------
    tombolSimpan.addEventListener('click', async () => {
        sibuk = true;
        perbaruiTampilan();
        tampil('Menyimpan data wajah…');

        try {
            const respons = await fetch(akar.dataset.urlSimpan, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ persetujuan: cekSetuju.checked ? 1 : 0, foto: hasil }),
            });
            const data = await respons.json();

            if (!respons.ok) {
                sibuk = false;
                tampil(data.errors ? Object.values(data.errors).flat()[0] : (data.message ?? 'Terjadi kesalahan.'), true);
                perbaruiTampilan();
                return;
            }

            matikanKamera();
            window.location.href = data.redirect;
        } catch (e) {
            console.error(e);
            sibuk = false;
            tampil('Gagal terhubung ke server. Coba lagi.', true);
            perbaruiTampilan();
        }
    });

    window.addEventListener('beforeunload', matikanKamera);
    perbaruiTampilan();
}