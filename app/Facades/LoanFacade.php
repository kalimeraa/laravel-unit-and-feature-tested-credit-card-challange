<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class LoanFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'loanservice';
    }
}