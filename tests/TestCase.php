<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Vite as ViteFacade;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ViteFacade::swap(new class extends Vite {
            protected function manifest($buildDirectory): array
            {
                return [
                    'resources/scss/app.scss'   => ['file' => 'assets/app.scss.css', 'src' => 'resources/scss/app.scss'],
                    'resources/scss/icons.scss' => ['file' => 'assets/icons.scss.css', 'src' => 'resources/scss/icons.scss'],
                    'resources/css/app.css'     => ['file' => 'assets/app.css.css', 'src' => 'resources/css/app.css'],
                    'resources/js/app.js'       => ['file' => 'assets/app.js.js', 'src' => 'resources/js/app.js'],
                    'resources/js/pages/datatable.init.js' => ['file' => 'assets/datatable.init.js.js', 'src' => 'resources/js/pages/datatable.init.js'],
                    'node_modules/datatables.net-bs5/css/dataTables.bootstrap5.min.css' => ['file' => 'assets/dataTables.bootstrap5.min.css.css', 'src' => 'node_modules/datatables.net-bs5/css/dataTables.bootstrap5.min.css'],
                    'node_modules/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css' => ['file' => 'assets/responsive.bootstrap5.min.css.css', 'src' => 'node_modules/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css'],
                ];
            }
        });
    }
}