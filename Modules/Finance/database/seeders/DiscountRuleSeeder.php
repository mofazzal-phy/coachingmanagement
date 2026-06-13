<?php

namespace Modules\Finance\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DiscountRuleSeeder extends Seeder
{
    public function run(): void
    {
        // Get first admin user to use as created_by
        $adminUser = DB::table('users')->where('role', 'admin')->first();
        $createdBy = $adminUser?->id ?? DB::table('users')->first()?->id;

        if (!$createdBy) {
            $this->command->warn('No users found. Skipping discount rules seeder.');
            return;
        }

        $rules = [
            [
                'name' => 'Early Bird Discount',
                'condition_type' => 'early_bird',
                'condition_config' => json_encode(['days_before' => 15, 'discount_percent' => 10]),
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'max_cap' => null,
                'priority' => 1,
                'stackable' => false,
                'status' => 'active',
            ],
            [
                'name' => 'Sibling Discount',
                'condition_type' => 'sibling',
                'condition_config' => json_encode(['discount_percent' => 10]),
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'max_cap' => null,
                'priority' => 2,
                'stackable' => false,
                'status' => 'active',
            ],
            [
                'name' => 'Loyalty Discount',
                'condition_type' => 'loyalty',
                'condition_config' => json_encode(['discount_percent' => 5]),
                'discount_type' => 'percentage',
                'discount_value' => 5,
                'max_cap' => null,
                'priority' => 3,
                'stackable' => false,
                'status' => 'active',
            ],
            [
                'name' => 'Merit Scholarship',
                'condition_type' => 'merit',
                'condition_config' => json_encode(['min_gpa' => 5.0, 'discount_percent' => 15]),
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'max_cap' => null,
                'priority' => 4,
                'stackable' => false,
                'status' => 'active',
            ],
            [
                'name' => 'Bulk Enrollment Discount',
                'condition_type' => 'bulk',
                'condition_config' => json_encode(['min_students' => 3, 'discount_percent' => 20]),
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'max_cap' => null,
                'priority' => 5,
                'stackable' => false,
                'status' => 'active',
            ],
            [
                'name' => 'Need-Based Scholarship',
                'condition_type' => 'need_based',
                'condition_config' => json_encode(['discount_percent' => 25]),
                'discount_type' => 'percentage',
                'discount_value' => 25,
                'max_cap' => 5000,
                'priority' => 6,
                'stackable' => false,
                'status' => 'active',
            ],
            [
                'name' => 'Special Custom Discount',
                'condition_type' => 'custom',
                'condition_config' => json_encode(['operator' => 'manual', 'value' => 0]),
                'discount_type' => 'percentage',
                'discount_value' => 0,
                'max_cap' => null,
                'priority' => 7,
                'stackable' => false,
                'status' => 'active',
            ],
            // Flat discount rules (preferable for monthly fee type)
            [
                'name' => 'Monthly Fee Waiver 500',
                'condition_type' => 'custom',
                'condition_config' => json_encode(['operator' => 'manual', 'value' => 500]),
                'discount_type' => 'fixed',
                'discount_value' => 500,
                'max_cap' => null,
                'priority' => 8,
                'stackable' => false,
                'status' => 'active',
            ],
            [
                'name' => 'Monthly Fee Waiver 1000',
                'condition_type' => 'custom',
                'condition_config' => json_encode(['operator' => 'manual', 'value' => 1000]),
                'discount_type' => 'fixed',
                'discount_value' => 1000,
                'max_cap' => null,
                'priority' => 9,
                'stackable' => false,
                'status' => 'active',
            ],
            [
                'name' => 'Staff Child Discount',
                'condition_type' => 'custom',
                'condition_config' => json_encode(['operator' => 'manual', 'value' => 50]),
                'discount_type' => 'percentage',
                'discount_value' => 50,
                'max_cap' => null,
                'priority' => 10,
                'stackable' => false,
                'status' => 'active',
            ],
        ];

        foreach ($rules as $rule) {
            $rule['id'] = (string) Str::uuid();
            $rule['created_by'] = $createdBy;

            // Check if a rule with same name already exists
            $existing = DB::table('discount_rules')->where('name', $rule['name'])->first();
            if (!$existing) {
                DB::table('discount_rules')->insert($rule);
            } else {
                // Update existing rule
                DB::table('discount_rules')->where('id', $existing->id)->update([
                    'condition_type' => $rule['condition_type'],
                    'condition_config' => $rule['condition_config'],
                    'discount_type' => $rule['discount_type'],
                    'discount_value' => $rule['discount_value'],
                    'max_cap' => $rule['max_cap'],
                    'priority' => $rule['priority'],
                    'stackable' => $rule['stackable'],
                    'status' => $rule['status'],
                ]);
            }
        }

        $this->command->info('Seeded ' . count($rules) . ' discount rules.');
    }
}
