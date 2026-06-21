<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('price')) {
            $this->merge([
                'price' => preg_replace('/[^\d]/', '', (string) $this->input('price')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'outlet_id' => ['required', 'exists:outlets,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:5120'], // For image upload
            'is_available' => ['required', 'boolean'],
        ];
    }

    public function messages()
    {
        return [
            'outlet_id.required' => 'Outlet wajib diisi.',
            'outlet_id.exists' => 'Outlet yang dipilih tidak valid.',
            'category_id.required' => 'Kategori wajib diisi.',
            'category_id.exists' => 'Kategori yang dipilih tidak valid.',
            'name.required' => 'Nama produk wajib diisi.',
            'name.string' => 'Nama produk harus berupa string.',
            'name.max' => 'Nama produk tidak boleh lebih dari :max karakter.',
            'description.string' => 'Deskripsi harus berupa string.',
            'price.required' => 'Harga wajib diisi.',
            'price.numeric' => 'Harga harus berupa angka.',
            'price.min' => 'Harga tidak boleh kurang dari nol.',
            'image.image' => 'Gambar produk harus berupa gambar.',
            'image.mimes' => 'Gambar produk harus memiliki format: jpeg, png, jpg, gif, svg.',
            'image.max' => 'Ukuran gambar produk tidak boleh lebih dari 5MB.',
            'is_available.required' => 'Ketersediaan produk wajib diisi.',
            'is_available.boolean' => 'Ketersediaan produk harus berupa nilai boolean (true/false).',
        ];
    }
}
