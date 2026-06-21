@extends('layouts.app')

@section('title', 'Edit Produk')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Konsol / Manajemen Produk /</span> Edit</h4>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Form Edit Produk</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('products.update', $product->id) }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="outlet_id" value="{{1}}">
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Kategori</label>
                        <select class="form-select @error('category_id') is-invalid @enderror" id="category_id"
                            name="category_id" required>
                            <option value="">Pilih Kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}"
                                    {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Produk</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                            name="name" value="{{ old('name', $product->name) }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description"
                            name="description" rows="3">{{ old('description', $product->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Harga</label>
                        <input type="number" class="form-control @error('price') is-invalid @enderror" id="price"
                            name="price" value="{{ old('price', (int) $product->price) }}" required min="0">
                        @error('price')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="image" class="form-label">Gambar Saat Ini</label>
                        @if ($product->image)
                            <div class="mb-2">
                                <img src="{{ asset($product->image) }}" alt="{{ $product->name }}"
                                    class="img-thumbnail" width="100">
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image"
                                    value="1">
                                <label class="form-check-label" for="remove_image">Hapus Gambar</label>
                            </div>
                        @else
                            <p>Tidak ada gambar saat ini.</p>
                        @endif
                        <label for="new_image" class="form-label">Upload Gambar Baru (Opsional)</label>
                        <input class="form-control @error('image') is-invalid @enderror" type="file" id="new_image"
                            name="image">
                        @error('image')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="is_available" class="form-label">Ketersediaan</label>
                        <select class="form-select @error('is_available') is-invalid @enderror" id="is_available" name="is_available"
                            required>
                            <option value="1" {{ old('is_available', $product->is_available) == '1' ? 'selected' : '' }}>Tersedia</option>
                            <option value="0" {{ old('is_available', $product->is_available) == '0' ? 'selected' : '' }}>Tidak Tersedia
                            </option>
                        </select>
                        @error('is_available')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <a href="{{ route('products.index') }}" class="btn btn-secondary me-2">Batal</a>
                    <button type="submit" class="btn btn-primary">Update</button>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Optional: Image preview before upload
            $('#new_image').change(function(){
                const file = this.files[0];
                if (file){
                    let reader = new FileReader();
                    reader.onload = function(event){
                        $('#previewImage').attr('src', event.target.result);
                        $('#previewImage').show();
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
@endpush
