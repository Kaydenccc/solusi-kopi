@extends('layouts.app')

@section('title', 'QR Code Meja ' . $table->table_number)

@push('styles')
<style>
    @media print {
        .no-print { display: none !important; }
        .print-area { padding: 0 !important; }
        body { background: white !important; }
    }
    .qr-card {
        max-width: 400px;
        margin: 0 auto;
        border: 2px dashed #666;
        padding: 30px 20px;
        text-align: center;
        background: white;
    }
    .qr-card img { max-width: 280px; width: 100%; height: auto; }
</style>
@endpush

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <h4 class="fw-bold py-3 mb-4 no-print">
            <span class="text-muted fw-light">Konsol / Manajemen Meja /</span> QR Code Meja {{ $table->table_number }}
        </h4>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center no-print">
                <h5 class="mb-0">QR Code untuk Scan Menu</h5>
                <div>
                    <a href="{{ route('tables.index') }}" class="btn btn-secondary btn-sm">
                        <i class="ri-arrow-left-line me-1"></i>Kembali
                    </a>
                    <button onclick="window.print()" class="btn btn-primary btn-sm">
                        <i class="ri-printer-line me-1"></i>Cetak
                    </button>
                    @if($table->qr_code_url)
                    <a href="{{ asset('storage/' . $table->qr_code_url) }}" download="qr-meja-{{ $table->table_number }}.svg" class="btn btn-success btn-sm">
                        <i class="ri-download-line me-1"></i>Download
                    </a>
                    @endif
                </div>
            </div>

            <div class="card-body print-area">
                <div class="qr-card">
                    <h3 class="mb-1">{{ $table->outlet->name ?? 'Solusi Kopi' }}</h3>
                    <p class="mb-3 text-muted">Scan untuk memesan</p>

                    @if($table->qr_code_url && file_exists(public_path('storage/' . $table->qr_code_url)))
                        <img src="{{ asset('storage/' . $table->qr_code_url) }}" alt="QR Code Meja {{ $table->table_number }}">
                    @else
                        <div class="text-danger py-5">
                            QR Code belum tersedia. Silakan edit meja ini untuk regenerate.
                        </div>
                    @endif

                    <h2 class="mt-3 mb-1">Meja {{ $table->table_number }}</h2>
                    <p class="mb-0 small text-muted">Kapasitas: {{ $table->capacity ?? '-' }} orang</p>
                    <hr>
                    <p class="small mb-0">
                        Atau buka: <br>
                        <strong>{{ url('/order/menu?table_code=' . $table->table_code) }}</strong>
                    </p>
                </div>

                <div class="mt-4 no-print">
                    <div class="alert alert-info">
                        <h6 class="mb-2"><i class="ri-information-line me-1"></i>Cara Pakai:</h6>
                        <ol class="mb-0 small">
                            <li>Klik <strong>Cetak</strong> untuk print QR Code ini, lalu tempel di meja</li>
                            <li>Atau klik <strong>Download</strong> untuk simpan file SVG</li>
                            <li>Customer scan QR Code → langsung diarahkan ke menu pemesanan</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
