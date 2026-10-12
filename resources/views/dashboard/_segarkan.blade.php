@push('script')
<script>
    // SR-F30: data kehadiran diperbarui otomatis setiap 60 detik selama tab sedang dilihat
    setInterval(() => {
        if (!document.hidden) location.reload();
    }, 60000);
</script>
@endpush