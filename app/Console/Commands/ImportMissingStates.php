<?php

namespace App\\Console\\Commands;

use Illuminate\\Console\\Command;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;
use RuntimeException;

class ImportMissingStates extends Command
{
    protected $signature = 'world:import-missing-states';

    protected $description = 'Import missing country states without deleting existing reference data.';

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $connection = config('world.connection') ?: config('database.default');
        $countriesTable = config('world.migrations.countries.table_name', 'countries');
        $statesTable = config('world.migrations.states.table_name', 'states');
        $schema = Schema::connection($connection);
        $database = DB::connection($connection);

        if (!$schema->hasTable($countriesTable) || !$schema->hasTable($statesTable)) {
            $this->error('The countries/states tables do not exist yet. Run database migrations first.');

            return self::FAILURE;
        }

        $jsonPath = base_path('vendor/nnjeim/world/resources/json/states.json');

        if (!is_file($jsonPath)) {
            $this->error('The states data file was not found: '.$jsonPath);

            return self::FAILURE;
        }

        $statesData = json_decode(file_get_contents($jsonPath), true);

        if (!is_array($statesData)) {
            $this->error('The states data file could not be read.');

            return self::FAILURE;
        }

        $countryIds = $database->table($countriesTable)
            ->pluck('id', 'iso2')
            ->mapWithKeys(fn ($id, $iso2) => [strtoupper((string) $iso2) => (int) $id])
            ->all();

        $existingKeys = [];
        foreach ($database->table($statesTable)->select(['country_id', 'name'])->get() as $state) {
            $existingKeys[(int) $state->country_id.'|'.mb_strtolower(trim((string) $state->name))] = true;
        }

        $columns = array_flip($schema->getColumnListing($statesTable));
        unset($columns['id'], $columns['created_at'], $columns['updated_at']);

        $missing = [];
        foreach ($statesData as $state) {
            $countryCode = strtoupper((string) ($state['country_code'] ?? ''));
            $countryId = $countryIds[$countryCode] ?? null;
            $name = trim((string) ($state['name'] ?? ''));

            if ($countryId === null || $name === '') {
                continue;
            }

            $key = $countryId.'|'.mb_strtolower($name);
            if (isset($existingKeys[$key])) {
                continue;
            }

            $row = array_intersect_key($state, $columns);
            $row['country_id'] = $countryId;
            $row = array_intersect_key($row, $columns);

            if (!isset($row['name'])) {
                continue;
            }

            $missing[] = $row;
            $existingKeys[$key] = true;
        }

        foreach (array_chunk($missing, 400) as $chunk) {
            $database->table($statesTable)->insert($chunk);
        }

        $usCountryId = $countryIds['US'] ?? null;
        $usCount = $usCountryId === null
            ? 0
            : $database->table($statesTable)->where('country_id', $usCountryId)->count();

        $this->info('Imported '.$this->formatCount(count($missing)).' missing states.');
        $this->info('United States state records currently available: '.$usCount.'.');

        if ($usCount === 0) {
            throw new RuntimeException('No United States states were found after importing the reference data.');
        }

        return self::SUCCESS;
    }

    private function formatCount(int $count): string
    {
        return number_format($count);
    }
}
