<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidatePostSize as Middleware;

class ValidatePostSize extends Middleware
{
    /**
     * The name of the field from the request to determine the post size.
     *
     * @var string|null
     */
    protected $postMaxSizeField = 'post_max_size';
}
