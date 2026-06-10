<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserVerification;
use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Payment;
use Carbon\Carbon;

class OverdueRentalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Buat User Khusus yang Telat Mengembalikan
        $user = User::firstOrCreate(
            ['email' => 'budi.telat@example.com'],
            [
                'name' => 'Budi Terlambat',
                'password' => bcrypt('password123'),
                'phone' => '081299998888',
            ]
        );

        // Verifikasi KYC agar dianggap valid
        UserVerification::firstOrCreate(
            ['user_id' => $user->id],
            [
                'ktp_number' => '1234567890123456',
                'status' => 'approved',
                'verified_at' => now(),
            ]
        );

        // 2. Ambil produk yang harganya lumayan & denda harian tinggi
        // Kita ambil produk Kamera Sony A7 III atau produk aktif pertama
        $product = Product::where('is_active', true)->whereHas('units', function($q) {
            $q->where('status', 'available');
        })->first();

        if (!$product) {
            $this->command->error("Tidak ada produk tersedia untuk disewa.");
            return;
        }

        // Ambil 1 unit
        $unit = $product->units()->where('status', 'available')->first();

        // 3. Set Tanggal Sewa di Masa Lalu (Misal: Harusnya kembali 3 hari lalu)
        $totalDays = 2; // Sewa 2 hari
        $startDate = Carbon::now()->subDays(5); // Mulai 5 hari yang lalu
        $endDate = Carbon::now()->subDays(3);   // Berakhir 3 hari yang lalu (Sudah telat 3 hari saat ini)
        
        $subtotal = $product->price_per_day * $totalDays;
        $totalAmount = $subtotal; // Tidak ada ongkir untuk seeder ini

        // 4. Buat Rental
        $rental = Rental::create([
            'invoice_number' => Rental::generateInvoiceNumber(),
            'user_id' => $user->id,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'total_days' => $totalDays,
            'subtotal' => $subtotal,
            'delivery_cost' => 0,
            'total_amount' => $totalAmount,
            'dp_amount' => $totalAmount, // Lunas diawal
            'remaining_amount' => 0,
            'delivery_method' => 'pickup',
            'status' => 'rented', // Sedang disewa, belum dikembalikan
            'notes' => 'Seeder: Simulasi Telat 3 Hari',
        ]);

        // 5. Buat Rental Item
        RentalItem::create([
            'rental_id' => $rental->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => 1,
            'price_per_day' => $product->price_per_day,
            'subtotal' => $subtotal,
        ]);

        // Tandai unit sedang disewa
        $unit->update(['status' => 'rented']);

        // 6. Buat Payment History yang valid (Lunas di awal)
        Payment::create([
            'rental_id' => $rental->id,
            'type' => 'full_payment',
            'amount' => $totalAmount,
            'proof_image' => 'payments/dummy-proof.jpg',
            'status' => 'confirmed',
            'admin_notes' => 'Otomatis lunas oleh seeder',
            'confirmed_by' => 1, // Asumsi admin ID 1 ada
            'confirmed_at' => $startDate,
        ]);

        $this->command->info("Seeder berhasil! Budi Terlambat (budi.telat@example.com) telat mengembalikan {$product->name} selama 3 hari.");
        $this->command->info("Cek Admin Dashboard -> Pesanan untuk mencoba memproses pengembaliannya.");
    }
}
