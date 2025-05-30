<?php
/*
 *
 *  * Copyright © ${YEAR} SAGE Counseling, Inc.
 *  * All rights reserved.
 *  *
 *  * This file is part SAGE Counseling, Inc. internal software systems.
 *  * Unauthorized use, reproduction, or distribution is strictly prohibited.
 *
 *
 */

namespace Sage\Credible;

use Illuminate\Support\ServiceProvider;
use Sage\Credible\Console\Commands\CompareCredibleServicesCommand;

class CredibleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/credible.php', 'credible'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/credible.php' => config_path('credible.php'),
        ], 'credible-config');

    }
}
