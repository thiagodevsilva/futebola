<?php

use App\Http\Controllers\Api\FixtureController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\SourceController;
use App\Http\Controllers\Api\StandingController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{id}', [NewsController::class, 'show']);
Route::get('/standings', [StandingController::class, 'index']);
Route::get('/fixtures/home-rounds', [FixtureController::class, 'homeRounds']);
Route::get('/fixtures', [FixtureController::class, 'index']);
Route::get('/sources', [SourceController::class, 'index']);
Route::get('/sources/topics', [SourceController::class, 'topics']);
Route::get('/teams/serie-a', [TeamController::class, 'serieA']);
