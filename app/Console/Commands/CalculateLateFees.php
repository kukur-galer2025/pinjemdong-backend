<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

#[Signature('rentals:calculate-late-fees')]
#[Description('Calculate and add late fees to overdue rentals')]
class CalculateLateFees extends Command
{
    /**
     * The fixed late fee amount per day in IDR
     */
    private const LATE_FEE_PER_DAY = 50000;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting late fee calculation...');
        $today = Carbon::today();
        
        // Get all rentals that are currently rented and have an end_date before today
        $overdueRentals = Rental::where('status', 'rented')
            ->whereDate('end_date', '<', $today)
            ->get();
            
        $count = 0;

        foreach ($overdueRentals as $rental) {
            $endDate = Carbon::parse($rental->end_date);
            
            // Calculate days late (difference in days between today and end_date)
            $daysLate = $endDate->diffInDays($today);
            
            if ($daysLate > 0) {
                $totalLateFee = $daysLate * self::LATE_FEE_PER_DAY;
                
                $newTotalAmount = $rental->subtotal + $rental->delivery_cost + $totalLateFee;
                $newRemainingAmount = $newTotalAmount - $rental->dp_amount;
                
                $rental->update([
                    'late_fee_total' => $totalLateFee,
                    'total_amount' => $newTotalAmount,
                    'remaining_amount' => max(0, $newRemainingAmount),
                ]);
                
                Log::info("Late fee applied to Rental {$rental->invoice_number}: {$daysLate} days late, Fee: {$totalLateFee}");
                $count++;
            }
        }
        
        $this->info("Completed. Applied late fees to {$count} rentals.");
    }
}
