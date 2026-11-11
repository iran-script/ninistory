<?php

namespace App\Console\Commands;

use App\Models\{Category, Product};
use Illuminate\Console\Command;

class DemoCatalogCommand extends Command
{
    protected $signature = 'demo:catalog {--seed : Create or update the local demo catalog} {--clean : Safely remove unreferenced demo records}';
    protected $description = 'Seed or safely clean the opt-in local NiniStory demo catalog';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']) || ! config('demo_catalog.enabled')) { $this->error('Demo catalog is fail-closed. Use only local/testing with DEMO_CATALOG_ENABLED=true.'); return self::FAILURE; }
        if ($this->option('seed') === $this->option('clean')) { $this->error('Choose exactly one of --seed or --clean.'); return self::INVALID; }
        if ($this->option('seed')) { $this->call('db:seed', ['--class' => 'Database\\Seeders\\CatalogDemoSeeder', '--force' => true]); return self::SUCCESS; }
        $deleted = 0; $preserved = 0;
        foreach (Product::query()->where('slug', 'like', config('demo_catalog.prefix').'%')->get() as $product) {
            $referenced = $product->variants()->whereHas('orderItems')->exists() || $product->variants()->whereHas('reservations')->exists() || $product->variants()->whereHas('inventoryMovements')->exists();
            if ($referenced) { $product->update(['is_active' => false, 'is_featured' => false]); $product->variants()->update(['is_active' => false]); $preserved++; continue; }
            $product->delete(); $deleted++;
        }
        Category::query()->where('slug', 'like', config('demo_catalog.prefix').'%')->orderByDesc('parent_id')->get()->each(function (Category $category) use (&$deleted): void { if (! $category->products()->exists() && ! $category->children()->exists()) { $category->delete(); $deleted++; } });
        $this->info("Removed {$deleted} unreferenced demo record(s); preserved {$preserved} referenced product(s) as inactive."); return self::SUCCESS;
    }
}
