<?php

namespace App\Console\Commands;

use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rentals:calculate-penalties')]
#[Description('Calculate late penalties for overdue rentals')]
class CalculateRentalPenalties extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting penalty calculation...');

        $now = Carbon::now();
        
        $rentals = Rental::where('status', 'rented')->get();
        $overdueRentals = $rentals->filter(function($rental) {
            return $rental->is_overdue;
        });

        $count = 0;

        foreach ($overdueRentals as $rental) {
            $lateDays = $rental->overdue_days;

            if ($lateDays > 0) {
                // Calculate original rental daily rate
                $dailyRate = $rental->subtotal / $rental->total_days;

                // 100% penalty per late day
                $penaltyAmount = $lateDays * $dailyRate;
                
                $newTotalAmount = $rental->subtotal + $rental->delivery_cost + $penaltyAmount;
                $newRemainingAmount = $newTotalAmount - $rental->dp_amount;

                $rental->update([
                    'late_fee_total' => $penaltyAmount,
                    'total_amount' => $newTotalAmount,
                    'remaining_amount' => max(0, $newRemainingAmount),
                ]);

                $count++;
            }
        }

        $this->info("Successfully calculated penalties for {$count} overdue rentals.");
    }
}
