<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration is safe to retry because MySQL-compatible databases may
     * complete individual DDL statements before a later statement fails.
     */
    public function up(): void
    {
        $this->replaceScope('grade_categories');
        $this->replaceScope('grade_items');
        $this->replaceScope('result_snapshots');
    }

    private function replaceScope(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'course_offering_id')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('course_offering_id')->nullable();
            });
        }

        $foreignKeys = Schema::getForeignKeys($tableName);
        $hasCourseOfferingForeignKey = collect($foreignKeys)->contains(
            fn (array $key): bool => in_array('course_offering_id', $key['columns'] ?? [], true)
        );

        if (! $hasCourseOfferingForeignKey) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('course_offering_id')
                    ->references('id')
                    ->on('course_offerings')
                    ->cascadeOnDelete();
            });
        }

        $legacyNames = ['subject_id', 'academic_year_id', 'academic_period_id'];

        // Remove foreign keys first, then secondary indexes that include
        // legacy columns. TiDB refuses to drop an indexed column.
        foreach ($legacyNames as $columnName) {
            $foreignKeys = Schema::getForeignKeys($tableName);
            $foreignKey = collect($foreignKeys)->first(
                fn (array $key): bool => in_array($columnName, $key['columns'] ?? [], true)
            );

            if ($foreignKey && ! empty($foreignKey['name'])) {
                Schema::table($tableName, function (Blueprint $table) use ($foreignKey): void {
                    $table->dropForeign($foreignKey['name']);
                });
            }
        }

        foreach (Schema::getIndexes($tableName) as $index) {
            $columns = $index['columns'] ?? [];
            $usesLegacyColumn = count(array_intersect($legacyNames, $columns)) > 0;
            $isPrimary = ($index['primary'] ?? false)
                || strtoupper($index['name'] ?? '') === 'PRIMARY';

            if ($usesLegacyColumn && ! $isPrimary && ! empty($index['name'])) {
                Schema::table($tableName, function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index['name']);
                });
            }
        }

        // Never attempt to remove a column that remains in an index
        // (for example, a primary key). This keeps the migration retry-safe.
        $indexedColumns = collect(Schema::getIndexes($tableName))
            ->flatMap(fn (array $index): array => $index['columns'] ?? [])
            ->unique()
            ->all();

        $legacyColumns = array_values(array_filter(
            $legacyNames,
            fn (string $columnName): bool =>
                Schema::hasColumn($tableName, $columnName)
                && ! in_array($columnName, $indexedColumns, true)
        ));

        if ($legacyColumns !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($legacyColumns): void {
                $table->dropColumn($legacyColumns);
            });
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new LogicException('Gradebook and result records now identify their course offering directly.');
    }
};
