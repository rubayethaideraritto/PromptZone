<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Required for $this->authorize(): Laravel 11+ no longer ships this trait
    // on the base controller.
    use AuthorizesRequests;
}
