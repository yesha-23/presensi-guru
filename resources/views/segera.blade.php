@extends('layouts.app')

@section('konten')
<div class="flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">{{ $judul }}</h1>
        <p class="text-sm text-gray-500">Halaman ini dibangun pada tahap berikutnya.</p>
    </div>
    <div class="hidden lg:block">@include('partials.lonceng')</div>
</div>

<div class="mt-6 rounded-2xl border border-dashed border-garis bg-white p-10 text-center text-gray-500">
    Belum tersedia
</div>
@endsection