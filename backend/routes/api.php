<?php

use Illuminate\Support\Facades\Route;

// All routes here are prefixed with /api (see bootstrap/app.php).
// Endpoints are added in step 4 of the README plan.

Route::get('/ping', fn () => ['status' => 'ok']);
