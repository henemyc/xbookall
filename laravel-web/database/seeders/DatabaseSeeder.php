<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subscription;
use App\Models\Category;
use App\Models\Type;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSubscriptions();
        $this->seedCategories();
        $this->seedTypes();
    }

    private function seedSubscriptions(): void
    {
        $plans = [
            [
                'title' => 'Starter',
                'package_amount' => 499,
                'interval' => 'monthly',
                'user_limit' => 1,
                'trainer_limit' => 2,
                'trainee_limit' => 50,
                'enabled_logged_history' => false,
            ],
            [
                'title' => 'Professional',
                'package_amount' => 999,
                'interval' => 'monthly',
                'user_limit' => 3,
                'trainer_limit' => 5,
                'trainee_limit' => 200,
                'enabled_logged_history' => true,
            ],
            [
                'title' => 'Enterprise',
                'package_amount' => 1999,
                'interval' => 'monthly',
                'user_limit' => 0, // unlimited
                'trainer_limit' => 0,
                'trainee_limit' => 0,
                'enabled_logged_history' => true,
            ],
            [
                'title' => 'Weekly Trial',
                'package_amount' => 0,
                'interval' => 'weekly',
                'user_limit' => 1,
                'trainer_limit' => 1,
                'trainee_limit' => 10,
                'enabled_logged_history' => false,
            ],
        ];

        foreach ($plans as $plan) {
            Subscription::firstOrCreate(
                ['title' => $plan['title']],
                $plan
            );
        }
    }

    private function seedCategories(): void
    {
        $categories = ['General', 'VIP', 'Premium', 'Student', 'Senior'];
        
        foreach ($categories as $title) {
            Category::firstOrCreate(
                ['title' => $title, 'parent_id' => 0],
                ['title' => $title, 'parent_id' => 0]
            );
        }
    }

    private function seedTypes(): void
    {
        $types = [
            'Rent',
            'Salary',
            'Utilities',
            'Maintenance',
            'Equipment',
            'Marketing',
            'Insurance',
            'Other',
        ];
        
        foreach ($types as $title) {
            Type::firstOrCreate(
                ['title' => $title, 'parent_id' => 0],
                ['title' => $title, 'parent_id' => 0]
            );
        }
    }
}
