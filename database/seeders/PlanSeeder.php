<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'                  => 'Free',
                'slug'                  => 'free',
                'price_monthly'         => 0,
                'template_limit'        => 3,
                'document_limit'        => 5,
                'ai_credit_limit'       => 10,
                'file_size_limit_mb'    => 5,
                'bulk_generation_limit' => null,
                'max_users'             => 1,
                'storage_days'          => null,
                'features'              => [],
                'sort_order'            => 1,
            ],
            [
                'name'                  => 'Starter',
                'slug'                  => 'starter',
                'price_monthly'         => 499,
                'template_limit'        => 10,
                'document_limit'        => 100,
                'ai_credit_limit'       => 50,
                'file_size_limit_mb'    => 25,
                'bulk_generation_limit' => 50,
                'max_users'             => 1,
                'storage_days'          => 90,
                'features'              => [
                    'password_portal', 'bulk_generation', 'portal_consent',
                    'email_delivery', 'folders_tags',
                ],
                'sort_order'            => 2,
            ],
            [
                'name'                  => 'Pro',
                'slug'                  => 'pro',
                'price_monthly'         => 1499,
                'template_limit'        => 50,
                'document_limit'        => 500,
                'ai_credit_limit'       => 300,
                'file_size_limit_mb'    => 100,
                'bulk_generation_limit' => null,
                'max_users'             => 1,
                'storage_days'          => null,
                'features'              => [
                    'password_portal', 'bulk_generation', 'portal_consent',
                    'email_delivery', 'folders_tags', 'brand_kit',
                    'smart_rules', 'auto_calculations', 'repeating_rows',
                    'image_fields', 'qr_code', 'smart_filenames',
                    'version_history', 'one_time_links',
                ],
                'sort_order'            => 3,
            ],
            [
                'name'                  => 'Business',
                'slug'                  => 'business',
                'price_monthly'         => 3999,
                'template_limit'        => 200,
                'document_limit'        => 2000,
                'ai_credit_limit'       => 1500,
                'file_size_limit_mb'    => 250,
                'bulk_generation_limit' => null,
                'max_users'             => 10,
                'storage_days'          => null,
                'features'              => [
                    'password_portal', 'bulk_generation', 'portal_consent',
                    'email_delivery', 'folders_tags', 'brand_kit',
                    'smart_rules', 'auto_calculations', 'repeating_rows',
                    'image_fields', 'qr_code', 'smart_filenames',
                    'version_history', 'one_time_links', 'team_members',
                    'approvals', 'audit_logs', 'usage_analytics', 'excel_output',
                ],
                'sort_order'            => 4,
            ],
            [
                'name'                  => 'Enterprise',
                'slug'                  => 'enterprise',
                'price_monthly'         => 0,
                'template_limit'        => null,
                'document_limit'        => null,
                'ai_credit_limit'       => null,
                'file_size_limit_mb'    => 500,
                'bulk_generation_limit' => null,
                'max_users'             => null,
                'storage_days'          => null,
                'features'              => [
                    'password_portal', 'bulk_generation', 'portal_consent',
                    'email_delivery', 'folders_tags', 'brand_kit',
                    'smart_rules', 'auto_calculations', 'repeating_rows',
                    'image_fields', 'qr_code', 'smart_filenames',
                    'version_history', 'one_time_links', 'team_members',
                    'approvals', 'audit_logs', 'usage_analytics', 'excel_output',
                    'api_keys', 'webhooks', 'sso', 'white_label', 'custom_retention',
                ],
                'sort_order'            => 5,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
