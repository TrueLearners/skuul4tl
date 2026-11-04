<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Make each schema change conditional so a partially completed TiDB
     * migration can be safely retried.
     */
    public function up(): void
    {
        $tableName = 'school_domains';

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $indexes = Schema::getIndexes($tableName);

        // Drop the old single-column host unique index only if it exists.
        foreach ($indexes as $index) {
            $columns = $index['columns'] ?? [];
            if (($index['unique'] ?? false)
                && $columns === ['host']
                && ! empty($index['name'])) {
                Schema::table($tableName, function (Blueprint $table) use ($index): void {
                    $table->dropUnique($index['name']);
                });
            }
        }

        $indexes = Schema::getIndexes($tableName);
        $hasOrganizationHostUnique = collect($indexes)->contains(
            fn (array $index): bool =>
                ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['organization_id', 'host']
        );

        if (! $hasOrganizationHostUnique) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique(['organization_id', 'host']);
            });
        }

        if (! Schema::hasColumn($tableName, 'verified_host')) {
            // TiDB does not support adding generated stored columns through ALTER TABLE.
            // Use a normal nullable column and backfill hosts that are already verified.
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('verified_host')->nullable();
            });
        }

        DB::table($tableName)
            ->whereNotNull('verified_at')
            ->whereNull('verified_host')
            ->update(['verified_host' => DB::raw('host')]);

        $indexes = Schema::getIndexes($tableName);
        $hasVerifiedHostUnique = collect($indexes)->contains(
            fn (array $index): bool =>
                ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['verified_host']
        );

        if (! $hasVerifiedHostUnique) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique('verified_host');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'school_domains';

        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach (Schema::getIndexes($tableName) as $index) {
            $columns = $index['columns'] ?? [];

            if (($index['unique'] ?? false)
                && $columns === ['verified_host']
                && ! empty($index['name'])) {
                Schema::table($tableName, function (Blueprint $table) use ($index): void {
                    $table->dropUnique($index['name']);
                });
            }

            if (($index['unique'] ?? false)
                && $columns === ['organization_id', 'host']
                && ! empty($index['name'])) {
                Schema::table($tableName, function (Blueprint $table) use ($index): void {
                    $table->dropUnique($index['name']);
                });
            }
        }

        if (Schema::hasColumn($tableName, 'verified_host')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('verified_host');
            });
        }

        $indexes = Schema::getIndexes($tableName);
        $hasHostUnique = collect($indexes)->contains(
            fn (array $index): bool =>
                ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['host']
        );

        if (! $hasHostUnique) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique('host');
            });
        }
    }
};
