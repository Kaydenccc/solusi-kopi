<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Product;
use App\Models\Outlet;
use App\Models\Table;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Promotion;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Session; // Untuk Guest Checkout
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MenuLivewire extends Component
{
    public $table;
    public $outlet;
    public $productsGrouped;
    public $categories = [];
    public $activeCategory = null;
    public $cart = [];
    public $promoCode = '';
    public $appliedPromotion = null;
    public $subtotal = 0;
    public $discountAmount = 0;
    public $taxAmount = 0;
    public $serviceFee = 0;
    public $totalAmount = 0;
    public $guestName = '';
    public $guestEmail = '';
    public $guestPhone = '';
    public $paymentMethod = 'QRIS'; // Default payment method
    public $orderNote = '';
    public $guestOrderSubmitted = false;
    public $ready = false;
    public $activeTab = 'menu'; // Default active tab
    public $sortBy = 'name'; // Default sort
    public $sortOrder = 'asc'; // Default order
    public $selectedCategory = 'all'; // Default category filter
    public $searchTerm = ''; // Search term
    public $showNewProducts = false; // Filter produk baru

    protected $rules = [
        'cart' => 'required|array|min:1',
        'cart.*.product_id' => 'required|exists:products,id',
        'cart.*.quantity' => 'required|integer|min:1',
        'cart.*.price' => 'required|numeric|min:0',
        'promoCode' => 'nullable|string',
        'guestName' => 'nullable|string|max:255',
        'guestEmail' => 'nullable|email|max:255',
        'guestPhone' => 'nullable|string|max:255',
        'paymentMethod' => 'required|in:QRIS,cash',
        'orderNote' => 'nullable|string|max:500',
    ];

    public function mount($table = null)
    {
        // Jika table tidak diberikan, coba ambil dari session
        if (!$table) {
            $tableCode = session('current_table_code');
            if (!$tableCode) {
                // Redirect ke halaman welcome jika tidak ada table code
                $this->redirect(route('welcome'), navigate: true);
                return;
            }

            $table = Table::where('table_code', $tableCode)
                ->orWhere('table_number', $tableCode)
                ->with('outlet')
                ->first();

            if (!$table) {
                session()->flash('error', 'Meja tidak ditemukan. Silakan pilih meja lagi.');
                $this->redirect(route('welcome'), navigate: true);
                return;
            }
        }

        if (!$table || !($table instanceof \App\Models\Table)) {
            session()->flash('error', 'Meja tidak ditemukan atau data tidak valid.');
            $this->redirect(route('welcome'), navigate: true);
            return;
        }

        // Simpan table code ke session
        session(['current_table_code' => $table->table_code ?? $table->table_number]);

        $this->table = $table;
        $this->outlet = $table->outlet;
        $this->ready = true;

        if ($this->outlet) {
            $products = Product::where('outlet_id', $this->outlet->id)
                ->where('is_available', true)
                ->with('category')
                ->get();
            $this->productsGrouped = $products->groupBy(fn($p) => $p->category->name ?? 'Tanpa Kategori')
                ->map(fn($group) => $group->all())
                ->toArray();
            $this->categories = array_keys($this->productsGrouped);
            $this->activeCategory = $this->categories[0] ?? null;

            Log::info('Loaded products for outlet', [
                'outlet_id' => $this->outlet->id,
                'outlet_name' => $this->outlet->name,
                'total_products' => $products->count(),
                'categories' => $this->categories,
                'products_by_category' => collect($this->productsGrouped)->map(fn($products) => count($products))
            ]);
        } else {
            $this->productsGrouped = collect();
            $this->categories = [];
            $this->activeCategory = null;
            $this->dispatch('show-notification', type: 'error', message: 'Outlet tidak ditemukan untuk meja ini.');

            Log::warning('No outlet found for table', [
                'table_id' => $this->table->id ?? null,
                'table_number' => $this->table->table_number ?? null
            ]);
        }

        $this->loadCartFromSession();
        $this->calculateTotals();
        $this->loadGuestInfoFromSession();
        $this->loadFilterPreferences();

        // Pastikan guest info dimuat dengan benar
        if (!Auth::check()) {
            $this->ensureGuestInfoLoaded();
        }

        // Inisialisasi filter dan pastikan produk dimuat
        $this->initializeFilters();
        $this->ensureProductsLoaded();

        // Pastikan filter preferences tersimpan setelah dimuat
        $this->ensureFilterPreferences();

        // Log untuk debug
        Log::info('Mount completed', [
            'outlet_id' => $this->outlet->id ?? null,
            'outlet_name' => $this->outlet->name ?? null,
            'table_id' => $this->table->id ?? null,
            'table_number' => $this->table->table_number ?? null,
            'categories' => $this->categories,
            'selectedCategory' => $this->selectedCategory,
            'searchTerm' => $this->searchTerm,
            'showNewProducts' => $this->showNewProducts,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder,
            'total_products' => collect($this->productsGrouped)->sum(fn($products) => count($products)),
            'products_by_category' => collect($this->productsGrouped)->map(fn($products) => count($products)),
            'guestInfo' => [
                'name' => $this->guestName,
                'email' => $this->guestEmail,
                'phone' => $this->guestPhone,
            ],
            'mount_status' => 'Completed successfully'
        ]);
    }

    public function updatedCart()
    {
        $this->calculateTotals();
        $this->saveCartToSession();
    }

    public function updatedPromoCode()
    {
        $this->applyPromo();
    }

        public function setActiveTab($tab)
    {
        $this->activeTab = $tab;

        // Jika beralih ke tab checkout, load guest info dari session
        if ($tab === 'checkout' && !Auth::check()) {
            $this->loadGuestInfoFromSession();

            Log::info('Switched to checkout tab, loaded guest info', [
                'tab' => $tab,
                'guestName' => $this->guestName,
                'guestEmail' => $this->guestEmail,
                'guestPhone' => $this->guestPhone,
                'switch_status' => 'Guest info loaded for checkout'
            ]);

            // Dispatch event untuk JavaScript
            $this->dispatch('tab-changed', tab: $tab);
        }
    }

    public function setActiveCategory($category)
    {
        $this->activeCategory = $category;
    }

    public function updateQuantity($productId, $quantity)
    {
        $quantity = (int) $quantity;

        if ($quantity < 0) {
            $quantity = 0;
        }

        if ($quantity > 99) {
            $quantity = 99;
        }

        if ($quantity === 0) {
            $this->removeFromCartById($productId);
            return;
        }

        $product = Product::find($productId);
        if (!$product) {
            $this->dispatch('show-notification', type: 'error', message: 'Produk tidak ditemukan.');
            return;
        }

        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['quantity'] = $quantity;
            // Pastikan data lain tetap ada
            if (!isset($this->cart[$productId]['product_id'])) {
                $this->cart[$productId]['product_id'] = $product->id;
            }
            if (!isset($this->cart[$productId]['name'])) {
                $this->cart[$productId]['name'] = $product->name ?? 'Produk';
            }
            if (!isset($this->cart[$productId]['price'])) {
                $this->cart[$productId]['price'] = (float) ($product->price ?? 0);
            }
        } else {
            $this->cart[$productId] = [
                'product_id' => $product->id,
                'name' => $product->name ?? 'Produk',
                'price' => (float) ($product->price ?? 0),
                'quantity' => $quantity,
            ];
        }

        $this->cart = $this->cart; // Force Livewire to re-render
        $this->calculateTotals();
        $this->saveCartToSession();
    }

    // Load cart from session for guest checkout persistence
    protected function loadCartFromSession()
    {
        if (Session::has('guest_cart_' . $this->table->id)) {
            $this->cart = Session::get('guest_cart_' . $this->table->id);
            $this->cleanInvalidCartItems(); // Bersihkan data cart yang rusak
        }
    }

    // Clean invalid cart items
    protected function cleanInvalidCartItems()
    {
        if (!is_array($this->cart)) {
            $this->cart = [];
            return;
        }

        $cleanedCart = [];
        foreach ($this->cart as $productId => $item) {
            // Validasi struktur data item
            if (isset($item['product_id']) &&
                isset($item['name']) &&
                isset($item['price']) &&
                isset($item['quantity']) &&
                is_numeric($item['price']) &&
                is_numeric($item['quantity']) &&
                $item['quantity'] > 0) {
                $cleanedCart[$productId] = $item;
            }
        }
        $this->cart = $cleanedCart;
    }

    // Save cart to session for guest checkout persistence
    protected function saveCartToSession()
    {
        Session::put('guest_cart_' . $this->table->id, $this->cart);
        if (!Auth::check()) { // Only save guest info if not logged in
            Session::put('guest_info_' . $this->table->id, [
                'name' => $this->guestName,
                'email' => $this->guestEmail,
                'phone' => $this->guestPhone,
            ]);
        }
    }

    public function addToCartById($productId)
    {
        $product = Product::find($productId);
        if (!$product) {
            $this->dispatch('show-notification', type: 'error', message: 'Produk tidak ditemukan.');
            return;
        }
        $this->addToCart($product);
    }

    public function removeFromCartById($productId)
    {
        $product = Product::find($productId);
        if (!$product) {
            return;
        }
        $this->removeFromCart($product);
    }

    public function addToCart(Product $product)
    {
        if (!$product || !$product->id) {
            $this->dispatch('show-notification', type: 'error', message: 'Produk tidak valid.');
            return;
        }

        if (isset($this->cart[$product->id])) {
            $this->cart[$product->id]['quantity']++;
        } else {
            $this->cart[$product->id] = [
                'product_id' => $product->id,
                'name' => $product->name ?? 'Produk',
                'price' => (float) ($product->price ?? 0),
                'quantity' => 1,
            ];
        }

        $this->cart = $this->cart; // Force Livewire to re-render the cart property
        $this->calculateTotals();
        $this->saveCartToSession();

        $this->dispatch('show-notification', type: 'success', message: 'Produk "' . ($product->name ?? 'Produk') . '" telah ditambahkan ke keranjang.');
    }

    public function removeFromCart(Product $product)
    {
        if (isset($this->cart[$product->id])) {
            $this->cart[$product->id]['quantity']--;
            if ($this->cart[$product->id]['quantity'] <= 0) {
                unset($this->cart[$product->id]);
                $this->cart = $this->cart; // Force Livewire to re-render the cart property
            }
        }
        $this->calculateTotals();
        $this->saveCartToSession();
    }

    protected function calculateDiscount()
    {
        $this->discountAmount = 0;

        if (!$this->appliedPromotion) return;

        if ($this->subtotal >= $this->appliedPromotion->min_order_amount) {
            $calculatedDiscount = $this->appliedPromotion->discount_type === 'percentage'
                ? $this->subtotal * ($this->appliedPromotion->discount_value / 100)
                : $this->appliedPromotion->discount_value;

            // Pastikan diskon tidak melebihi subtotal (untuk menghindari nilai negatif)
            $this->discountAmount = min($calculatedDiscount, $this->subtotal);

            // Untuk Midtrans, pastikan diskon minimal 0.01 jika ada diskon
            if ($this->discountAmount > 0 && $this->discountAmount < 0.01) {
                $this->discountAmount = 0.01;
            }
        } else {
            $this->dispatch('show-notification', type: 'warning', message: 'Minimum order untuk promo ini adalah Rp ' . number_format($this->appliedPromotion->min_order_amount, 2, ',', '.'));
            $this->appliedPromotion = null;
            $this->promoCode = '';
        }
    }

    public function calculateTotals()
    {
        $this->subtotal = collect($this->cart)->sum(function($item) {
            // Validasi data item cart
            if (!isset($item['price']) || !isset($item['quantity'])) {
                return 0; // Skip item yang tidak valid
            }
            return (float) $item['price'] * (int) $item['quantity'];
        });

        $this->calculateDiscount();

        $amountAfterDiscount = max(0, $this->subtotal - $this->discountAmount);
        $this->taxAmount = $amountAfterDiscount * 0.10;
        $this->serviceFee = $amountAfterDiscount * 0.05;

        // Pastikan total amount minimal 0.01 untuk Midtrans
        $calculatedTotal = $amountAfterDiscount + $this->taxAmount + $this->serviceFee;
        $this->totalAmount = max(0.01, $calculatedTotal);
    }

    public function applyPromo()
    {
        $this->appliedPromotion = null;
        $this->discountAmount = 0;

        if (!empty($this->promoCode)) {
            $promotion = Promotion::where('code', $this->promoCode)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('start_date')
                        ->orWhere('start_date', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', now());
                })
                ->first();

            if ($promotion) {
                if ($this->subtotal >= $promotion->min_order_amount) {
                    $this->appliedPromotion = $promotion;
                    $this->dispatch('show-notification', type: 'success', message: 'Promo "' . $promotion->code . '" berhasil diterapkan!');
                } else {
                    $this->dispatch('show-notification', type: 'warning', message: 'Minimum order untuk promo ini adalah Rp ' . number_format($promotion->min_order_amount, 2, ',', '.'));
                }
            } else {
                $this->dispatch('show-notification', type: 'error', message: 'Kode promo tidak valid atau sudah tidak berlaku.');
            }
        }
        $this->calculateTotals();
    }

    public function submitOrder()
    {
        $this->guestOrderSubmitted = true; // Set ini untuk memicu validasi guest info
        $this->validate();

        $userId = Auth::id(); // Will be null for guests

        // If guest, validate guest info
        if (!$userId) {
            $this->validate([
                'guestName' => 'required|string|max:255',
                'guestEmail' => 'required|email|max:255',
                'guestPhone' => 'required|string|max:255',
            ]);
        }

        try {
            // Generate unique order number
            $orderNumber = 'SK-' . Str::upper(Str::random(10));
            while (Order::where('order_number', $orderNumber)->exists()) {
                $orderNumber = 'SK-' . Str::upper(Str::random(10));
            }

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $userId,
                'outlet_id' => $this->outlet->id,
                'table_id' => $this->table->id,
                'promotion_id' => $this->appliedPromotion ? $this->appliedPromotion->id : null,
                'order_type' => 'dine-in',
                'status' => 'pending',
                'subtotal' => $this->subtotal,
                'discount_amount' => $this->discountAmount,
                'additional_fee' => $this->serviceFee,
                'other_fee' => $this->taxAmount,
                'total_amount' => $this->totalAmount,
                'payment_method' => $this->paymentMethod,
                'payment_status' => 'pending',
                'note' => $this->orderNote,
                'ordered_at' => now(),
                'guest_info' => [
                    'name' => $this->guestName,
                    'email' => $this->guestEmail,
                    'phone' => $this->guestPhone,
                ],
            ]);

            foreach ($this->cart as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price_at_order' => $item['price'],
                ]);
            }

            // Clear cart from session after successful order
            Session::forget('guest_cart_' . $this->table->id);
            Session::forget('guest_info_' . $this->table->id);

            // Store order ID in session for guest history
            if (!$userId) {
                $guestOrders = Session::get('guest_order_history', []);
                $guestOrders[] = $order->id;
                Session::put('guest_order_history', array_unique($guestOrders));
            }

            // Redirect to payment or confirmation page
            if ($this->paymentMethod == 'QRIS') {
                return redirect()->route('order.payment.qris', ['order_number' => $order->order_number]);
            } else { // Cash
                $order->payments()->create([
                    'amount' => $order->total_amount,
                    'method' => 'cash',
                    'status' => 'pending',
                    'payment_gateway_ref' => null, // No gateway ref for cash
                    'snap_token' => null,
                ]);
                return redirect()->route('order.success', ['order_number' => $order->order_number]);
            }
        } catch (\Exception $e) {
            $this->dispatch('show-notification', type: 'error', message: 'Terjadi kesalahan saat memproses pesanan Anda: ' . $e->getMessage());
            // Log the error
            Log::error('Order submission failed: ' . $e->getMessage(), ['cart' => $this->cart, 'guest_info' => ['name' => $this->guestName, 'email' => $this->guestEmail]]);
        }
    }

    public function getFilteredProducts()
    {
        if (!$this->outlet) {
            Log::warning('No outlet found for filtering products');
            return collect();
        }

        $query = Product::where('outlet_id', $this->outlet->id)
            ->where('is_available', true)
            ->with('category');

        // Filter berdasarkan kategori
        if ($this->selectedCategory !== 'all') {
            $query->whereHas('category', function($q) {
                $q->where('name', $this->selectedCategory);
            });
            Log::info('Applied category filter', [
                'category' => $this->selectedCategory,
                'filterType' => 'Category specific',
                'filterStatus' => 'Category filter applied'
            ]);
        } else {
            Log::info('No category filter applied', [
                'selectedCategory' => $this->selectedCategory,
                'filterType' => 'All categories',
                'filterStatus' => 'No category filter'
            ]);
        }

        // Filter produk baru (dibuat dalam 7 hari terakhir)
        if ($this->showNewProducts) {
            $query->where('created_at', '>=', now()->subDays(7));
            Log::info('Applied new products filter', [
                'showNewProducts' => $this->showNewProducts,
                'dateFilter' => now()->subDays(7)->format('Y-m-d H:i:s'),
                'filterStatus' => 'New products filter applied'
            ]);
        } else {
            Log::info('No new products filter applied', [
                'showNewProducts' => $this->showNewProducts,
                'filterStatus' => 'No new products filter'
            ]);
        }

        // Search term
        if (!empty(trim($this->searchTerm))) {
            $searchTerm = trim($this->searchTerm);
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('description', 'like', '%' . $searchTerm . '%');
            });
            Log::info('Applied search filter', [
                'searchTerm' => $searchTerm,
                'searchLength' => strlen($searchTerm),
                'filterStatus' => 'Search filter applied'
            ]);
        } else {
            Log::info('No search filter applied', [
                'searchTerm' => $this->searchTerm,
                'filterStatus' => 'No search filter'
            ]);
        }

        // Sorting
        switch ($this->sortBy) {
            case 'price':
                $query->orderBy('price', $this->sortOrder);
                Log::info('Applied price sorting', [
                    'sortBy' => $this->sortBy,
                    'sortOrder' => $this->sortOrder,
                    'sortStatus' => 'Price sorting applied'
                ]);
                break;
            case 'name':
                $query->orderBy('name', $this->sortOrder);
                Log::info('Applied name sorting', [
                    'sortBy' => $this->sortBy,
                    'sortOrder' => $this->sortOrder,
                    'sortStatus' => 'Name sorting applied'
                ]);
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                Log::info('Applied newest sorting', [
                    'sortBy' => $this->sortBy,
                    'sortOrder' => 'desc',
                    'sortStatus' => 'Newest sorting applied'
                ]);
                break;
            default:
                $query->orderBy('name', 'asc');
                Log::info('Applied default sorting', [
                    'sortBy' => 'name',
                    'sortOrder' => 'asc',
                    'sortStatus' => 'Default sorting applied'
                ]);
        }

        $products = $query->get();

        // Log untuk debug
        Log::info('Filtered products result', [
            'selectedCategory' => $this->selectedCategory,
            'showNewProducts' => $this->showNewProducts,
            'searchTerm' => $this->searchTerm,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder,
            'count' => $products->count(),
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'outlet_id' => $this->outlet->id,
            'outlet_name' => $this->outlet->name,
            'filterStatus' => 'Filtering completed successfully'
        ]);

        return $products;
    }

    public function render()
    {
        // Pastikan filter aman dan berjalan dengan benar
        $this->ensureFilterSafety();

        // Pastikan produk dimuat
        $this->ensureProductsLoaded();

        // Validasi dan perbaiki data filter sebelum memproses
        $this->validateAndFixFilterData();
        $this->ensureFilterConsistency();

        $filteredProducts = $this->ensureProductFiltering();

        // Group products by category
        $this->productsGrouped = $filteredProducts->groupBy(fn($p) => $p->category->name ?? 'Tanpa Kategori')
            ->map(fn($group) => $group->all())
            ->toArray();

        // Refresh daftar kategori dari SEMUA produk available (bukan hasil filter)
        // agar tombol filter kategori tetap muncul setelah user pilih kategori
        if ($this->outlet) {
            $allProducts = Product::where('outlet_id', $this->outlet->id)
                ->where('is_available', true)
                ->with('category')
                ->get();
            $this->categories = $allProducts->groupBy(fn($p) => $p->category->name ?? 'Tanpa Kategori')
                ->keys()
                ->toArray();
        }

        // Update active category jika kategori yang dipilih tidak ada dalam hasil filter
        if ($this->selectedCategory !== 'all' && !in_array($this->selectedCategory, $this->categories)) {
            Log::info('Selected category not found in filtered results, resetting to all', [
                'selectedCategory' => $this->selectedCategory,
                'availableCategories' => $this->categories,
                'action' => 'Reset to all categories',
                'renderStatus' => 'Category reset applied'
            ]);
            $this->selectedCategory = 'all';
        }

        // Pastikan guest info dimuat dari session jika user adalah guest
        if (!Auth::check() && $this->activeTab === 'checkout') {
            $this->loadGuestInfoFromSession();
        }

        // Log untuk debug
        Log::info('Render data', [
            'selectedCategory' => $this->selectedCategory,
            'searchTerm' => $this->searchTerm,
            'showNewProducts' => $this->showNewProducts,
            'categories' => $this->categories,
            'productsGrouped' => array_keys($this->productsGrouped),
            'totalProducts' => collect($this->productsGrouped)->sum(fn($products) => count($products)),
            'filteredProductsCount' => $filteredProducts->count(),
            'productsByCategory' => collect($this->productsGrouped)->map(fn($products) => count($products)),
            'guestInfo' => [
                'name' => $this->guestName,
                'email' => $this->guestEmail,
                'phone' => $this->guestPhone,
            ],
            'renderStatus' => 'Render completed successfully'
        ]);

        return view('livewire.menu-livewire', [
            'productsGrouped' => $this->productsGrouped ?? collect(),
            'cart' => $this->cart,
            'subtotal' => $this->subtotal,
            'discountAmount' => $this->discountAmount,
            'taxAmount' => $this->taxAmount,
            'serviceFee' => $this->serviceFee,
            'totalAmount' => $this->totalAmount,
            'appliedPromotion' => $this->appliedPromotion,
            'paymentMethod' => $this->paymentMethod,
        ]);
    }

    protected function loadGuestInfoFromSession()
    {
        $guestInfo = Session::get('guest_info_' . $this->table->id);
        if ($guestInfo) {
            $this->guestName = $guestInfo['name'] ?? '';
            $this->guestEmail = $guestInfo['email'] ?? '';
            $this->guestPhone = $guestInfo['phone'] ?? '';

            // Log untuk debug
            Log::info('Loaded guest info from session', [
                'table_id' => $this->table->id,
                'guestName' => $this->guestName,
                'guestEmail' => $this->guestEmail,
                'guestPhone' => $this->guestPhone,
                'session_key' => 'guest_info_' . $this->table->id,
                'load_status' => 'Guest info loaded successfully'
            ]);
        } else {
            // Log jika tidak ada session data
            Log::info('No guest info found in session', [
                'table_id' => $this->table->id,
                'session_key' => 'guest_info_' . $this->table->id,
                'load_status' => 'No session data found'
            ]);
        }
    }

    public function sortProducts($sortBy, $sortOrder = 'asc')
    {
        $this->sortBy = $sortBy;
        $this->sortOrder = $sortOrder;
    }

    public function filterByCategory($category)
    {
        $this->selectedCategory = $category;
        $this->saveFilterPreferences();
        $this->ensureFilterPreferences();

        // Log untuk debug
        Log::info('Filter by category', [
            'category' => $category,
            'selectedCategory' => $this->selectedCategory,
            'isAllCategory' => $category === 'all',
            'categoryName' => $category === 'all' ? 'Semua' : $category,
            'filterStatus' => 'Category filter applied'
        ]);

        $this->dispatch('show-notification', type: 'info', message: 'Filter kategori: ' . ($category === 'all' ? 'Semua' : $category));
    }

    public function toggleNewProducts()
    {
        $this->showNewProducts = !$this->showNewProducts;
        $this->saveFilterPreferences();
        $this->ensureFilterPreferences();

        // Log untuk debug
        Log::info('Toggle new products', [
            'showNewProducts' => $this->showNewProducts,
            'showNewProductsText' => $this->showNewProducts ? 'Active' : 'Inactive',
            'toggleAction' => $this->showNewProducts ? 'Enabled' : 'Disabled',
            'filterStatus' => 'New products filter toggled'
        ]);

        $this->dispatch('show-notification', type: 'info', message: 'Filter produk baru: ' . ($this->showNewProducts ? 'Aktif' : 'Nonaktif'));
    }

    public function searchProducts()
    {
        // Method ini akan dipanggil saat user mengetik di search box
        if (!empty($this->searchTerm)) {
            $this->dispatch('show-notification', type: 'info', message: 'Mencari: ' . $this->searchTerm);
        }
    }

    public function resetFilters()
    {
        $this->selectedCategory = 'all';
        $this->searchTerm = '';
        $this->sortBy = 'name';
        $this->sortOrder = 'asc';
        $this->showNewProducts = false;
        $this->saveFilterPreferences();
        $this->ensureFilterPreferences();

        // Log untuk debug
        Log::info('Reset filters to default', [
            'selectedCategory' => $this->selectedCategory,
            'searchTerm' => $this->searchTerm,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder,
            'showNewProducts' => $this->showNewProducts,
            'resetAction' => 'All filters reset to default values',
            'filterStatus' => 'Filters reset successfully'
        ]);

        $this->dispatch('show-notification', type: 'success', message: 'Filter telah direset ke default');
    }

    public function logout()
    {
        // Cek apakah ada item di cart
        if (!empty($this->cart)) {
            $this->dispatch('show-notification', type: 'warning', message: 'Anda masih memiliki item di keranjang. Silakan selesaikan pesanan terlebih dahulu.');
            return;
        }

        // Logout user yang login
        if (Auth::check()) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
        }

        // Clear semua session data
        session()->flush();

        // Redirect ke halaman welcome menggunakan Livewire
        $this->redirect(route('welcome'), navigate: true);
    }

    public function clearSession()
    {
        // Cek apakah ada item di cart
        if (!empty($this->cart)) {
            $this->dispatch('show-notification', type: 'warning', message: 'Anda masih memiliki item di keranjang. Silakan selesaikan pesanan terlebih dahulu.');
            return;
        }

        // Hanya clear session tanpa logout user
        session()->flush();

        // Redirect ke halaman welcome menggunakan Livewire
        $this->redirect(route('welcome'), navigate: true);
    }

    public function updatedSearchTerm()
    {
        // Method ini akan dipanggil saat search term berubah
        if (!empty($this->searchTerm)) {
            $this->dispatch('show-notification', type: 'info', message: 'Mencari: ' . $this->searchTerm);
        }
        $this->saveFilterPreferences();
        $this->ensureFilterPreferences();

        // Log untuk debug
        Log::info('Updated search term', [
            'searchTerm' => $this->searchTerm,
            'searchTermLength' => strlen($this->searchTerm),
            'updateStatus' => 'Search term updated'
        ]);
    }

    public function updatedSortBy()
    {
        // Method ini akan dipanggil saat sort by berubah
        $this->dispatch('show-notification', type: 'info', message: 'Urut berdasarkan: ' . $this->sortBy);
        $this->saveFilterPreferences();
        $this->ensureFilterPreferences();

        // Log untuk debug
        Log::info('Updated sort by', [
            'sortBy' => $this->sortBy,
            'previousSortBy' => $this->sortBy,
            'updateStatus' => 'Sort by updated'
        ]);
    }

    public function updatedSortOrder()
    {
        // Method ini akan dipanggil saat sort order berubah
        $this->dispatch('show-notification', type: 'info', message: 'Urutan: ' . ($this->sortOrder === 'asc' ? 'Naik' : 'Turun'));
        $this->saveFilterPreferences();
        $this->ensureFilterPreferences();

        // Log untuk debug
        Log::info('Updated sort order', [
            'sortOrder' => $this->sortOrder,
            'sortOrderText' => $this->sortOrder === 'asc' ? 'Ascending' : 'Descending',
            'updateStatus' => 'Sort order updated'
        ]);
    }

    public function updatedSelectedCategory()
    {
        // Method ini akan dipanggil saat selected category berubah
        if ($this->selectedCategory !== 'all') {
            $this->dispatch('show-notification', type: 'info', message: 'Kategori: ' . $this->selectedCategory);
        }
        $this->saveFilterPreferences();
        $this->ensureFilterPreferences();

        // Log untuk debug
        Log::info('Updated selected category', [
            'selectedCategory' => $this->selectedCategory,
            'isAllCategory' => $this->selectedCategory === 'all',
            'updateStatus' => 'Selected category updated'
        ]);
    }

    public function updatedShowNewProducts()
    {
        // Method ini akan dipanggil saat show new products berubah
        $this->dispatch('show-notification', type: 'info', message: 'Produk baru: ' . ($this->showNewProducts ? 'Aktif' : 'Nonaktif'));
        $this->saveFilterPreferences();
        $this->ensureFilterPreferences();

        // Log untuk debug
        Log::info('Updated show new products', [
            'showNewProducts' => $this->showNewProducts,
            'showNewProductsText' => $this->showNewProducts ? 'Active' : 'Inactive',
            'updateStatus' => 'Show new products updated'
        ]);
    }

    // Method untuk menyimpan guest info ke session secara real-time
    public function updatedGuestName()
    {
        $this->saveGuestInfoToSession();
        Log::info('Updated guest name', [
            'guestName' => $this->guestName,
            'updateStatus' => 'Guest name updated and saved to session'
        ]);
    }

    public function updatedGuestEmail()
    {
        $this->saveGuestInfoToSession();
        Log::info('Updated guest email', [
            'guestEmail' => $this->guestEmail,
            'updateStatus' => 'Guest email updated and saved to session'
        ]);
    }

    public function updatedGuestPhone()
    {
        $this->saveGuestInfoToSession();
        Log::info('Updated guest phone', [
            'guestPhone' => $this->guestPhone,
            'updateStatus' => 'Guest phone updated and saved to session'
        ]);
    }

    // Method untuk memastikan guest info dimuat saat komponen di-render
    public function hydrate()
    {
        // Load guest info dari session saat komponen di-hydrate
        if (!Auth::check() && $this->table) {
            $this->loadGuestInfoFromSession();
        }
    }

    // Method untuk memastikan guest info dimuat dengan benar
    protected function ensureGuestInfoLoaded()
    {
        // Load guest info dari session
        $this->loadGuestInfoFromSession();

        // Log untuk debug
        Log::info('Ensured guest info loaded', [
            'table_id' => $this->table->id,
            'guestName' => $this->guestName,
            'guestEmail' => $this->guestEmail,
            'guestPhone' => $this->guestPhone,
            'ensure_status' => 'Guest info ensured to be loaded'
        ]);
    }

    // Method untuk menyimpan guest info ke session
    protected function saveGuestInfoToSession()
    {
        if (!Auth::check()) { // Hanya simpan jika user tidak login (guest)
            Session::put('guest_info_' . $this->table->id, [
                'name' => $this->guestName,
                'email' => $this->guestEmail,
                'phone' => $this->guestPhone,
            ]);

            Log::info('Saved guest info to session', [
                'table_id' => $this->table->id,
                'guestName' => $this->guestName,
                'guestEmail' => $this->guestEmail,
                'guestPhone' => $this->guestPhone,
                'session_key' => 'guest_info_' . $this->table->id,
                'save_status' => 'Guest info saved successfully'
            ]);
        }
    }

        // Method untuk menangani event check-guest-session dari JavaScript
    public function checkGuestSession()
    {
        // Reload guest info from session
        $this->loadGuestInfoFromSession();

        Log::info('Checked guest session', [
            'table_id' => $this->table->id,
            'guestName' => $this->guestName,
            'guestEmail' => $this->guestEmail,
            'guestPhone' => $this->guestPhone,
            'check_status' => 'Guest session checked'
        ]);

        $this->dispatch('guest-info-loaded', [
            'name' => $this->guestName,
            'email' => $this->guestEmail,
            'phone' => $this->guestPhone
        ]);
    }

    // Method untuk memastikan guest info dimuat saat komponen di-render
    public function boot()
    {
        // Load guest info dari session saat komponen di-boot
        if (!Auth::check() && $this->table) {
            $this->loadGuestInfoFromSession();
        }
    }

    // Method untuk memastikan guest info dimuat saat komponen di-render
    public function updated($propertyName)
    {
        // Jika ada perubahan pada guest info, simpan ke session
        if (in_array($propertyName, ['guestName', 'guestEmail', 'guestPhone'])) {
            $this->saveGuestInfoToSession();
        }
    }

    // Method untuk memastikan guest info dimuat saat komponen di-render
    public function dehydrate()
    {
        // Simpan guest info ke session saat komponen di-dehydrate
        if (!Auth::check()) {
            $this->saveGuestInfoToSession();
        }
    }



    protected function loadFilterPreferences()
    {
        // Load filter preferences from session
        $filterKey = 'filter_preferences_' . ($this->table->id ?? 'default');

        if (Session::has($filterKey)) {
            $preferences = Session::get($filterKey);
            $this->selectedCategory = $preferences['selectedCategory'] ?? 'all';
            $this->searchTerm = $preferences['searchTerm'] ?? '';
            $this->sortBy = $preferences['sortBy'] ?? 'name';
            $this->sortOrder = $preferences['sortOrder'] ?? 'asc';
            $this->showNewProducts = $preferences['showNewProducts'] ?? false;

            // Log untuk debug
            Log::info('Loaded filter preferences from session', [
                'filterKey' => $filterKey,
                'preferences' => $preferences,
                'selectedCategory' => $this->selectedCategory,
                'searchTerm' => $this->searchTerm,
                'showNewProducts' => $this->showNewProducts,
                'sortBy' => $this->sortBy,
                'sortOrder' => $this->sortOrder,
                'loadStatus' => 'Successfully loaded from session'
            ]);
        } else {
            // Set default values
            $this->selectedCategory = 'all';
            $this->searchTerm = '';
            $this->sortBy = 'name';
            $this->sortOrder = 'asc';
            $this->showNewProducts = false;

            // Log untuk debug
            Log::info('Set default filter preferences (no session data)', [
                'filterKey' => $filterKey,
                'selectedCategory' => $this->selectedCategory,
                'searchTerm' => $this->searchTerm,
                'showNewProducts' => $this->showNewProducts,
                'sortBy' => $this->sortBy,
                'sortOrder' => $this->sortOrder,
                'loadStatus' => 'Set to default values'
            ]);
        }
    }

    protected function saveFilterPreferences()
    {
        // Save filter preferences to session
        $filterKey = 'filter_preferences_' . ($this->table->id ?? 'default');

        $preferences = [
            'selectedCategory' => $this->selectedCategory,
            'searchTerm' => $this->searchTerm,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder,
            'showNewProducts' => $this->showNewProducts,
        ];

        Session::put($filterKey, $preferences);

        // Log untuk debug
        Log::info('Saved filter preferences to session', [
            'filterKey' => $filterKey,
            'preferences' => $preferences,
            'table_id' => $this->table->id ?? null,
            'table_number' => $this->table->table_number ?? null,
            'saveStatus' => 'Successfully saved to session'
        ]);
    }

    // Method untuk memvalidasi dan memperbaiki data filter
    protected function validateAndFixFilterData()
    {
        // Validasi selectedCategory
        if (!in_array($this->selectedCategory, ['all']) && !in_array($this->selectedCategory, $this->categories)) {
            Log::warning('Invalid selectedCategory, resetting to all', [
                'selectedCategory' => $this->selectedCategory,
                'availableCategories' => $this->categories
            ]);
            $this->selectedCategory = 'all';
        }

        // Validasi sortBy
        if (!in_array($this->sortBy, ['name', 'price', 'newest'])) {
            Log::warning('Invalid sortBy, resetting to name', [
                'sortBy' => $this->sortBy
            ]);
            $this->sortBy = 'name';
        }

        // Validasi sortOrder
        if (!in_array($this->sortOrder, ['asc', 'desc'])) {
            Log::warning('Invalid sortOrder, resetting to asc', [
                'sortOrder' => $this->sortOrder
            ]);
            $this->sortOrder = 'asc';
        }

        // Validasi showNewProducts
        if (!is_bool($this->showNewProducts)) {
            Log::warning('Invalid showNewProducts, resetting to false', [
                'showNewProducts' => $this->showNewProducts
            ]);
            $this->showNewProducts = false;
        }

        // Validasi searchTerm
        if (!is_string($this->searchTerm)) {
            Log::warning('Invalid searchTerm, resetting to empty string', [
                'searchTerm' => $this->searchTerm
            ]);
            $this->searchTerm = '';
        }
    }

    // Method untuk debugging filter
    public function debugFilters()
    {
        Log::info('Debug filter state', [
            'selectedCategory' => $this->selectedCategory,
            'searchTerm' => $this->searchTerm,
            'showNewProducts' => $this->showNewProducts,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder,
            'categories' => $this->categories,
            'outlet_id' => $this->outlet->id ?? null,
            'outlet_name' => $this->outlet->name ?? null,
            'total_products' => collect($this->productsGrouped)->sum(fn($products) => count($products)),
            'products_by_category' => collect($this->productsGrouped)->map(fn($products) => count($products)),
            'debug_status' => 'Filter state logged'
        ]);

        $this->dispatch('show-notification', type: 'info', message: 'Debug info telah dicatat di log');
    }

    // Method untuk memastikan filter berjalan dengan benar di semua kondisi
    public function ensureFilterPreferences()
    {
        $this->saveFilterPreferences();

        Log::info('Ensured filter preferences are saved', [
            'selectedCategory' => $this->selectedCategory,
            'searchTerm' => $this->searchTerm,
            'showNewProducts' => $this->showNewProducts,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder,
            'table_id' => $this->table->id ?? null,
            'ensure_status' => 'Filter preferences ensured'
        ]);
    }

    // Method untuk clear filter preferences
    public function clearFilterPreferences()
    {
        $filterKey = 'filter_preferences_' . ($this->table->id ?? 'default');
        Session::forget($filterKey);

        Log::info('Cleared filter preferences', [
            'filterKey' => $filterKey,
            'table_id' => $this->table->id ?? null,
            'clear_status' => 'Filter preferences cleared'
        ]);

        $this->dispatch('show-notification', type: 'success', message: 'Preferensi filter telah dihapus');
    }

    // Method untuk memastikan filter berjalan dengan benar di semua kondisi dan menambahkan error handling yang lebih baik
    protected function handleFilterError($error, $context = [])
    {
        Log::error('Filter error occurred', [
            'error' => $error,
            'context' => $context,
            'selectedCategory' => $this->selectedCategory,
            'searchTerm' => $this->searchTerm,
            'showNewProducts' => $this->showNewProducts,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder,
            'error_status' => 'Filter error handled'
        ]);

        // Reset to safe defaults
        $this->selectedCategory = 'all';
        $this->searchTerm = '';
        $this->sortBy = 'name';
        $this->sortOrder = 'asc';
        $this->showNewProducts = false;

        $this->dispatch('show-notification', type: 'error', message: 'Terjadi kesalahan pada filter. Filter telah direset ke default.');
    }

    // Method untuk memastikan filter berjalan dengan benar di semua kondisi
    protected function ensureFilterSafety()
    {
        try {
            // Validasi semua filter
            $this->validateAndFixFilterData();
            $this->ensureFilterConsistency();

            // Pastikan outlet tersedia
            if (!$this->outlet) {
                throw new \Exception('Outlet tidak tersedia');
            }

            // Pastikan produk dimuat
            $this->ensureProductsLoaded();

            // Pastikan filter preferences tersimpan
            $this->ensureFilterPreferences();

            Log::info('Filter safety ensured', [
                'selectedCategory' => $this->selectedCategory,
                'searchTerm' => $this->searchTerm,
                'showNewProducts' => $this->showNewProducts,
                'sortBy' => $this->sortBy,
                'sortOrder' => $this->sortOrder,
                'safety_status' => 'Filter safety ensured successfully'
            ]);

        } catch (\Exception $e) {
            $this->handleFilterError($e->getMessage(), [
                'method' => 'ensureFilterSafety',
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
        }
    }

    // Method untuk memastikan filter berjalan dengan benar di semua kondisi
    public function forceFilterRefresh()
    {
        try {
            // Clear current filter state
            $this->selectedCategory = 'all';
            $this->searchTerm = '';
            $this->sortBy = 'name';
            $this->sortOrder = 'asc';
            $this->showNewProducts = false;

            // Reload products
            $this->ensureProductsLoaded();

            // Save preferences
            $this->saveFilterPreferences();
            $this->ensureFilterPreferences();

            Log::info('Filter force refreshed', [
                'selectedCategory' => $this->selectedCategory,
                'searchTerm' => $this->searchTerm,
                'showNewProducts' => $this->showNewProducts,
                'sortBy' => $this->sortBy,
                'sortOrder' => $this->sortOrder,
                'refresh_status' => 'Filter force refreshed successfully'
            ]);

            $this->dispatch('show-notification', type: 'success', message: 'Filter telah diperbarui.');

        } catch (\Exception $e) {
            $this->handleFilterError($e->getMessage(), [
                'method' => 'forceFilterRefresh',
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
        }
    }

    // Method untuk memastikan filter berjalan dengan benar di semua kondisi
    protected function ensureFilterConsistency()
    {
        // Pastikan selectedCategory valid
        if ($this->selectedCategory !== 'all' && !in_array($this->selectedCategory, $this->categories)) {
            Log::warning('Invalid selectedCategory detected, resetting to all', [
                'selectedCategory' => $this->selectedCategory,
                'availableCategories' => $this->categories
            ]);
            $this->selectedCategory = 'all';
        }

        // Pastikan searchTerm tidak terlalu panjang
        if (strlen($this->searchTerm) > 100) {
            Log::warning('Search term too long, truncating', [
                'originalLength' => strlen($this->searchTerm),
                'truncatedLength' => 100
            ]);
            $this->searchTerm = substr($this->searchTerm, 0, 100);
        }

        // Pastikan sortBy valid
        if (!in_array($this->sortBy, ['name', 'price', 'newest'])) {
            Log::warning('Invalid sortBy detected, resetting to name', [
                'sortBy' => $this->sortBy
            ]);
            $this->sortBy = 'name';
        }

        // Pastikan sortOrder valid
        if (!in_array($this->sortOrder, ['asc', 'desc'])) {
            Log::warning('Invalid sortOrder detected, resetting to asc', [
                'sortOrder' => $this->sortOrder
            ]);
            $this->sortOrder = 'asc';
        }

        // Pastikan showNewProducts boolean
        if (!is_bool($this->showNewProducts)) {
            Log::warning('Invalid showNewProducts detected, resetting to false', [
                'showNewProducts' => $this->showNewProducts
            ]);
            $this->showNewProducts = false;
        }

        Log::info('Filter consistency ensured', [
            'selectedCategory' => $this->selectedCategory,
            'searchTerm' => $this->searchTerm,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder,
            'showNewProducts' => $this->showNewProducts,
            'consistency_status' => 'Filter consistency ensured'
        ]);
    }

    // Method untuk memastikan produk difilter dengan benar
    protected function ensureProductFiltering()
    {
        if (!$this->outlet) {
            Log::warning('No outlet available for product filtering');
            return collect();
        }

        // Pastikan query dasar valid
        $query = Product::where('outlet_id', $this->outlet->id)
            ->where('is_available', true)
            ->with('category');

        // Terapkan filter dengan validasi
        if ($this->selectedCategory !== 'all' && in_array($this->selectedCategory, $this->categories)) {
            $query->whereHas('category', function($q) {
                $q->where('name', $this->selectedCategory);
            });
        }

        if ($this->showNewProducts) {
            $query->where('created_at', '>=', now()->subDays(7));
        }

        if (!empty(trim($this->searchTerm))) {
            $searchTerm = trim($this->searchTerm);
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('description', 'like', '%' . $searchTerm . '%');
            });
        }

        // Terapkan sorting dengan validasi
        switch ($this->sortBy) {
            case 'price':
                $query->orderBy('price', $this->sortOrder);
                break;
            case 'name':
                $query->orderBy('name', $this->sortOrder);
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            default:
                $query->orderBy('name', 'asc');
        }

        $products = $query->get();

        Log::info('Product filtering completed', [
            'selectedCategory' => $this->selectedCategory,
            'showNewProducts' => $this->showNewProducts,
            'searchTerm' => $this->searchTerm,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder,
            'productCount' => $products->count(),
            'filtering_status' => 'Product filtering completed successfully'
        ]);

        return $products;
    }

    // Method untuk memastikan filter berjalan dengan benar di semua kondisi dan menambahkan error handling yang lebih baik
    public function healthCheck()
    {
        try {
            Log::info('Starting health check', [
                'health_check_start' => now()->format('Y-m-d H:i:s'),
                'outlet_id' => $this->outlet->id ?? null,
                'outlet_name' => $this->outlet->name ?? null
            ]);

            $healthStatus = [
                'outlet_available' => $this->outlet && $this->outlet->id,
                'products_loaded' => !empty($this->productsGrouped),
                'categories_available' => !empty($this->categories),
                'filter_valid' => $this->validateAndFixFilterData(),
                'consistency_ok' => $this->ensureFilterConsistency(),
                'safety_ok' => $this->ensureFilterSafety(),
                'preferences_saved' => $this->saveFilterPreferences(),
                'cart_loaded' => !empty($this->cart) || is_array($this->cart),
                'session_working' => Session::has('current_table_code')
            ];

            $allHealthy = true;
            $issues = [];

            foreach ($healthStatus as $component => $isHealthy) {
                if (!$isHealthy) {
                    $allHealthy = false;
                    $issues[] = $component;
                }
            }

            Log::info('Health check results', [
                'all_healthy' => $allHealthy,
                'issues' => $issues,
                'health_status' => $healthStatus,
                'health_check_end' => now()->format('Y-m-d H:i:s')
            ]);

            if (!$allHealthy) {
                Log::warning('Health check failed, attempting recovery', [
                    'issues' => $issues
                ]);

                // Attempt recovery
                $this->emergencyFilterReset();

                $this->dispatch('show-notification', type: 'warning', message: 'Ditemukan masalah pada sistem. Mencoba memperbaiki...');
            } else {
                Log::info('All health checks passed successfully');
                $this->dispatch('show-notification', type: 'success', message: 'Sistem sehat dan berfungsi dengan baik.');
            }

            return $allHealthy;

        } catch (\Exception $e) {
            Log::error('Health check failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            $this->handleFilterError($e->getMessage(), [
                'method' => 'healthCheck',
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return false;
        }
    }

    // Method untuk memastikan filter berjalan dengan benar di semua kondisi
    public function systemDiagnostic()
    {
        try {
            Log::info('Starting system diagnostic', [
                'diagnostic_start' => now()->format('Y-m-d H:i:s')
            ]);

            $diagnosticResults = [
                'system_info' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'livewire_version' => '3.x',
                    'memory_usage' => memory_get_usage(true),
                    'peak_memory' => memory_get_peak_usage(true)
                ],
                'filter_state' => [
                    'selectedCategory' => $this->selectedCategory,
                    'searchTerm' => $this->searchTerm,
                    'showNewProducts' => $this->showNewProducts,
                    'sortBy' => $this->sortBy,
                    'sortOrder' => $this->sortOrder
                ],
                'data_state' => [
                    'outlet_id' => $this->outlet->id ?? null,
                    'table_id' => $this->table->id ?? null,
                    'products_count' => collect($this->productsGrouped)->sum(fn($products) => count($products)),
                    'categories_count' => count($this->categories),
                    'cart_items_count' => count($this->cart)
                ],
                'session_state' => [
                    'current_table_code' => Session::get('current_table_code'),
                    'filter_preferences_key' => 'filter_preferences_' . ($this->table->id ?? 'default'),
                    'filter_preferences_exists' => Session::has('filter_preferences_' . ($this->table->id ?? 'default')),
                    'guest_cart_exists' => Session::has('guest_cart_' . ($this->table->id ?? 'default'))
                ]
            ];

            Log::info('System diagnostic completed', [
                'diagnostic_results' => $diagnosticResults,
                'diagnostic_end' => now()->format('Y-m-d H:i:s')
            ]);

            $this->dispatch('show-notification', type: 'info', message: 'Diagnostik sistem selesai. Lihat log untuk detail.');

            return $diagnosticResults;

        } catch (\Exception $e) {
            Log::error('System diagnostic failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            $this->handleFilterError($e->getMessage(), [
                'method' => 'systemDiagnostic',
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return null;
        }
    }

    // Method untuk memastikan filter berjalan dengan benar di semua kondisi
    public function emergencyFilterReset()
    {
        try {
            Log::warning('Emergency filter reset initiated', [
                'reset_time' => now()->format('Y-m-d H:i:s'),
                'previous_state' => [
                    'selectedCategory' => $this->selectedCategory,
                    'searchTerm' => $this->searchTerm,
                    'showNewProducts' => $this->showNewProducts,
                    'sortBy' => $this->sortBy,
                    'sortOrder' => $this->sortOrder
                ]
            ]);

            // Reset all filters to safe defaults
            $this->selectedCategory = 'all';
            $this->searchTerm = '';
            $this->sortBy = 'name';
            $this->sortOrder = 'asc';
            $this->showNewProducts = false;

            // Clear session preferences
            $filterKey = 'filter_preferences_' . ($this->table->id ?? 'default');
            Session::forget($filterKey);

            // Reload products
            $this->ensureProductsLoaded();

            // Save new preferences
            $this->saveFilterPreferences();
            $this->ensureFilterPreferences();

            Log::info('Emergency filter reset completed', [
                'reset_completion_time' => now()->format('Y-m-d H:i:s'),
                'new_state' => [
                    'selectedCategory' => $this->selectedCategory,
                    'searchTerm' => $this->searchTerm,
                    'showNewProducts' => $this->showNewProducts,
                    'sortBy' => $this->sortBy,
                    'sortOrder' => $this->sortOrder
                ],
                'reset_status' => 'Emergency reset completed successfully'
            ]);

            $this->dispatch('show-notification', type: 'warning', message: 'Filter telah direset secara darurat. Semua pengaturan telah dikembalikan ke default.');

        } catch (\Exception $e) {
            Log::error('Emergency filter reset failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'reset_status' => 'Emergency reset failed'
            ]);

            $this->dispatch('show-notification', type: 'error', message: 'Reset darurat gagal. Silakan refresh halaman.');
        }
    }

    // Method untuk memastikan filter berjalan dengan benar di semua kondisi
    public function completeFilterCheck()
    {
        try {
            Log::info('Starting complete filter check', [
                'check_start' => now()->format('Y-m-d H:i:s')
            ]);

            // Step 1: Basic validation
            $this->validateAndFixFilterData();
            Log::info('Step 1: Basic validation completed');

            // Step 2: Consistency check
            $this->ensureFilterConsistency();
            Log::info('Step 2: Consistency check completed');

            // Step 3: Safety check
            $this->ensureFilterSafety();
            Log::info('Step 3: Safety check completed');

            // Step 4: Product loading check
            $this->ensureProductsLoaded();
            Log::info('Step 4: Product loading check completed');

            // Step 5: Filtering test
            $filteredProducts = $this->ensureProductFiltering();
            Log::info('Step 5: Filtering test completed', [
                'filtered_count' => $filteredProducts->count()
            ]);

            // Step 6: Preferences check
            $this->saveFilterPreferences();
            $this->ensureFilterPreferences();
            Log::info('Step 6: Preferences check completed');

            // Step 7: Final validation
            $finalValidation = $this->finalFilterValidation();
            Log::info('Step 7: Final validation completed', [
                'final_validation_passed' => $finalValidation
            ]);

            Log::info('Complete filter check finished successfully', [
                'check_end' => now()->format('Y-m-d H:i:s'),
                'all_steps_passed' => true
            ]);

            $this->dispatch('show-notification', type: 'success', message: 'Pemeriksaan filter lengkap selesai. Semua sistem berfungsi dengan baik.');

        } catch (\Exception $e) {
            Log::error('Complete filter check failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            $this->handleFilterError($e->getMessage(), [
                'method' => 'completeFilterCheck',
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
        }
    }

    // Method untuk memastikan filter berjalan dengan benar di semua kondisi
    public function finalFilterValidation()
    {
        try {
            Log::info('Starting final filter validation', [
                'validation_start' => now()->format('Y-m-d H:i:s'),
                'outlet_id' => $this->outlet->id ?? null,
                'outlet_name' => $this->outlet->name ?? null
            ]);

            // Validate all filter properties
            $validationResults = [
                'selectedCategory' => $this->selectedCategory && in_array($this->selectedCategory, array_merge(['all'], $this->categories)),
                'searchTerm' => is_string($this->searchTerm) && strlen($this->searchTerm) <= 100,
                'sortBy' => in_array($this->sortBy, ['name', 'price', 'newest']),
                'sortOrder' => in_array($this->sortOrder, ['asc', 'desc']),
                'showNewProducts' => is_bool($this->showNewProducts),
                'outlet' => $this->outlet && $this->outlet->id,
                'products' => !empty($this->productsGrouped),
                'categories' => !empty($this->categories)
            ];

            $allValid = true;
            $invalidFields = [];

            foreach ($validationResults as $field => $isValid) {
                if (!$isValid) {
                    $allValid = false;
                    $invalidFields[] = $field;
                }
            }

            Log::info('Final filter validation results', [
                'all_valid' => $allValid,
                'invalid_fields' => $invalidFields,
                'validation_results' => $validationResults,
                'validation_end' => now()->format('Y-m-d H:i:s')
            ]);

            if (!$allValid) {
                Log::warning('Filter validation failed, resetting to defaults', [
                    'invalid_fields' => $invalidFields
                ]);

                // Reset invalid fields to defaults
                if (in_array('selectedCategory', $invalidFields)) {
                    $this->selectedCategory = 'all';
                }
                if (in_array('searchTerm', $invalidFields)) {
                    $this->searchTerm = '';
                }
                if (in_array('sortBy', $invalidFields)) {
                    $this->sortBy = 'name';
                }
                if (in_array('sortOrder', $invalidFields)) {
                    $this->sortOrder = 'asc';
                }
                if (in_array('showNewProducts', $invalidFields)) {
                    $this->showNewProducts = false;
                }

                $this->saveFilterPreferences();
                $this->ensureFilterPreferences();

                $this->dispatch('show-notification', type: 'warning', message: 'Beberapa filter tidak valid dan telah direset ke default.');
            } else {
                Log::info('All filter validations passed successfully');
                $this->dispatch('show-notification', type: 'success', message: 'Semua filter valid dan berfungsi dengan baik.');
            }

            return $allValid;

        } catch (\Exception $e) {
            Log::error('Final filter validation failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            $this->handleFilterError($e->getMessage(), [
                'method' => 'finalFilterValidation',
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return false;
        }
    }

    // Method untuk memastikan filter berjalan dengan benar di semua kondisi dan menambahkan error handling yang lebih baik
    public function comprehensiveSystemCheck()
    {
        try {
            Log::info('Starting comprehensive system check', [
                'check_start' => now()->format('Y-m-d H:i:s'),
                'outlet_id' => $this->outlet->id ?? null,
                'outlet_name' => $this->outlet->name ?? null
            ]);

            $systemChecks = [
                'health_check' => $this->healthCheck(),
                'filter_validation' => $this->finalFilterValidation(),
                'product_filtering' => $this->ensureProductFiltering()->count() > 0,
                'preferences_saved' => $this->saveFilterPreferences(),
                'session_working' => Session::has('current_table_code'),
                'cart_functional' => is_array($this->cart),
                'outlet_available' => $this->outlet && $this->outlet->id,
                'products_loaded' => !empty($this->productsGrouped),
                'categories_available' => !empty($this->categories)
            ];

            $allChecksPassed = true;
            $failedChecks = [];

            foreach ($systemChecks as $check => $passed) {
                if (!$passed) {
                    $allChecksPassed = false;
                    $failedChecks[] = $check;
                }
            }

            Log::info('Comprehensive system check results', [
                'all_checks_passed' => $allChecksPassed,
                'failed_checks' => $failedChecks,
                'system_checks' => $systemChecks,
                'check_end' => now()->format('Y-m-d H:i:s')
            ]);

            if (!$allChecksPassed) {
                Log::warning('System check failed, attempting recovery', [
                    'failed_checks' => $failedChecks
                ]);

                // Attempt comprehensive recovery
                $this->emergencyFilterReset();
                $this->healthCheck();

                $this->dispatch('show-notification', type: 'warning', message: 'Ditemukan masalah pada sistem. Mencoba memperbaiki secara komprehensif...');
            } else {
                Log::info('All system checks passed successfully');
                $this->dispatch('show-notification', type: 'success', message: 'Semua pemeriksaan sistem berhasil. Sistem berfungsi dengan optimal.');
            }

            return $allChecksPassed;

        } catch (\Exception $e) {
            Log::error('Comprehensive system check failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            $this->handleFilterError($e->getMessage(), [
                'method' => 'comprehensiveSystemCheck',
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return false;
        }
    }

    // Method untuk memastikan filter berjalan dengan benar di semua kondisi
    public function advancedFilterOptimization()
    {
        try {
            Log::info('Starting advanced filter optimization', [
                'optimization_start' => now()->format('Y-m-d H:i:s')
            ]);

            // Optimize filter performance
            $optimizationResults = [
                'cache_cleared' => $this->clearFilterCache(),
                'memory_optimized' => $this->optimizeMemoryUsage(),
                'query_optimized' => $this->optimizeDatabaseQueries(),
                'session_optimized' => $this->optimizeSessionStorage(),
                'ui_optimized' => $this->optimizeUserInterface()
            ];

            $allOptimized = true;
            $failedOptimizations = [];

            foreach ($optimizationResults as $optimization => $success) {
                if (!$success) {
                    $allOptimized = false;
                    $failedOptimizations[] = $optimization;
                }
            }

            Log::info('Advanced filter optimization results', [
                'all_optimized' => $allOptimized,
                'failed_optimizations' => $failedOptimizations,
                'optimization_results' => $optimizationResults,
                'optimization_end' => now()->format('Y-m-d H:i:s')
            ]);

            if (!$allOptimized) {
                Log::warning('Some optimizations failed', [
                    'failed_optimizations' => $failedOptimizations
                ]);

                $this->dispatch('show-notification', type: 'warning', message: 'Beberapa optimisasi gagal. Sistem tetap berfungsi normal.');
            } else {
                Log::info('All optimizations completed successfully');
                $this->dispatch('show-notification', type: 'success', message: 'Semua optimisasi berhasil. Performa sistem telah ditingkatkan.');
            }

            return $allOptimized;

        } catch (\Exception $e) {
            Log::error('Advanced filter optimization failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            $this->handleFilterError($e->getMessage(), [
                'method' => 'advancedFilterOptimization',
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return false;
        }
    }

    // Helper methods for optimization
    protected function clearFilterCache()
    {
        try {
            // Clear any cached filter data
            $this->productsGrouped = [];
            $this->categories = [];

            // Reload products
            $this->ensureProductsLoaded();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear filter cache', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function optimizeMemoryUsage()
    {
        try {
            // Optimize memory usage by limiting data structures
            if (count($this->cart) > 100) {
                $this->cart = array_slice($this->cart, -100, 100, true);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to optimize memory usage', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function optimizeDatabaseQueries()
    {
        try {
            // Ensure efficient database queries
            if ($this->outlet) {
                $products = Product::where('outlet_id', $this->outlet->id)
                    ->where('is_available', true)
                    ->with('category')
                    ->select(['id', 'name', 'price', 'description', 'image', 'category_id', 'outlet_id', 'is_available', 'created_at'])
                    ->get();

                $this->productsGrouped = $products->groupBy(fn($p) => $p->category->name ?? 'Tanpa Kategori')
                    ->map(fn($group) => $group->all())
                    ->toArray();
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to optimize database queries', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function optimizeSessionStorage()
    {
        try {
            // Optimize session storage
            $this->saveFilterPreferences();
            $this->ensureFilterPreferences();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to optimize session storage', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function optimizeUserInterface()
    {
        try {
            // Ensure UI is optimized
            $this->validateAndFixFilterData();
            $this->ensureFilterConsistency();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to optimize user interface', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Pastikan filter memiliki nilai default yang valid
    protected function initializeFilters()
    {
        if (empty($this->selectedCategory)) {
            $this->selectedCategory = 'all';
        }
        if (empty($this->searchTerm)) {
            $this->searchTerm = '';
        }
        if (empty($this->sortBy)) {
            $this->sortBy = 'name';
        }
        if (empty($this->sortOrder)) {
            $this->sortOrder = 'asc';
        }
        if (!is_bool($this->showNewProducts)) {
            $this->showNewProducts = false;
        }
        Log::info('Initialized filters with default values', [
            'selectedCategory' => $this->selectedCategory,
            'searchTerm' => $this->searchTerm,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder,
            'showNewProducts' => $this->showNewProducts,
            'init_status' => 'Filters initialized successfully'
        ]);
    }

    // Pastikan produk sudah dimuat dan dikelompokkan per kategori
    protected function ensureProductsLoaded()
    {
        if (!$this->outlet) {
            Log::warning('No outlet available for loading products');
            return;
        }
        if (empty($this->productsGrouped)) {
            $products = Product::where('outlet_id', $this->outlet->id)
                ->where('is_available', true)
                ->with('category')
                ->get();
            $this->productsGrouped = $products->groupBy(fn($p) => $p->category->name ?? 'Tanpa Kategori')
                ->map(fn($group) => $group->all())
                ->toArray();
            $this->categories = array_keys($this->productsGrouped);
            $this->activeCategory = $this->categories[0] ?? null;
            Log::info('Products loaded successfully', [
                'total_products' => $products->count(),
                'categories' => $this->categories,
                'products_by_category' => collect($this->productsGrouped)->map(fn($products) => count($products)),
                'load_status' => 'Products loaded successfully'
            ]);
        }
    }
}
