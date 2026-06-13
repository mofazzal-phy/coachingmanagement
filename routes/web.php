<?php
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => response()->json([
    'app' => config('app.name'),
    'api_base' => url('/api/v1'),
], 200, [], JSON_UNESCAPED_SLASHES));