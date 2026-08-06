<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class SystemUpdateController extends BaseController
{
    public function index()
    {
        $status = $this->buildStatus();
        return view('admin.system-update.index', compact('status'));
    }

    public function run(Request $request)
    {
        @set_time_limit(300);

        $output = [];
        $ok = true;

        try {
            $before = $this->buildStatus();
            $output[] = 'Before update: ' . count($before['pending_migrations']) . ' pending migrations, ' . count($before['missing_tables']) . ' missing tables.';

            $this->ensureMigrationsTable();

            $packageRepairMessages = $this->repairKnownPackageTables();
            foreach ($packageRepairMessages as $message) {
                $output[] = $message;
            }

            $packageMarkedBefore = $this->markKnownPackageMigrationsAsRan();
            if (!empty($packageMarkedBefore)) {
                $output[] = 'Marked package table migrations as ran: ' . count($packageMarkedBefore);
            }

            $createColumnRepairMessages = $this->repairExistingCreateMigrationColumns();
            foreach ($createColumnRepairMessages as $message) {
                $output[] = $message;
            }

            // Old/live databases can already have tables while the migrations
            // table is empty or incomplete. Mark create-table migrations as ran
            // when their target table already exists, otherwise Laravel migrate
            // stops with "Base table already exists" before updates can run.
            $markedBefore = $this->markExistingCreateMigrationsAsRan();
            if (!empty($markedBefore)) {
                $output[] = 'Marked existing table migrations as ran: ' . count($markedBefore);
            }

            // Repair known critical schema before migrate too. If repair creates
            // tables, mark those create-table migrations as ran before migrate.
            $repairMessages = $this->repairKnownSchema();
            foreach ($repairMessages as $message) {
                $output[] = $message;
            }

            $createColumnRepairMessages = $this->repairExistingCreateMigrationColumns();
            foreach ($createColumnRepairMessages as $message) {
                $output[] = $message;
            }

            $packageRepairMessages = $this->repairKnownPackageTables();
            foreach ($packageRepairMessages as $message) {
                $output[] = $message;
            }

            $packageMarkedAfterRepair = $this->markKnownPackageMigrationsAsRan();
            if (!empty($packageMarkedAfterRepair)) {
                $output[] = 'Marked repaired package migrations as ran: ' . count($packageMarkedAfterRepair);
            }

            $markedAfterRepair = $this->markExistingCreateMigrationsAsRan();
            if (!empty($markedAfterRepair)) {
                $output[] = 'Marked repaired table migrations as ran: ' . count($markedAfterRepair);
            }

            $duplicateCreateMarked = $this->markKnownDuplicateCreateMigrationsAsRan();
            if (!empty($duplicateCreateMarked)) {
                $output[] = 'Marked duplicate create-table migrations as ran: ' . count($duplicateCreateMarked);
            }

            // Run normal Laravel migrations after existing/repaired tables are
            // safely registered in the migrations table.
            try {
                Artisan::call('migrate', ['--force' => true]);
                $output[] = trim(Artisan::output()) ?: 'php artisan migrate completed.';
            } catch (\Throwable $migrateException) {
                if ($this->isDuplicatePermissionForeignKeyError($migrateException)) {
                    $output[] = 'Permission package FK warning detected. Repairing/marking permission tables and retrying migration.';
                    foreach ($this->repairKnownPackageTables(true) as $message) {
                        $output[] = $message;
                    }
                    $packageMarkedOnRetry = $this->markKnownPackageMigrationsAsRan(true);
                    if (!empty($packageMarkedOnRetry)) {
                        $output[] = 'Marked permission package migrations as ran before retry: ' . count($packageMarkedOnRetry);
                    }
                } elseif ($this->isDuplicateIndexError($migrateException)) {
                    $output[] = 'Duplicate index warning detected. Marking duplicate create-table migrations and retrying migration.';
                    $duplicateCreateMarkedOnRetry = $this->markKnownDuplicateCreateMigrationsAsRan(true);
                    if (!empty($duplicateCreateMarkedOnRetry)) {
                        $output[] = 'Marked duplicate create-table migrations before retry: ' . count($duplicateCreateMarkedOnRetry);
                    }
                } else {
                    throw $migrateException;
                }

                Artisan::call('migrate', ['--force' => true]);
                $output[] = trim(Artisan::output()) ?: 'php artisan migrate completed after duplicate-schema repair.';
            }

            $repairMessages = $this->repairKnownSchema();
            foreach ($repairMessages as $message) {
                $output[] = $message;
            }

            try {
                Artisan::call('optimize:clear');
                $output[] = 'Cache cleared.';
            } catch (\Throwable $e) {
                $output[] = 'Cache clear warning: ' . $e->getMessage();
            }

            $after = $this->buildStatus();
            $output[] = 'After update: ' . count($after['pending_migrations']) . ' pending migrations, ' . count($after['missing_tables']) . ' missing tables.';
        } catch (\Throwable $e) {
            $ok = false;
            $output[] = 'Update failed: ' . $e->getMessage();
        }

        return redirect()
            ->route('admin.system-update.index')
            ->with($ok ? 'success' : 'error', $ok ? 'Database update completed.' : 'Database update failed.')
            ->with('update_output', implode("\n\n", array_filter($output)));
    }

    private function buildStatus(): array
    {
        $migrationFiles = $this->migrationFiles();
        $ranMigrations = $this->ranMigrations();
        $pending = array_values(array_diff(array_keys($migrationFiles), $ranMigrations));
        sort($pending);

        $missingTables = [];
        foreach ($migrationFiles as $migration => $meta) {
            foreach ($meta['creates'] as $table) {
                if (!Schema::hasTable($table)) {
                    $missingTables[$table][] = $migration;
                }
            }
        }

        foreach ($this->expectedRequiredTables() as $table) {
            if (!Schema::hasTable($table)) {
                $missingTables[$table][] = 'required_schema_65_tables';
            }
        }
        ksort($missingTables);

        // Also check schema that was added by column migrations.
        $missingColumns = [];
        foreach ($this->knownColumns() as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach (array_unique($columns) as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    $missingColumns[$table][] = $column;
                }
            }
        }

        foreach ($this->missingColumnsFromCreateMigrations($migrationFiles) as $table => $columns) {
            $missingColumns[$table] = array_values(array_unique(array_merge($missingColumns[$table] ?? [], $columns)));
        }
        ksort($missingColumns);

        return [
            'database' => config('database.connections.' . config('database.default') . '.database'),
            'driver' => config('database.default'),
            'migrations_table_exists' => Schema::hasTable('migrations'),
            'total_migration_files' => count($migrationFiles),
            'ran_migrations_count' => count($ranMigrations),
            'expected_table_count' => count($this->expectedRequiredTables()),
            'current_table_count' => $this->currentTableCount(),
            'pending_migrations' => $pending,
            'missing_tables' => $missingTables,
            'missing_columns' => $missingColumns,
            'recent_ran' => array_slice(array_reverse($ranMigrations), 0, 12),
            'checked_at' => now('Asia/Kolkata')->format('d M Y, h:i A'),
        ];
    }

    private function expectedRequiredTables(): array
    {
        $tables = [
            'activity_logs',
            'app_notifications',
            'assign_lockers',
            'attendances',
            'auth_pages',
            'bug_reports',
            'cache',
            'cache_locks',
            'categories',
            'class_assigns',
            'class_schedules',
            'classes',
            'contacts',
            'coupon_histories',
            'coupons',
            'event_types',
            'events',
            'expenses',
            'f_a_q_s',
            'failed_jobs',
            'freeze_membership_logs',
            'healths',
            'home_pages',
            'invoice_items',
            'invoice_payments',
            'invoices',
            'lockers',
            'logged_histories',
            'memberships',
            'migrations',
            'model_has_permissions',
            'model_has_roles',
            'notice_boards',
            'notifications',
            'nutrition_schedules',
            'otp_verifications',
            'package_transactions',
            'pages',
            'password_resets',
            'payment_gateway_settings',
            'permissions',
            'personal_access_tokens',
            'product_booking_items',
            'product_bookings',
            'products',
            'role_has_permissions',
            'roles',
            'sessions',
            'settings',
            'staff_role_permissions',
            'staff_roles',
            'subscription_orders',
            'subscription_tier_card_features',
            'subscription_tier_features',
            'subscription_tier_prices',
            'subscription_tiers',
            'subscriptions',
            'trainee_details',
            'trainer_details',
            'types',
            'users',
            'web_login_tokens',
            'whatsapp_logs',
            'workout_activities',
            'workouts',
        ];

        sort($tables);
        return array_values(array_unique($tables));
    }

    private function currentTableCount(): int
    {
        try {
            return (int) DB::table('information_schema.tables')
                ->where('table_schema', DB::getDatabaseName())
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function migrationFiles(): array
    {
        $files = File::glob(database_path('migrations/*.php')) ?: [];
        $out = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $content = File::get($file);
            preg_match_all("/Schema::create\(['\"]([^'\"]+)['\"]/", $content, $matches);
            $creates = array_values(array_unique($matches[1] ?? []));
            $columns = $this->extractCreateColumns($content);
            $isPermissionPackageMigration = $this->isPermissionPackageMigrationContent($content);

            if ($isPermissionPackageMigration) {
                $creates = array_values(array_unique(array_merge($creates, $this->permissionPackageTables())));
                $columns = array_merge($columns, $this->permissionPackageExpectedColumns());
            }

            $out[$name] = [
                'file' => basename($file),
                'creates' => $creates,
                'columns' => $columns,
                'is_permission_package_migration' => $isPermissionPackageMigration,
            ];
        }

        ksort($out);
        return $out;
    }

    private function extractCreateColumns(string $content): array
    {
        $tables = [];

        preg_match_all('/Schema::create\((["\x27])([^"\x27]+)\1\s*,\s*function\s*\([^)]*\)\s*\{(.*?)\n\s*\}\);/s', $content, $blocks, PREG_SET_ORDER);
        foreach ($blocks as $block) {
            $tableName = $block[2];
            $body = $block[3] ?? '';
            $columns = [];

            if (preg_match('/\$table->id\s*\(\s*(["\x27])([^"\x27]+)\1\s*\)/', $body, $idMatch)) {
                $this->registerExpectedColumn($columns, $idMatch[2] ?: 'id', 'id');
            } elseif (preg_match('/\$table->id\s*\(/', $body)) {
                $this->registerExpectedColumn($columns, 'id', 'id');
            }

            if (preg_match('/\$table->timestamps\s*\(/', $body) || preg_match('/\$table->nullableTimestamps\s*\(/', $body)) {
                $this->registerExpectedColumn($columns, 'created_at', 'timestamp');
                $this->registerExpectedColumn($columns, 'updated_at', 'timestamp');
            }

            if (preg_match('/\$table->softDeletes\s*\(/', $body)) {
                $this->registerExpectedColumn($columns, 'deleted_at', 'timestamp');
            }

            if (preg_match('/\$table->rememberToken\s*\(/', $body)) {
                $this->registerExpectedColumn($columns, 'remember_token', 'string');
            }

            preg_match_all('/\$table->(?:nullableMorphs|morphs)\s*\(\s*(["\x27])([^"\x27]+)\1/', $body, $morphMatches, PREG_SET_ORDER);
            foreach ($morphMatches as $match) {
                $this->registerExpectedColumn($columns, $match[2] . '_type', 'string');
                $this->registerExpectedColumn($columns, $match[2] . '_id', 'unsignedBigInteger');
            }

            preg_match_all('/\$table->([A-Za-z0-9_]+)\s*\(\s*(["\x27])([^"\x27]+)\2/', $body, $columnMatches, PREG_SET_ORDER);
            foreach ($columnMatches as $match) {
                $type = $match[1];
                $column = $match[3];
                if (in_array($type, $this->nonColumnBlueprintMethods(), true)) {
                    continue;
                }
                $this->registerExpectedColumn($columns, $column, $type);
            }

            $tables[$tableName] = $columns;
        }

        return $tables;
    }

    private function registerExpectedColumn(array &$columns, ?string $column, string $type): void
    {
        $column = trim((string) $column);
        if ($column === '' || str_contains($column, '$')) {
            return;
        }
        $columns[$column] = ['type' => $type];
    }

    private function nonColumnBlueprintMethods(): array
    {
        return [
            'index', 'unique', 'primary', 'foreign', 'foreignIdFor', 'dropColumn', 'dropIndex',
            'dropUnique', 'dropPrimary', 'renameColumn', 'fullText', 'spatialIndex', 'morphs', 'nullableMorphs',
        ];
    }

    private function isPermissionPackageMigrationContent(string $content): bool
    {
        return str_contains($content, 'permission.table_names')
            || str_contains($content, 'model_has_permissions')
            || str_contains($content, 'model_has_roles')
            || str_contains($content, 'role_has_permissions')
            || str_contains($content, 'PermissionRegistrar::$pivotPermission')
            || str_contains($content, 'PermissionRegistrar::$pivotRole');
    }

    private function permissionPackageTables(): array
    {
        return [
            'permissions',
            'roles',
            'model_has_permissions',
            'model_has_roles',
            'role_has_permissions',
        ];
    }

    private function permissionPackageExpectedColumns(): array
    {
        return [
            'permissions' => [
                'id' => ['type' => 'id'],
                'name' => ['type' => 'string'],
                'guard_name' => ['type' => 'string'],
                'created_at' => ['type' => 'timestamp'],
                'updated_at' => ['type' => 'timestamp'],
            ],
            'roles' => [
                'id' => ['type' => 'id'],
                'name' => ['type' => 'string'],
                'guard_name' => ['type' => 'string'],
                'created_at' => ['type' => 'timestamp'],
                'updated_at' => ['type' => 'timestamp'],
            ],
            'model_has_permissions' => [
                'permission_id' => ['type' => 'unsignedBigInteger'],
                'model_type' => ['type' => 'string'],
                'model_id' => ['type' => 'unsignedBigInteger'],
            ],
            'model_has_roles' => [
                'role_id' => ['type' => 'unsignedBigInteger'],
                'model_type' => ['type' => 'string'],
                'model_id' => ['type' => 'unsignedBigInteger'],
            ],
            'role_has_permissions' => [
                'permission_id' => ['type' => 'unsignedBigInteger'],
                'role_id' => ['type' => 'unsignedBigInteger'],
            ],
        ];
    }

    private function permissionPackageMigrationFiles(?array $migrationFiles = null): array
    {
        $migrationFiles = $migrationFiles ?: $this->migrationFiles();
        return array_filter($migrationFiles, fn($meta) => !empty($meta['is_permission_package_migration']));
    }

    private function needsPermissionPackageRepair(): bool
    {
        if (!empty($this->permissionPackageMigrationFiles())) {
            return true;
        }

        foreach ($this->permissionPackageTables() as $table) {
            if (Schema::hasTable($table)) {
                return true;
            }
        }

        return false;
    }

    private function repairKnownPackageTables(bool $force = false): array
    {
        $messages = [];
        if (!$force && !$this->needsPermissionPackageRepair()) {
            return $messages;
        }

        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->timestamps();
                $table->unique(['name', 'guard_name'], 'permissions_name_guard_name_unique');
            });
            $messages[] = 'Repaired: created permissions table.';
        }

        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->timestamps();
                $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
            });
            $messages[] = 'Repaired: created roles table.';
        }

        if (!Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            });
            $messages[] = 'Repaired: created model_has_permissions table.';
        }

        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            });
            $messages[] = 'Repaired: created model_has_roles table.';
        }

        if (!Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->index('permission_id', 'role_has_permissions_permission_id_index');
                $table->index('role_id', 'role_has_permissions_role_id_index');
            });
            $messages[] = 'Repaired: created role_has_permissions table.';
        }

        foreach ($this->permissionPackageExpectedColumns() as $tableName => $columns) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            foreach ($columns as $column => $definition) {
                if (Schema::hasColumn($tableName, $column)) {
                    continue;
                }
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($column, $definition) {
                        $this->addSafeColumn($table, $column, (string) ($definition['type'] ?? 'string'));
                    });
                    $messages[] = 'Repaired: added ' . $tableName . '.' . $column . ' column.';
                } catch (\Throwable $e) {
                    $messages[] = 'Warning: could not add ' . $tableName . '.' . $column . ' column: ' . $e->getMessage();
                }
            }
        }

        return $messages;
    }

    private function markKnownPackageMigrationsAsRan(bool $force = false): array
    {
        $this->ensureMigrationsTable();
        $migrationFiles = $this->migrationFiles();
        $ranMigrations = $this->ranMigrations();
        $batch = ((int) DB::table('migrations')->max('batch')) + 1;
        $marked = [];

        foreach ($migrationFiles as $migration => $meta) {
            if (in_array($migration, $ranMigrations, true)) {
                continue;
            }
            if (empty($meta['is_permission_package_migration'])) {
                continue;
            }
            if (!$force && !$this->permissionPackageTablesReady()) {
                continue;
            }

            DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => $batch,
            ]);
            $ranMigrations[] = $migration;
            $marked[] = $migration;
        }

        return $marked;
    }

    private function permissionPackageTablesReady(): bool
    {
        foreach ($this->permissionPackageExpectedColumns() as $tableName => $columns) {
            if (!Schema::hasTable($tableName)) {
                return false;
            }
            foreach ($columns as $column => $definition) {
                if (!Schema::hasColumn($tableName, $column)) {
                    return false;
                }
            }
        }
        return true;
    }

    private function isDuplicatePermissionForeignKeyError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'model_has_permissions')
            || str_contains($message, 'model_has_roles')
            || str_contains($message, 'role_has_permissions')
            || str_contains($message, 'permission_id_foreign')
            || str_contains($message, 'role_id_foreign')
            || (str_contains($message, 'errno: 121') && str_contains($message, 'foreign key'));
    }

    private function isDuplicateIndexError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'duplicate key name')
            || str_contains($message, '1061 duplicate')
            || str_contains($message, 'bug_reports_user_id_index')
            || str_contains($message, 'bug_reports_status_created_at_index');
    }

    private function markKnownDuplicateCreateMigrationsAsRan(bool $force = false): array
    {
        $this->ensureMigrationsTable();

        $tables = ['bug_reports'];
        $migrationFiles = $this->migrationFiles();
        $ranMigrations = $this->ranMigrations();
        $batch = ((int) DB::table('migrations')->max('batch')) + 1;
        $marked = [];

        foreach ($migrationFiles as $migration => $meta) {
            if (in_array($migration, $ranMigrations, true)) {
                continue;
            }

            $creates = $meta['creates'] ?? [];
            if (empty(array_intersect($tables, $creates))) {
                continue;
            }

            $targetTablesExist = true;
            foreach (array_intersect($tables, $creates) as $tableName) {
                if (!Schema::hasTable($tableName)) {
                    $targetTablesExist = false;
                    break;
                }
            }

            if (!$targetTablesExist && !$force) {
                continue;
            }

            DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => $batch,
            ]);
            $ranMigrations[] = $migration;
            $marked[] = $migration;
        }

        return $marked;
    }

    private function missingColumnsFromCreateMigrations(?array $migrationFiles = null): array
    {
        $migrationFiles = $migrationFiles ?: $this->migrationFiles();
        $missing = [];

        foreach ($migrationFiles as $meta) {
            foreach (($meta['columns'] ?? []) as $tableName => $columns) {
                if (!Schema::hasTable($tableName)) {
                    continue;
                }

                foreach ($columns as $column => $definition) {
                    if (!Schema::hasColumn($tableName, $column)) {
                        $missing[$tableName][] = $column;
                    }
                }
            }
        }

        foreach ($missing as $tableName => $columns) {
            $missing[$tableName] = array_values(array_unique($columns));
        }

        return $missing;
    }

    private function repairExistingCreateMigrationColumns(): array
    {
        $messages = [];
        $migrationFiles = $this->migrationFiles();

        foreach ($migrationFiles as $meta) {
            foreach (($meta['columns'] ?? []) as $tableName => $columns) {
                if (!Schema::hasTable($tableName)) {
                    continue;
                }

                foreach ($columns as $column => $definition) {
                    if (Schema::hasColumn($tableName, $column)) {
                        continue;
                    }

                    try {
                        Schema::table($tableName, function (Blueprint $table) use ($column, $definition) {
                            $this->addSafeColumn($table, $column, (string) ($definition['type'] ?? 'string'));
                        });
                        $messages[] = 'Repaired: added ' . $tableName . '.' . $column . ' column.';
                    } catch (\Throwable $e) {
                        $messages[] = 'Warning: could not add ' . $tableName . '.' . $column . ' column: ' . $e->getMessage();
                    }
                }
            }
        }

        return $messages;
    }

    private function createMigrationHasMissingColumns(array $meta): bool
    {
        foreach (($meta['columns'] ?? []) as $tableName => $columns) {
            if (!Schema::hasTable($tableName)) {
                return true;
            }

            foreach ($columns as $column => $definition) {
                if (!Schema::hasColumn($tableName, $column)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function addSafeColumn(Blueprint $table, string $column, string $type): void
    {
        match ($type) {
            'id', 'bigIncrements' => $table->unsignedBigInteger($column)->nullable(),
            'increments' => $table->unsignedInteger($column)->nullable(),
            'tinyIncrements' => $table->unsignedTinyInteger($column)->nullable(),
            'smallIncrements' => $table->unsignedSmallInteger($column)->nullable(),
            'mediumIncrements' => $table->unsignedMediumInteger($column)->nullable(),
            'string', 'char', 'uuid', 'ulid' => $table->string($column)->nullable(),
            'text' => $table->text($column)->nullable(),
            'mediumText' => $table->mediumText($column)->nullable(),
            'longText' => $table->longText($column)->nullable(),
            'integer' => $table->integer($column)->nullable(),
            'unsignedInteger' => $table->unsignedInteger($column)->nullable(),
            'tinyInteger' => $table->tinyInteger($column)->nullable(),
            'unsignedTinyInteger' => $table->unsignedTinyInteger($column)->nullable(),
            'smallInteger' => $table->smallInteger($column)->nullable(),
            'unsignedSmallInteger' => $table->unsignedSmallInteger($column)->nullable(),
            'mediumInteger' => $table->mediumInteger($column)->nullable(),
            'unsignedMediumInteger' => $table->unsignedMediumInteger($column)->nullable(),
            'bigInteger' => $table->bigInteger($column)->nullable(),
            'unsignedBigInteger', 'foreignId' => $table->unsignedBigInteger($column)->nullable(),
            'boolean' => $table->boolean($column)->default(false),
            'decimal' => $table->decimal($column, 12, 2)->default(0),
            'float' => $table->float($column)->nullable(),
            'double' => $table->double($column)->nullable(),
            'date' => $table->date($column)->nullable(),
            'dateTime', 'datetime' => $table->dateTime($column)->nullable(),
            'timestamp', 'timestamps', 'nullableTimestamps' => $table->timestamp($column)->nullable(),
            'time' => $table->time($column)->nullable(),
            'json' => $table->json($column)->nullable(),
            'jsonb' => $table->jsonb($column)->nullable(),
            'enum' => $table->string($column)->nullable(),
            default => $table->string($column)->nullable(),
        };
    }

    private function ensureMigrationsTable(): void
    {
        if (Schema::hasTable('migrations')) {
            return;
        }

        Schema::create('migrations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });
    }

    private function markExistingCreateMigrationsAsRan(): array
    {
        $this->ensureMigrationsTable();

        $migrationFiles = $this->migrationFiles();
        $ranMigrations = $this->ranMigrations();
        $batch = ((int) DB::table('migrations')->max('batch')) + 1;
        $marked = [];

        foreach ($migrationFiles as $migration => $meta) {
            if (in_array($migration, $ranMigrations, true)) {
                continue;
            }

            $creates = $meta['creates'] ?? [];
            if (empty($creates)) {
                continue;
            }

            $allTablesExist = true;
            foreach ($creates as $table) {
                if (!Schema::hasTable($table)) {
                    $allTablesExist = false;
                    break;
                }
            }

            if (!$allTablesExist) {
                continue;
            }

            if ($this->createMigrationHasMissingColumns($meta)) {
                continue;
            }

            DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => $batch,
            ]);
            $ranMigrations[] = $migration;
            $marked[] = $migration;
        }

        return $marked;
    }

    private function ranMigrations(): array
    {
        try {
            if (!Schema::hasTable('migrations')) {
                return [];
            }

            return DB::table('migrations')
                ->orderBy('batch')
                ->orderBy('migration')
                ->pluck('migration')
                ->map(fn($m) => (string) $m)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function knownColumns(): array
    {
        return [
            'users' => [
                'subscription_tier_id',
                'subscription_price_id',
                'subscription_status',
                'subscription_started_at',
                'subscription_ends_at',
                'staff_role_id',
                'last_login_at',
                'last_login_ip',
                'last_login_user_agent',
                'password_changed_at',
                'last_app_opened_at',
                'last_app_platform',
                'last_app_version',
                'last_app_ip',
            ],
            'subscription_orders' => [
                'subscription_tier_id',
                'subscription_tier_price_id',
                'billing_cycle',
                'duration_months',
                'gateway',
                'gateway_order_id',
                'gateway_payment_id',
                'gateway_status',
                'starts_at',
                'ends_at',
            ],
            'trainer_details' => [
                'specialization',
                'experience_years',
                'joining_date',
                'salary',
                'bio',
                'emergency_contact',
            ],
        ];
    }

    private function repairKnownSchema(): array
    {
        $messages = [];

        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
            $messages[] = 'Repaired: created sessions table.';
        }

        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
            $messages[] = 'Repaired: created cache table.';
        }

        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
            $messages[] = 'Repaired: created cache_locks table.';
        }

        foreach ($this->repairLegacyCompatibilityTables() as $message) {
            $messages[] = $message;
        }

        if (!Schema::hasTable('bug_reports')) {
            Schema::create('bug_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('gym_name')->nullable();
                $table->string('email')->nullable();
                $table->string('title');
                $table->text('description');
                $table->string('screenshot_path')->nullable();
                $table->boolean('has_screenshot')->default(false);
                $table->string('status')->default('open');
                $table->text('admin_notes')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at'], 'bug_reports_status_created_at_index');
                $table->index('user_id', 'bug_reports_user_id_index');
            });
            $messages[] = 'Repaired: created bug_reports table.';
        }

        if (!Schema::hasTable('staff_roles')) {
            Schema::create('staff_roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
                $table->unique(['parent_id', 'name']);
                $table->index('parent_id');
                $table->index('status');
            });
            $messages[] = 'Repaired: created staff_roles table.';
        }

        if (!Schema::hasTable('staff_role_permissions')) {
            Schema::create('staff_role_permissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_role_id');
                $table->string('permission_key', 120);
                $table->timestamps();
                $table->unique(['staff_role_id', 'permission_key'], 'staff_role_permissions_role_permission_unique');
                $table->index('staff_role_id');
                $table->index('permission_key');
            });
            $messages[] = 'Repaired: created staff_role_permissions table.';
        }

        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->default(0);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_type', 30)->nullable();
                $table->string('module', 80);
                $table->string('action', 80);
                $table->string('record_type', 120)->nullable();
                $table->unsignedBigInteger('record_id')->nullable();
                $table->text('description')->nullable();
                $table->json('before_json')->nullable();
                $table->json('after_json')->nullable();
                $table->string('ip', 60)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
                $table->index('parent_id');
                $table->index('user_id');
                $table->index(['module', 'action']);
                $table->index(['record_type', 'record_id']);
                $table->index('created_at');
            });
            $messages[] = 'Repaired: created activity_logs table.';
        }

        if (!Schema::hasTable('subscription_tiers')) {
            Schema::create('subscription_tiers', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->string('badge_text', 80)->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_coming_soon')->default(false);
                $table->timestamps();
                $table->index(['is_active', 'sort_order']);
                $table->index('is_coming_soon');
            });
            $messages[] = 'Repaired: created subscription_tiers table.';
        }

        if (!Schema::hasTable('subscription_tier_features')) {
            Schema::create('subscription_tier_features', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('subscription_tier_id');
                $table->string('feature_key', 100);
                $table->string('feature_label', 180);
                $table->string('value_type', 20)->default('bool');
                $table->string('value', 255)->default('0');
                $table->boolean('is_highlighted')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['subscription_tier_id', 'feature_key'], 'tier_features_tier_key_unique');
                $table->index('subscription_tier_id');
                $table->index('feature_key');
                $table->index('sort_order');
            });
            $messages[] = 'Repaired: created subscription_tier_features table.';
        }

        if (!Schema::hasTable('subscription_tier_prices')) {
            Schema::create('subscription_tier_prices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('subscription_tier_id');
                $table->string('billing_cycle', 40);
                $table->integer('duration_months')->default(1);
                $table->decimal('price', 10, 2)->default(0);
                $table->decimal('strike_price', 10, 2)->nullable();
                $table->string('discount_text', 80)->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->index('subscription_tier_id');
                $table->index(['is_active', 'sort_order']);
                $table->index(['billing_cycle', 'duration_months']);
            });
            $messages[] = 'Repaired: created subscription_tier_prices table.';
        }

        if (!Schema::hasTable('subscription_tier_card_features')) {
            Schema::create('subscription_tier_card_features', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('subscription_tier_id');
                $table->string('feature_label', 180);
                $table->boolean('is_included')->default(true);
                $table->string('tooltip_text', 255)->nullable();
                $table->boolean('is_visible')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->index('subscription_tier_id');
                $table->index(['is_visible', 'sort_order']);
            });
            $messages[] = 'Repaired: created subscription_tier_card_features table.';
        }

        $seededCardFeatures = $this->seedSubscriptionTierCardFeatures();
        if ($seededCardFeatures > 0) {
            $messages[] = 'Repaired: synced ' . $seededCardFeatures . ' subscription card features.';
        }

        if (Schema::hasTable('users')) {
            $userColumns = [
                'subscription_tier_id' => fn(Blueprint $table) => $table->unsignedBigInteger('subscription_tier_id')->nullable()->after('subscription')->index(),
                'subscription_price_id' => fn(Blueprint $table) => $table->unsignedBigInteger('subscription_price_id')->nullable()->after('subscription_tier_id')->index(),
                'subscription_status' => fn(Blueprint $table) => $table->string('subscription_status', 30)->nullable()->after('subscription_price_id')->index(),
                'subscription_started_at' => fn(Blueprint $table) => $table->timestamp('subscription_started_at')->nullable()->after('subscription_status'),
                'subscription_ends_at' => fn(Blueprint $table) => $table->timestamp('subscription_ends_at')->nullable()->after('subscription_started_at')->index(),
                'staff_role_id' => fn(Blueprint $table) => $table->unsignedBigInteger('staff_role_id')->nullable()->after('parent_id')->index(),
                'last_login_at' => fn(Blueprint $table) => $table->timestamp('last_login_at')->nullable()->after('email_verified_at'),
                'last_login_ip' => fn(Blueprint $table) => $table->string('last_login_ip', 60)->nullable()->after('last_login_at'),
                'last_login_user_agent' => fn(Blueprint $table) => $table->text('last_login_user_agent')->nullable()->after('last_login_ip'),
                'password_changed_at' => fn(Blueprint $table) => $table->timestamp('password_changed_at')->nullable()->after('last_login_user_agent'),
            ];

            foreach ($userColumns as $column => $callback) {
                if (!Schema::hasColumn('users', $column)) {
                    Schema::table('users', function (Blueprint $table) use ($callback) {
                        $callback($table);
                    });
                    $messages[] = 'Repaired: added users.' . $column . ' column.';
                }
            }
        }

        if (Schema::hasTable('subscription_orders')) {
            $orderColumns = [
                'subscription_tier_id' => fn(Blueprint $table) => $table->unsignedBigInteger('subscription_tier_id')->nullable()->after('plan_id')->index(),
                'subscription_tier_price_id' => fn(Blueprint $table) => $table->unsignedBigInteger('subscription_tier_price_id')->nullable()->after('subscription_tier_id')->index(),
                'billing_cycle' => fn(Blueprint $table) => $table->string('billing_cycle', 40)->nullable()->after('order_type'),
                'duration_months' => fn(Blueprint $table) => $table->integer('duration_months')->nullable()->after('billing_cycle'),
                'starts_at' => fn(Blueprint $table) => $table->timestamp('starts_at')->nullable()->after(Schema::hasColumn('subscription_orders', 'gateway_status') ? 'gateway_status' : 'cf_order_id'),
                'ends_at' => fn(Blueprint $table) => $table->timestamp('ends_at')->nullable()->after('starts_at')->index(),
            ];

            foreach ($orderColumns as $column => $callback) {
                if (!Schema::hasColumn('subscription_orders', $column)) {
                    Schema::table('subscription_orders', function (Blueprint $table) use ($callback) {
                        $callback($table);
                    });
                    $messages[] = 'Repaired: added subscription_orders.' . $column . ' column.';
                }
            }
        }

        return $messages;
    }

    private function repairLegacyCompatibilityTables(): array
    {
        $messages = [];

        if (!Schema::hasTable('auth_pages')) {
            Schema::create('auth_pages', function (Blueprint $table) {
                $table->id();
                $table->text('title')->nullable();
                $table->text('description')->nullable();
                $table->string('section')->nullable();
                $table->string('image')->nullable();
                $table->integer('parent_id')->default(0);
                $table->timestamps();
            });
            $messages[] = 'Repaired: created legacy auth_pages table.';
        }

        if (!Schema::hasTable('contacts')) {
            Schema::create('contacts', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('contact_number')->nullable();
                $table->string('subject')->nullable();
                $table->text('message')->nullable();
                $table->integer('parent_id')->default(0);
                $table->timestamps();
            });
            $messages[] = 'Repaired: created legacy contacts table.';
        }

        if (!Schema::hasTable('coupon_histories')) {
            Schema::create('coupon_histories', function (Blueprint $table) {
                $table->id();
                $table->integer('coupon')->default(0);
                $table->integer('package')->default(0);
                $table->integer('user_id')->default(0);
                $table->date('date')->nullable();
                $table->timestamps();
            });
            $messages[] = 'Repaired: created legacy coupon_histories table.';
        }

        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('type')->nullable();
                $table->double('rate', 8, 2)->default(0);
                $table->string('applicable_packages')->nullable();
                $table->string('code')->nullable();
                $table->date('valid_for')->nullable();
                $table->integer('use_limit')->default(0);
                $table->integer('status')->default(1);
                $table->timestamps();
            });
            $messages[] = 'Repaired: created legacy coupons table.';
        }

        if (!Schema::hasTable('f_a_q_s')) {
            Schema::create('f_a_q_s', function (Blueprint $table) {
                $table->id();
                $table->string('question')->nullable();
                $table->text('description')->nullable();
                $table->integer('enabled')->default(0);
                $table->integer('parent_id')->default(0);
                $table->timestamps();
            });
            $messages[] = 'Repaired: created legacy f_a_q_s table.';
        }

        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
            $messages[] = 'Repaired: created failed_jobs table.';
        }

        if (!Schema::hasTable('home_pages')) {
            Schema::create('home_pages', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->string('section')->nullable();
                $table->mediumText('content')->nullable();
                $table->text('content_value')->nullable();
                $table->integer('enabled')->default(1);
                $table->integer('parent_id')->default(0);
                $table->timestamps();
            });
            $messages[] = 'Repaired: created legacy home_pages table.';
        }

        if (!Schema::hasTable('logged_histories')) {
            Schema::create('logged_histories', function (Blueprint $table) {
                $table->id();
                $table->integer('user_id')->default(0);
                $table->string('ip')->nullable();
                $table->dateTime('date')->nullable();
                $table->text('details')->nullable();
                $table->string('type')->nullable();
                $table->integer('parent_id')->default(0);
                $table->timestamps();
            });
            $messages[] = 'Repaired: created legacy logged_histories table.';
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->string('module')->nullable();
                $table->string('name')->nullable();
                $table->text('subject')->nullable();
                $table->text('message')->nullable();
                $table->text('short_code')->nullable();
                $table->integer('enabled_email')->default(0);
                $table->integer('parent_id')->default(0);
                $table->timestamps();
            });
            $messages[] = 'Repaired: created legacy notifications table.';
        }

        if (!Schema::hasTable('nutrition_schedules')) {
            Schema::create('nutrition_schedules', function (Blueprint $table) {
                $table->id();
                $table->integer('parent_id');
                $table->integer('user_id');
                $table->date('start_date');
                $table->date('end_date');
                $table->text('schedules');
                $table->timestamps();
            });
            $messages[] = 'Repaired: created legacy nutrition_schedules table.';
        }

        if (!Schema::hasTable('package_transactions')) {
            Schema::create('package_transactions', function (Blueprint $table) {
                $table->id();
                $table->integer('user_id')->default(0);
                $table->integer('subscription_id')->default(0);
                $table->string('subscription_transactions_id');
                $table->double('amount', 8, 2)->default(0);
                $table->string('transaction_id')->nullable();
                $table->string('payment_status')->nullable();
                $table->string('payment_type')->nullable();
                $table->string('holder_name', 100)->nullable();
                $table->string('card_number', 10)->nullable();
                $table->string('card_expiry_month', 10)->nullable();
                $table->string('card_expiry_year', 10)->nullable();
                $table->string('receipt')->nullable();
                $table->timestamps();
            });
            $messages[] = 'Repaired: created legacy package_transactions table.';
        }

        if (!Schema::hasTable('pages')) {
            Schema::create('pages', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->string('slug')->nullable();
                $table->mediumText('content')->nullable();
                $table->integer('enabled')->default(0);
                $table->integer('parent_id')->default(0);
                $table->timestamps();
            });
            $messages[] = 'Repaired: created legacy pages table.';
        }

        if (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table) {
                $table->string('email');
                $table->string('token');
                $table->timestamp('created_at')->nullable();
                $table->index('email', 'password_resets_email_index');
            });
            $messages[] = 'Repaired: created password_resets table.';
        }

        if (!Schema::hasTable('product_bookings')) {
            Schema::create('product_bookings', function (Blueprint $table) {
                $table->id();
                $table->integer('parent_id');
                $table->integer('user_id');
                $table->decimal('price', 15, 2);
                $table->date('invoice_date');
                $table->timestamps();
            });
            $messages[] = 'Repaired: created legacy product_bookings table.';
        }

        if (!Schema::hasTable('product_booking_items')) {
            Schema::create('product_booking_items', function (Blueprint $table) {
                $table->id();
                $table->integer('product_booking_id');
                $table->integer('product_id');
                $table->integer('quantity')->default(1);
                $table->timestamps();
            });
            $messages[] = 'Repaired: created legacy product_booking_items table.';
        }

        return $messages;
    }

    private function seedSubscriptionTierCardFeatures(): int
    {
        if (!Schema::hasTable('subscription_tiers') || !Schema::hasTable('subscription_tier_card_features')) {
            return 0;
        }

        $rows = [
            'bronze' => [
                ['Up to 150 members', true, 'Editable from Super Admin SaaS feature limits.', 10],
                ['Bulk import up to 150 rows', true, 'CSV import limit is controlled from Bronze system features.', 20],
                ['Web QR login', true, 'Gym owner can login to web panel using app QR approval.', 30],
                ['Free QR Sticker', true, '3 stickers included.', 40],
                ['Priority support', true, 'Standard priority support during business hours.', 50],
                ['Trainer management', false, 'Available from Silver plan.', 60],
                ['Staff & roles', false, 'Available from Silver plan.', 70],
                ['Locker management', false, 'Available from Silver plan.', 80],
            ],
            'silver' => [
                ['Up to 300 members', true, 'Editable from Super Admin SaaS feature limits.', 10],
                ['Bulk import up to 500 rows', true, 'CSV import limit is controlled from Silver system features.', 20],
                ['Trainer management', true, 'Up to 5 trainers by default.', 30],
                ['Staff & roles', true, 'Up to 3 staff users by default.', 40],
                ['Locker management', true, 'Included in Silver plan.', 50],
                ['Free QR Sticker', true, '10 stickers included.', 60],
                ['Priority support', true, 'Faster response than Bronze.', 70],
                ['Biometric attendance', false, 'Coming soon in Gold plan.', 80],
            ],
            'gold' => [
                ['Up to 1000 members', true, 'Editable from Super Admin SaaS feature limits.', 10],
                ['Trainer management', true, 'Up to 15 trainers by default.', 20],
                ['Staff & roles', true, 'Up to 10 staff users by default.', 30],
                ['Locker management', true, 'Included in Gold plan.', 40],
                ['Free QR Sticker', true, '25 stickers included.', 50],
                ['Biometric attendance', true, 'Coming soon. Enable when hardware module is ready.', 60],
                ['Multi branch', true, 'Coming soon. Enable when branch module is ready.', 70],
                ['Premium support', true, 'Highest support priority.', 80],
            ],
        ];

        $now = now();
        $count = 0;
        foreach ($rows as $tierCode => $features) {
            $tierId = DB::table('subscription_tiers')->where('code', $tierCode)->value('id');
            if (!$tierId) continue;
            if (DB::table('subscription_tier_card_features')->where('subscription_tier_id', $tierId)->exists()) continue;

            foreach ($features as [$label, $included, $tooltip, $sort]) {
                DB::table('subscription_tier_card_features')->updateOrInsert(
                    ['subscription_tier_id' => $tierId, 'feature_label' => $label],
                    [
                        'is_included' => $included ? 1 : 0,
                        'tooltip_text' => $tooltip,
                        'is_visible' => 1,
                        'sort_order' => $sort,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }
}
