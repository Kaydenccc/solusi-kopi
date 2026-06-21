@extends('layouts.app')

@section('title', 'QR Code Master')

@push('styles')
<style>
    @media print {
        .no-print { display: none !important; }
        .print-area { padding: 0 !important; }
        body { background: white !important; }
    }
    .qr-card {
        max-width: 420px;
        margin: 0 auto;
        border: 2px dashed #666;
        padding: 30px 20px;
        text-align: center;
        background: white;
        border-radius: 8px;
    }
    .qr-card svg { max-width: 300px; width: 100%; height: auto; }
</style>
@endpush

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <h4 class="fw-bold py-3 mb-4 no-print">
            <span class="text-muted fw-light">Konsol / Manajemen Meja /</span> QR Code Master
        </h4>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center no-print">
                <h5 class="mb-0">QR Code Master untuk Semua Meja</h5>
                <div>
                    <a href="{{ route('tables.index') }}" class="btn btn-secondary btn-sm">
                        <i class="ri-arrow-left-line me-1"></i>Kembali
                    </a>
                    <button onclick="window.print()" class="btn btn-primary btn-sm">
                        <i class="ri-printer-line me-1"></i>Cetak
                    </button>
                </div>
            </div>

            <div class="card-body print-area">
                <div class="qr-card">
                    <h3 class="mb-1">Solusi Kopi</h3>
                    <p class="mb-3 text-muted">Scan untuk memesan</p>

                    {!! QrCode::format('svg')->size(300)->margin(1)->generate(url('/')) !!}

                    <h5 class="mt-3 mb-1">Cara Pesan</h5>
                    <ol class="text-start small mt-2" style="display:inline-block;">
                        <li>Scan QR Code dengan HP</li>
                        <li>Masukkan nomor meja Anda</li>
                        <li>Pilih menu & pesan</li>
                    </ol>
                    <hr>
                    <p class="small mb-0">
                        Atau buka: <br>
                        <strong>{{ url('/') }}</strong>
                    </p>
                </div>

                <div class="mt-4 no-print">
                    <div class="alert alert-info">
                        <h6 class="mb-2"><i class="ri-information-line me-1"></i>Info:</h6>
                        <p class="mb-0 small">
                            QR Code ini mengarah ke halaman utama dimana customer akan memasukkan nomor meja sendiri.
                            Cukup 1 QR Code untuk seluruh kedai - print dan tempel di area yang mudah terlihat.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
