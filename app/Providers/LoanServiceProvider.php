<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\LoanService;
use Illuminate\Support\ServiceProvider;

class LoanServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('loanservice', fn () => new LoanService());
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
