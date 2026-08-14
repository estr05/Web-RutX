<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Call withoutVite() so that feature tests that render Blade layouts
     * do not require a compiled public/build/manifest.json.
     *
     * The real Vite bundle is validated independently by the
     * `frontend-quality` job in GitHub Actions (npm run build).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
