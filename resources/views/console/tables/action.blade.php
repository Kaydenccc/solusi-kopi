<div class="d-flex align-items-center gap-2">
    {{-- Tombol Lihat QR --}}
    <a href="{{ route('tables.show', $id) }}" class="btn btn-sm btn-icon btn-text-primary rounded-pill waves-effect"
        data-bs-toggle="tooltip" title="Lihat & Cetak QR Code">
        <i class="ri-qr-code-line ri-20px"></i>
    </a>

    {{-- Tombol Edit --}}
    <a href="{{ route('tables.edit', $id) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect"
        data-bs-toggle="tooltip" title="Edit Meja">
        <i class="ri-edit-box-line ri-20px"></i>
    </a>

    {{-- Dropdown Menu --}}
    <button class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect dropdown-toggle hide-arrow"
        data-bs-toggle="dropdown">
        <i class="ri-more-2-line ri-20px"></i>
    </button>
    <div class="dropdown-menu dropdown-menu-end m-0">
        {{-- Tombol Lihat QR --}}
        <a href="{{ route('tables.show', $id) }}" class="dropdown-item">
            <i class="ri-qr-code-line me-2"></i><span>Lihat QR Code</span>
        </a>
        {{-- Tombol Edit --}}
        <a href="{{ route('tables.edit', $id) }}" class="dropdown-item">
            <i class="ri-edit-box-line me-2"></i><span>Edit</span>
        </a>
        {{-- Tombol Hapus --}}
        <a href="javascript:;" class="dropdown-item delete-record" data-id="{{ $id }}"
            data-url="{{ route('tables.destroy', $id) }}">
            <i class="ri-delete-bin-7-line me-2"></i><span>Hapus</span>
        </a>
    </div>
</div>
