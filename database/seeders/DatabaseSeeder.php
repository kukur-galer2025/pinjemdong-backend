<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductImage;
use App\Models\BankAccount;
use App\Models\UserVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\RentalPackage;
use App\Models\RentalPackageItem;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Payment;
use App\Models\Review;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ========================================
        // ADMIN USER
        // ========================================
        $admin = User::firstOrCreate(
            ['email' => 'admin@pinjemdong.com'],
            [
                'name' => 'Admin Pinjemdong',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '081234567890',
            ]
        );

        // ========================================
        // DEMO CUSTOMER
        // ========================================
        $customer = User::firstOrCreate(
            ['email' => 'budi@gmail.com'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '081298765432',
            ]
        );

        UserVerification::firstOrCreate(
            ['user_id' => $customer->id],
            [
                'ktp_number' => '3201234567890001',
                'ktp_image' => 'kyc/ktp/demo.jpg',
                'selfie_image' => 'kyc/selfie/demo.jpg',
                'emergency_contact_name' => 'Siti Aminah',
                'emergency_contact_phone' => '081211112222',
                'address' => 'Jl. Merdeka No. 45, Jakarta Selatan',
                'status' => 'approved',
                'verified_at' => now(),
            ]
        );

        // ========================================
        // BANK ACCOUNTS
        // ========================================
        $banks = [
            [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder' => 'PT Pinjemdong Indonesia',
                'is_active' => true,
            ],
            [
                'bank_name' => 'Mandiri',
                'account_number' => '0987654321',
                'account_holder' => 'PT Pinjemdong Indonesia',
                'is_active' => true,
            ],
            [
                'bank_name' => 'BNI',
                'account_number' => '5678901234',
                'account_holder' => 'PT Pinjemdong Indonesia',
                'is_active' => true,
            ],
        ];

        foreach ($banks as $b) {
            BankAccount::firstOrCreate(['account_number' => $b['account_number']], $b);
        }

        // ========================================
        // CATEGORIES
        // ========================================
        $categories = [
            ['name' => 'Kamera & Foto', 'slug' => 'kamera-foto', 'icon' => '📷', 'description' => 'Kamera DSLR, Mirrorless, Lensa, dan aksesoris fotografi', 'sort_order' => 1],
            ['name' => 'Alat Camping', 'slug' => 'alat-camping', 'icon' => '⛺', 'description' => 'Tenda, sleeping bag, kompor portable, dan perlengkapan outdoor', 'sort_order' => 2],
            ['name' => 'Konsol Game', 'slug' => 'konsol-game', 'icon' => '🎮', 'description' => 'PlayStation, Xbox, Nintendo Switch, dan aksesoris gaming', 'sort_order' => 3],
            ['name' => 'Perlengkapan Pesta', 'slug' => 'perlengkapan-pesta', 'icon' => '🎉', 'description' => 'Sound system, lighting, dekorasi, dan perlengkapan event', 'sort_order' => 4],
            ['name' => 'Peralatan Teknik', 'slug' => 'peralatan-teknik', 'icon' => '🔧', 'description' => 'Bor, gerinda, las, dan peralatan konstruksi', 'sort_order' => 5],
            ['name' => 'Fashion & Kostum', 'slug' => 'fashion-kostum', 'icon' => '👗', 'description' => 'Gaun pesta, jas, kostum cosplay, dan aksesoris fashion', 'sort_order' => 6],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug']],
                array_merge($cat, ['is_active' => true])
            );
        }

        // ========================================
        // PRODUCTS + UNITS
        // ========================================
        $products = [
            // Kamera
            ['category' => 'kamera-foto', 'name' => 'Sony A7 III Full Frame', 'slug' => 'sony-a7-iii', 'price' => 250000, 'late_fee' => 50000, 'dp' => 30, 'brand' => 'Sony', 'units' => 3, 'featured' => true,
                'desc' => 'Kamera mirrorless full-frame 24.2MP dengan autofocus mata yang luar biasa. Cocok untuk fotografi profesional dan videografi 4K.'],
            ['category' => 'kamera-foto', 'name' => 'Canon EOS R6 Mark II', 'slug' => 'canon-eos-r6-ii', 'price' => 300000, 'late_fee' => 60000, 'dp' => 30, 'brand' => 'Canon', 'units' => 2, 'featured' => true,
                'desc' => 'Mirrorless terbaru Canon dengan sensor 24.2MP, stabilisasi gambar in-body, dan kemampuan video 4K 60fps.'],
            ['category' => 'kamera-foto', 'name' => 'DJI Mavic Air 2 Drone', 'slug' => 'dji-mavic-air-2', 'price' => 350000, 'late_fee' => 75000, 'dp' => 40, 'brand' => 'DJI', 'units' => 2, 'featured' => true,
                'desc' => 'Drone fotografi dengan kamera 48MP, video 4K/60fps, dan waktu terbang hingga 34 menit. Dilengkapi obstacle avoidance.'],
            ['category' => 'kamera-foto', 'name' => 'Lensa Sony 85mm f/1.4 GM', 'slug' => 'lensa-sony-85mm', 'price' => 150000, 'late_fee' => 30000, 'dp' => 25, 'brand' => 'Sony', 'units' => 3, 'featured' => false,
                'desc' => 'Lensa portrait terbaik dari Sony G Master series. Bokeh yang sangat halus dan ketajaman luar biasa.'],

            // Camping
            ['category' => 'alat-camping', 'name' => 'Tenda Dome 4 Orang', 'slug' => 'tenda-dome-4-orang', 'price' => 85000, 'late_fee' => 15000, 'dp' => 20, 'brand' => 'Eiger', 'units' => 5, 'featured' => true,
                'desc' => 'Tenda camping kapasitas 4 orang, waterproof, mudah dipasang. Cocok untuk keluarga atau group kecil.'],
            ['category' => 'alat-camping', 'name' => 'Sleeping Bag Premium', 'slug' => 'sleeping-bag-premium', 'price' => 35000, 'late_fee' => 5000, 'dp' => 15, 'brand' => 'Consina', 'units' => 8, 'featured' => false,
                'desc' => 'Sleeping bag hangat untuk suhu hingga 5°C. Bahan lembut dan ringan, mudah dilipat.'],
            ['category' => 'alat-camping', 'name' => 'Kompor Portable + Tabung', 'slug' => 'kompor-portable', 'price' => 25000, 'late_fee' => 5000, 'dp' => 15, 'brand' => 'Bulin', 'units' => 6, 'featured' => false,
                'desc' => 'Kompor camping portable mini dengan tabung gas 230g. Api stabil, tahan angin.'],

            // Gaming
            ['category' => 'konsol-game', 'name' => 'PlayStation 5 + 2 Stik', 'slug' => 'ps5-2-stik', 'price' => 150000, 'late_fee' => 30000, 'dp' => 25, 'brand' => 'Sony', 'units' => 4, 'featured' => true,
                'desc' => 'Console next-gen PS5 Disc Edition lengkap dengan 2 DualSense controller. Termasuk 3 game populer.'],
            ['category' => 'konsol-game', 'name' => 'Nintendo Switch OLED', 'slug' => 'nintendo-switch-oled', 'price' => 100000, 'late_fee' => 20000, 'dp' => 20, 'brand' => 'Nintendo', 'units' => 3, 'featured' => false,
                'desc' => 'Switch OLED dengan layar 7 inch yang lebih jernih. Bisa dimainkan portable atau di TV.'],

            // Pesta
            ['category' => 'perlengkapan-pesta', 'name' => 'Sound System 15 Inch', 'slug' => 'sound-system-15-inch', 'price' => 200000, 'late_fee' => 40000, 'dp' => 30, 'brand' => 'JBL', 'units' => 3, 'featured' => false,
                'desc' => 'Speaker aktif 15 inch 500 Watt dengan mixer 4 channel. Cocok untuk acara outdoor hingga 200 orang.'],
            ['category' => 'perlengkapan-pesta', 'name' => 'Paket Lampu Panggung LED', 'slug' => 'lampu-panggung-led', 'price' => 175000, 'late_fee' => 35000, 'dp' => 25, 'brand' => 'Generic', 'units' => 2, 'featured' => false,
                'desc' => '6 unit lampu PAR LED RGB + DMX controller. Bisa di-program otomatis mengikuti beat musik.'],

            // Teknik
            ['category' => 'peralatan-teknik', 'name' => 'Bor Cordless Makita 18V', 'slug' => 'bor-cordless-makita', 'price' => 65000, 'late_fee' => 10000, 'dp' => 15, 'brand' => 'Makita', 'units' => 4, 'featured' => false,
                'desc' => 'Bor baterai 18V tanpa kabel. Dilengkapi 2 baterai dan charger cepat. Cocok untuk proyek rumah.'],

            // Fashion
            ['category' => 'fashion-kostum', 'name' => 'Gaun Pesta Mewah (S-XL)', 'slug' => 'gaun-pesta-mewah', 'price' => 250000, 'late_fee' => 50000, 'dp' => 35, 'brand' => 'Local Designer', 'units' => 5, 'featured' => true,
                'desc' => 'Gaun pesta premium berbahan satin dengan payet. Tersedia ukuran S, M, L, XL. Sudah termasuk dry cleaning.'],
            ['category' => 'fashion-kostum', 'name' => 'Jas Formal Premium', 'slug' => 'jas-formal-premium', 'price' => 200000, 'late_fee' => 40000, 'dp' => 30, 'brand' => 'Local Brand', 'units' => 4, 'featured' => false,
                'desc' => 'Jas formal slim-fit warna hitam dan navy. Termasuk celana, kemeja, dan dasi. Ukuran M-XXL.'],
        ];

        foreach ($products as $p) {
            $category = Category::where('slug', $p['category'])->first();

            if (!$category) continue;

            $product = Product::firstOrCreate(
                ['slug' => $p['slug']],
                [
                    'category_id' => $category->id,
                    'name' => $p['name'],
                    'description' => $p['desc'],
                    'price_per_day' => $p['price'],
                    'late_fee_per_day' => $p['late_fee'],
                    'min_dp_percentage' => $p['dp'],
                    'brand' => $p['brand'],
                    'total_units' => $p['units'],
                    'is_active' => true,
                    'is_featured' => $p['featured'],
                ]
            );

            // Create units with serial numbers
            for ($i = 1; $i <= $p['units']; $i++) {
                ProductUnit::firstOrCreate([
                    'product_id' => $product->id,
                    'serial_number' => strtoupper(Str::slug($p['name'])) . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                ], [
                    'status' => 'available',
                ]);
            }
        }

        // ========================================
        // PRODUCT IMAGES
        // ========================================
        $allProducts = Product::all();
        foreach ($allProducts as $prod) {
            // Primary image
            ProductImage::firstOrCreate([
                'product_id' => $prod->id,
                'is_primary' => true,
            ], [
                'image_path' => 'https://picsum.photos/seed/' . $prod->slug . '/600/400',
                'sort_order' => 1
            ]);
            
            // Secondary images
            ProductImage::firstOrCreate([
                'product_id' => $prod->id,
                'sort_order' => 2
            ], [
                'image_path' => 'https://picsum.photos/seed/' . $prod->slug . '-2/600/400',
                'is_primary' => false,
            ]);
            ProductImage::firstOrCreate([
                'product_id' => $prod->id,
                'sort_order' => 3
            ], [
                'image_path' => 'https://picsum.photos/seed/' . $prod->slug . '-3/600/400',
                'is_primary' => false,
            ]);
        }

        // ========================================
        // RENTAL PACKAGES
        // ========================================
        $package = RentalPackage::firstOrCreate(
            ['slug' => 'paket-camping-berdua'],
            [
                'name' => 'Paket Camping Berdua',
                'description' => 'Sewa alat camping lengkap untuk 2 orang.',
                'price_per_day' => 150000,
                'original_price_per_day' => 200000,
                'is_active' => true,
                'image' => 'https://picsum.photos/seed/paket-camping/600/400'
            ]
        );

        $tenda = Product::where('slug', 'tenda-dome-4-orang')->first();
        $sleepingbag = Product::where('slug', 'sleeping-bag-premium')->first();

        if ($tenda && $sleepingbag) {
            RentalPackageItem::firstOrCreate(['rental_package_id' => $package->id, 'product_id' => $tenda->id], ['quantity' => 1]);
            RentalPackageItem::firstOrCreate(['rental_package_id' => $package->id, 'product_id' => $sleepingbag->id], ['quantity' => 2]);
        }

        // ========================================
        // RENTALS & ITEMS
        // ========================================
        $invoiceNumber = 'INV-' . date('Ymd') . '-0001';
        $rental = Rental::firstOrCreate(
            ['invoice_number' => $invoiceNumber],
            [
                'user_id' => $customer->id,
                'start_date' => now()->addDays(2),
                'end_date' => now()->addDays(5),
                'total_days' => 3,
                'subtotal' => 255000,
                'total_amount' => 255000, // 85k * 3
                'dp_amount' => 51000,
                'remaining_amount' => 204000,
                'delivery_method' => 'pickup',
                'status' => 'pending_payment',
                'notes' => 'Tolong disiapkan ya min.'
            ]
        );

        if ($tenda) {
            $unit = ProductUnit::where('product_id', $tenda->id)->first();
            if ($unit) {
                RentalItem::firstOrCreate(
                    ['rental_id' => $rental->id, 'product_id' => $tenda->id],
                    [
                        'product_unit_id' => $unit->id,
                        'price_per_day' => $tenda->price_per_day,
                        'quantity' => 1,
                        'subtotal' => $tenda->price_per_day * 1
                    ]
                );
            }
        }

        // ========================================
        // PAYMENTS
        // ========================================
        $bank = BankAccount::first();
        if ($bank) {
            Payment::firstOrCreate(
                ['rental_id' => $rental->id, 'type' => 'dp'],
                [
                    'amount' => 51000,
                    'status' => 'pending',
                    'proof_image' => 'payments/demo-proof.jpg'
                ]
            );
        }

        // ========================================
        // REVIEWS & WISHLISTS
        // ========================================
        if ($tenda) {
            Review::firstOrCreate(
                ['product_id' => $tenda->id, 'user_id' => $customer->id],
                [
                    'rental_id' => $rental->id, // dummy for review
                    'rating' => 5,
                    'comment' => 'Barangnya bagus banget, bersih!'
                ]
            );

            // wishlist
            $customer->wishlist()->syncWithoutDetaching([$tenda->id]);
        }
    }
}
