<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('users', 'users_parent_type_index', ['parent_id', 'type']);
        $this->addIndexIfMissing('users', 'users_phone_number_index', ['phone_number']);
        $this->addIndexIfMissing('users', 'users_staff_role_id_index', ['staff_role_id']);
        $this->addIndexIfMissing('users', 'users_last_login_at_index', ['last_login_at']);

        $this->addIndexIfMissing('trainee_details', 'trainee_details_parent_status_index', ['parent_id', 'status']);
        $this->addIndexIfMissing('trainee_details', 'trainee_details_trainer_assign_index', ['trainer_assign']);
        $this->addIndexIfMissing('trainee_details', 'trainee_details_parent_expiry_index', ['parent_id', 'membership_expiry_date']);

        $this->addIndexIfMissing('attendances', 'attendances_parent_date_index', ['parent_id', 'date']);
        $this->addIndexIfMissing('attendances', 'attendances_user_date_index', ['user_id', 'date']);

        $this->addIndexIfMissing('invoices', 'invoices_parent_status_index', ['parent_id', 'status']);
        $this->addIndexIfMissing('invoices', 'invoices_parent_invoice_date_index', ['parent_id', 'invoice_date']);
        $this->addIndexIfMissing('invoices', 'invoices_user_id_index', ['user_id']);

        $this->addIndexIfMissing('invoice_items', 'invoice_items_invoice_id_index', ['invoice_id']);
        $this->addIndexIfMissing('invoice_payments', 'invoice_payments_invoice_id_index', ['invoice_id']);
        $this->addIndexIfMissing('invoice_payments', 'invoice_payments_parent_payment_date_index', ['parent_id', 'payment_date']);

        $this->addIndexIfMissing('expenses', 'expenses_parent_date_index', ['parent_id', 'date']);
        $this->addIndexIfMissing('memberships', 'memberships_parent_id_index', ['parent_id']);
        $this->addIndexIfMissing('classes', 'classes_parent_id_index', ['parent_id']);
        $this->addIndexIfMissing('class_assigns', 'class_assigns_assign_type_assign_id_index', ['assign_type', 'assign_id']);

        $this->addIndexIfMissing('products', 'products_parent_id_index', ['parent_id']);
        $this->addIndexIfMissing('notice_boards', 'notice_boards_parent_id_index', ['parent_id']);
        $this->addIndexIfMissing('lockers', 'lockers_parent_id_index', ['parent_id']);
        $this->addIndexIfMissing('events', 'events_parent_start_date_index', ['parent_id', 'start_date']);
        $this->addIndexIfMissing('workouts', 'workouts_parent_assign_index', ['parent_id', 'assign_to', 'assign_id']);

        $this->addIndexIfMissing('activity_logs', 'activity_logs_parent_created_at_index', ['parent_id', 'created_at']);
        $this->addIndexIfMissing('activity_logs', 'activity_logs_user_created_at_index', ['user_id', 'created_at']);
    }

    public function down(): void
    {
        // Intentionally do not drop indexes in production rollback; dropping large
        // indexes can lock tables and is not needed for app rollback.
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $existing = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        if (!empty($existing)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        $columnsSql = collect($columns)->map(fn($column) => "`{$column}`")->implode(', ');
        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$columnsSql})");
    }
};
