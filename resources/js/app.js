import './bootstrap';
import { createIcons, icons } from 'lucide';

// Ikon Lucide: <i data-lucide="nama-ikon"></i>
createIcons({ icons });

// ---------- Sidebar di layar kecil ----------
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay-sidebar');

document.querySelectorAll('[data-buka-sidebar]').forEach((tombol) =>
    tombol.addEventListener('click', () => {
        sidebar?.classList.remove('-translate-x-full');
        overlay?.classList.remove('hidden');
    })
);

document.querySelectorAll('[data-tutup-sidebar]').forEach((tombol) =>
    tombol.addEventListener('click', () => {
        sidebar?.classList.add('-translate-x-full');
        overlay?.classList.add('hidden');
    })
);

// ---------- Jam mengikuti waktu server (WIB) ----------
const jamServer = document.querySelector('[data-jam-server]');

if (jamServer) {
    const selisih = Number(jamServer.dataset.jamServer) - Date.now();
    const zona = { timeZone: 'Asia/Jakarta' };

    const fmtPanjang = new Intl.DateTimeFormat('id-ID', {
        ...zona, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    });
    const fmtPendek = new Intl.DateTimeFormat('id-ID', {
        ...zona, weekday: 'short', day: 'numeric', month: 'short', year: 'numeric',
    });
    const fmtJam = new Intl.DateTimeFormat('id-ID', {
        ...zona, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
    });

    const perbarui = () => {
        const waktu = new Date(Date.now() + selisih);
        document.querySelectorAll('[data-tanggal-panjang]').forEach((el) => (el.textContent = fmtPanjang.format(waktu).toUpperCase()));
        document.querySelectorAll('[data-tanggal-pendek]').forEach((el) => (el.textContent = fmtPendek.format(waktu).toUpperCase()));
        document.querySelectorAll('[data-jam]').forEach((el) => (el.textContent = fmtJam.format(waktu).replaceAll('.', ':')));
    };

    perbarui();
    setInterval(perbarui, 1000);
}

// ---------- Tombol lihat/sembunyikan kata sandi ----------
document.querySelectorAll('[data-toggle-sandi]').forEach((tombol) =>
    tombol.addEventListener('click', () => {
        const input = document.getElementById(tombol.dataset.toggleSandi);
        input.type = input.type === 'password' ? 'text' : 'password';
    })
);