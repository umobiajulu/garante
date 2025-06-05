<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Guarantee;
use App\Models\User;
use Illuminate\Database\Seeder;

class GuaranteeSeeder extends Seeder
{
    public function run(): void
    {
        $verifiedBusinesses = Business::where('verification_status', 'verified')->get();

        // Create guarantees between businesses
        $verifiedBusinesses->each(function ($sellerBusiness) use ($verifiedBusinesses) {
            // Get 2 random businesses as buyers
            $buyerBusinesses = $verifiedBusinesses->where('id', '!=', $sellerBusiness->id)->random(2);

            foreach ($buyerBusinesses as $buyerBusiness) {
                Guarantee::create([
                    'seller_id' => $sellerBusiness->owner_id,
                    'buyer_id' => $buyerBusiness->owner_id,
                    'business_id' => $sellerBusiness->id,
                    'service_description' => 'Test service between ' . $sellerBusiness->name . ' and ' . $buyerBusiness->name,
                    'price' => rand(100000, 1000000), // 100k to 1M Naira
                    'terms' => [
                        'delivery_date' => now()->addDays(30)->toDateString(),
                        'payment_terms' => '50% upfront, 50% on completion',
                        'deliverables' => ['Item 1', 'Item 2', 'Item 3']
                    ],
                    'status' => 'accepted',
                    'progress' => rand(0, 100),
                    'accepted_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        });
    }
} 