<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\AiFeedbackController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\MonitoringPointController;
use App\Http\Controllers\ObservationReportController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\PhotoAnalysisController;
use App\Http\Controllers\PhotoComparisonController;
use App\Http\Controllers\PhotoNoteController;
use App\Http\Controllers\PhotoRecordController;
use App\Http\Controllers\PhotoRecordImageController;
use App\Http\Controllers\Research\EvaluationItemController;
use App\Http\Controllers\Research\EvaluationRunController;
use App\Http\Controllers\Research\WorkspaceController;
use App\Http\Middleware\EnsureResearcher;

Route::get('/', function () {
    return redirect()->route('overview');
});

Route::middleware('guest')->group(function() {
    
    //AUTH ROUTES
    Route::get('/login',             [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',            [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register',          [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',         [AuthController::class, 'register'])->middleware('throttle:register');
    
});

Route::middleware('auth')->group(function() {
    
    Route::post('/logout',           [AuthController::class, 'logout'])->name('logout');

    //RISE ROUTES (every point, record and photo is checked against its owner in the controllers)
    Route::get('/overview',               OverviewController::class)->name('overview');
    Route::resource('points',             MonitoringPointController::class)->except('index');
    Route::get('/points/{point}/compare', ComparisonController::class)->name('points.compare');
    Route::get('/points/{point}/report',  ObservationReportController::class)->name('points.report');
    Route::patch('/points/{point}/follow-up',  [FollowUpController::class, 'update'])->name('points.follow-up.update');
    Route::delete('/points/{point}/follow-up', [FollowUpController::class, 'destroy'])->name('points.follow-up.destroy');
    Route::get('/records/create',         [PhotoRecordController::class, 'create'])->name('records.create');
    Route::post('/records',               [PhotoRecordController::class, 'store'])->name('records.store');
    Route::delete('/records/{record}',    [PhotoRecordController::class, 'destroy'])->name('records.destroy');
    Route::get('/records/{record}/photo', PhotoRecordImageController::class)->name('records.photo');
    Route::get('/records/{record}/analysis',        [PhotoAnalysisController::class, 'show'])->name('records.analysis.show');
    Route::post('/records/{record}/analysis',       [PhotoAnalysisController::class, 'store'])->middleware('throttle:ai-requests')->name('records.analysis.store');
    Route::get('/records/{record}/analysis/status', [PhotoAnalysisController::class, 'status'])->name('records.analysis.status');
    Route::patch('/records/{record}/note',          PhotoNoteController::class)->name('records.note.update');
    Route::post('/points/{point}/ai-comparisons',   [PhotoComparisonController::class, 'store'])->middleware('throttle:ai-requests')->name('points.ai-comparisons.store');
    Route::get('/ai-comparisons/{comparison}/status',    [PhotoComparisonController::class, 'status'])->name('ai-comparisons.status');
    Route::patch('/ai-comparisons/{comparison}/dismiss', [PhotoComparisonController::class, 'dismiss'])->name('ai-comparisons.dismiss');
    Route::put('/analyses/{analysis}/feedback',          [AiFeedbackController::class, 'analysis'])->middleware('throttle:ai-feedback')->name('analyses.feedback');
    Route::put('/ai-comparisons/{comparison}/feedback',  [AiFeedbackController::class, 'comparison'])->middleware('throttle:ai-feedback')->name('ai-comparisons.feedback');

    //CAPSTONE EVALUATION WORKSPACE (researcher accounts only, see README.md; anyone else gets a 404)
    Route::middleware(EnsureResearcher::class)->prefix('research')->name('research.')->group(function () {
        Route::get('/',                    WorkspaceController::class)->name('index');
        Route::get('/images',              [EvaluationItemController::class, 'index'])->name('items.index');
        Route::post('/images',             [EvaluationItemController::class, 'store'])->middleware('throttle:research')->name('items.store');
        Route::get('/images/{item}',       [EvaluationItemController::class, 'edit'])->name('items.edit');
        Route::put('/images/{item}',       [EvaluationItemController::class, 'update'])->middleware('throttle:research')->name('items.update');
        Route::delete('/images/{item}',    [EvaluationItemController::class, 'destroy'])->middleware('throttle:research')->name('items.destroy');
        Route::get('/images/{item}/file',  [EvaluationItemController::class, 'image'])->name('items.image');
        Route::post('/runs',               [EvaluationRunController::class, 'store'])->middleware('throttle:research-runs')->name('runs.store');
        Route::get('/runs/{run}',          [EvaluationRunController::class, 'show'])->name('runs.show');
        Route::get('/runs/{run}/status',   [EvaluationRunController::class, 'status'])->name('runs.status');
        Route::get('/runs/{run}/export',   [EvaluationRunController::class, 'export'])->name('runs.export');
    });

    //NOTE ROUTES
    Route::get('/notes',             [NoteController::class, 'index'])->name('notes.index');
    Route::get('/notes/create',      [NoteController::class, 'create'])->name('notes.create');
    Route::post('/notes',            [NoteController::class, 'store'])->name('notes.store');
    Route::get('/notes/{note}/edit', [NoteController::class, 'edit'])->name('notes.edit');
    Route::put('/notes/{note}',      [NoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}',   [NoteController::class, 'destroy'])->name('notes.destroy');
    
});