<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


//  streaming route
Route::get('/stream/{filename}', [VideoController::class, 'stream'])
     ->name('video.stream')
     ->middleware(\App\Http\Middleware\VideoStreamingMiddleware::class);