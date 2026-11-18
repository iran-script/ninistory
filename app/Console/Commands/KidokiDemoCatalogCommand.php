<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\KidokiDemoCatalogSeeder;
use Illuminate\Console\Command;

class KidokiDemoCatalogCommand extends Command
{
    protected $signature = 'kidoki:demo-catalog {--dry-run : Report planned records without writing} {--seed : Safely create or update the Kidoki preview catalog} {--rollback : Remove only unreferenced Kidoki preview records} {--backup-verified : Confirm a fresh database and storage backup exists for rollback}';

    protected $description = 'Preview, seed, or selectively remove the Kidoki zero-stock demo catalog';

    public function handle(KidokiDemoCatalogSeeder $seeder): int
    {
        $modes = collect(['dry-run', 'seed', 'rollback'])->filter(fn (string $mode): bool => (bool) $this->option($mode));
        if ($modes->count() !== 1) {
            $this->error('Choose exactly one of --dry-run, --seed, or --rollback.');

            return self::INVALID;
        }

        if ($this->option('dry-run')) {
            $this->table(['Metric', 'Count'], collect($seeder->plan())->map(fn (int $count, string $metric): array => [$metric, $count])->all());
            $this->line('Current preview records: '.Product::query()->where('slug', 'like', KidokiDemoCatalogSeeder::CATEGORY_PREFIX.'%')->where('brand', KidokiDemoCatalogSeeder::PRODUCT_BRAND)->count().' products, '.Category::query()->where('slug', 'like', KidokiDemoCatalogSeeder::CATEGORY_PREFIX.'%')->count().' categories, '.ProductVariant::query()->where('sku', 'like', 'DEMO-KIDOKI-%')->count().' variants.');

            return self::SUCCESS;
        }

        if (! app()->environment('production') || ! config('demo_catalog.public_preview')) {
            $this->error('Seed and rollback require production with DEMO_CATALOG_PUBLIC_PREVIEW=true.');

            return self::FAILURE;
        }

        if ($this->option('seed')) {
            $result = $seeder->seedCatalog();
            $this->table(['Metric', 'Result'], collect($result)->map(fn (int $count, string $metric): array => [$metric, $count])->all());

            return self::SUCCESS;
        }

        if (! $this->option('backup-verified')) {
            $this->error('Rollback requires --backup-verified after a fresh database and storage backup.');

            return self::FAILURE;
        }
        if (! $this->confirm('Remove only unreferenced DEMO-KIDOKI records? Referenced demo products will be deactivated.', false)) {
            $this->info('No changes made.');

            return self::SUCCESS;
        }

        $result = $seeder->rollbackCatalog();
        $this->table(['Metric', 'Result'], collect($result)->map(fn (int $count, string $metric): array => [$metric, $count])->all());

        return self::SUCCESS;
    }
}
