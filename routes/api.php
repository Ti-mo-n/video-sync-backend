<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\VideoController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Sync Endpoints
Route::post('/sync', [SyncController::class, 'createSession']);
Route::get('/sync/{session}', [SyncController::class, 'getSession']);
Route::put('/sync/{session}', [SyncController::class, 'updateSession']);

// Video Management
Route::apiResource('videos', VideoController::class)->only(['index', 'store']);

Route::post('videos-test', function(Request $request) {
    return response()->json([
        'received_url' => $request->url(),
        'request_path' => $request->path()
    ]);
});