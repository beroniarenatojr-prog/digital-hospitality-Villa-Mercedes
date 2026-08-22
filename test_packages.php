<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$package = App\Models\Facility::where('slug', 'overnight-package')->first();
echo "Components: " . $package->packageComponents()->count() . PHP_EOL;

$component = $package->packageComponents()->first()->componentFacility;
echo "Part of packages: " . $component->partOfPackages()->count() . PHP_EOL;
