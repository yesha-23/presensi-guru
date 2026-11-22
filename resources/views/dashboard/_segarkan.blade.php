@push('script')
<script>
    // SR-F30: data kehadiran diperbarui otomatis setiap 60 detik selama tab sedang dilihat.
    // Penyegaran ditunda saat pengguna sedang mengetik (mis. di kotak cari) agar ketikan tidak terputus;
    // kata pencarian disimpan oleh rekap/_cari sehingga tetap berlaku setelah halaman dimuat ulang.
    setInterval(() => {
        const aktif = document.activeElement;
        const sedangMengetik = aktif && ['INPUT', 'TEXTAREA', 'SELECT'].includes(aktif.tagName);
        if (!document.hidden && !sedangMengetik) location.reload();
    }, 60000);
</script>
@endpush