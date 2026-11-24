<?php

namespace Tests\Feature;

use Tests\TestCase;

class ViewCachePathTest extends TestCase
{
    public function test_request_views_use_the_same_cache_key_as_artisan_precompilation(): void
    {
        $finder = app('view.finder');
        $compiler = app('blade.compiler');

        foreach (['admin.reports.index', 'admin.dashboard', 'layouts.customer', 'components.admin.panel'] as $name) {
            $runtimePath = $finder->find($name);
            $precompilePath = realpath($runtimePath);

            $this->assertSame($precompilePath, $runtimePath);
            $this->assertSame($compiler->getCompiledPath($precompilePath), $compiler->getCompiledPath($runtimePath));
        }
    }

    public function test_namespaced_views_also_resolve_to_the_precompiled_path(): void
    {
        $finder = app('view.finder');
        $finder->addNamespace('performance-test', resource_path('views/components/admin'));

        $this->assertSame(
            realpath(resource_path('views/components/admin/panel.blade.php')),
            $finder->find('performance-test::panel'),
        );
    }
}
