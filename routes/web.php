<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\EstagioController;
use App\Http\Middleware\PrivateResponse;
use App\Http\Middleware\Professor;
use Illuminate\Support\Facades\Route;

Route::middleware(PrivateResponse::class)->group(function () {
    Route::get('/', [EstagioController::class, 'loginForm'])->name('estagio.login');
    Route::post('/entrar', [EstagioController::class, 'login'])->middleware('throttle:5,1')->name('estagio.entrar');
    Route::post('/sair', [EstagioController::class, 'logout'])->name('estagio.sair');
    Route::get('/relatorio', [EstagioController::class, 'edit'])->name('estagio.editar');
    Route::post('/relatorio', [EstagioController::class, 'save'])->middleware('throttle:60,1')->name('estagio.salvar');
    Route::get('/relatorio/pdf', [EstagioController::class, 'pdf'])->middleware('throttle:10,1')->name('estagio.pdf');
    Route::get('/relatorio/visualizar', [EstagioController::class, 'preview'])->middleware('throttle:10,1')->name('estagio.preview');
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/entrar', [AdminController::class, 'loginForm'])->name('login');
        Route::post('/entrar', [AdminController::class, 'login'])->middleware('throttle:5,1')->name('entrar');
        Route::middleware(Professor::class)->group(function () {
            Route::get('/', [AdminController::class, 'index'])->name('index');
            Route::post('/sair', [AdminController::class, 'logout'])->name('sair');
            Route::post('/importar', [AdminController::class, 'importar'])->middleware('throttle:5,1')->name('importar');
            Route::get('/alunos/{aluno}', [AdminController::class, 'show'])->name('show');
            Route::get('/alunos/{aluno}/pdf', [AdminController::class, 'pdf'])->middleware('throttle:20,1')->name('pdf');
        });
    });
});
