<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull as Middleware;

class ConvertEmptyStringsToNull extends Middleware
{
    /**
     * All of the registered string replacements.
     *
     * @var array
     */
    protected $replacements = [
        //
    ];
}
