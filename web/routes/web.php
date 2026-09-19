<?php

declare(strict_types=1);

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FinancialAccountController;
use App\Http\Controllers\MonthlyClosureController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\MovementExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\RecurrenceController;
use App\Http\Controllers\RecurrenceRecoveryNoticeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/registro', [RegisterController::class, 'create'])->name('register');
    Route::post('/registro', [RegisterController::class, 'store'])->middleware('throttle:5,1');

    Route::get('/acceder', [LoginController::class, 'create'])->name('login');
    Route::post('/acceder', [LoginController::class, 'store'])->middleware('throttle:5,1');

    Route::get('/contrasena/olvidada', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/contrasena/olvidada', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:3,1')
        ->name('password.email');
    Route::get('/contrasena/restablecer/{token}', [ResetPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/contrasena/restablecer', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', [ProjectController::class, 'index'])->name('dashboard');
    Route::get('/perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/perfil/nombre', [ProfileController::class, 'updateName'])->name('profile.name.update');
    Route::patch('/perfil/correo', [ProfileController::class, 'updateEmail'])->name('profile.email.update');
    Route::put('/perfil/contrasena', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::delete('/perfil/sesiones', [ProfileController::class, 'destroyOtherSessions'])->name('profile.sessions.destroy-others');
    Route::delete('/perfil/sesiones/{sessionId}', [ProfileController::class, 'destroySession'])->name('profile.sessions.destroy');
    Route::get('/proyectos/nuevo', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/proyectos', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/proyectos/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/proyectos/{project}/configuracion', [ProjectController::class, 'settings'])->name('projects.settings');
    Route::patch('/proyectos/{project}/configuracion', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('/proyectos/{project}/archivar', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::post('/proyectos/{project}/reactivar', [ProjectController::class, 'restore'])->name('projects.restore');
    Route::get('/proyectos/{project}/miembros', [ProjectMemberController::class, 'index'])
        ->name('project-members.index');
    Route::post('/proyectos/{project}/miembros', [ProjectMemberController::class, 'store'])
        ->name('project-members.store');
    Route::patch('/proyectos/{project}/miembros/{membership}', [ProjectMemberController::class, 'update'])
        ->name('project-members.update');
    Route::delete('/proyectos/{project}/miembros/{membership}', [ProjectMemberController::class, 'destroy'])
        ->name('project-members.destroy');
    Route::get('/proyectos/{project}/categorias', [CategoryController::class, 'index'])
        ->name('categories.index');
    Route::post('/proyectos/{project}/categorias', [CategoryController::class, 'store'])
        ->name('categories.store');
    Route::get('/proyectos/{project}/categorias/{category}/editar', [CategoryController::class, 'edit'])
        ->name('categories.edit');
    Route::patch('/proyectos/{project}/categorias/{category}', [CategoryController::class, 'update'])
        ->name('categories.update');
    Route::post('/proyectos/{project}/categorias/{category}/archivar', [CategoryController::class, 'archive'])
        ->name('categories.archive');
    Route::post('/proyectos/{project}/categorias/{category}/restaurar', [CategoryController::class, 'restore'])
        ->name('categories.restore');
    Route::post('/proyectos/{project}/categorias/{category}/mover', [CategoryController::class, 'move'])
        ->name('categories.move');
    Route::get('/proyectos/{project}/presupuestos', [BudgetController::class, 'index'])
        ->name('budgets.index');
    Route::put('/proyectos/{project}/presupuestos', [BudgetController::class, 'update'])
        ->name('budgets.update');
    Route::get('/proyectos/{project}/presupuestos/cierre', [MonthlyClosureController::class, 'show'])
        ->name('budgets.closure');
    Route::post('/proyectos/{project}/presupuestos/cierre', [MonthlyClosureController::class, 'store'])
        ->name('budgets.closure.store');
    Route::get('/proyectos/{project}/informes/mensual', [ReportController::class, 'monthly'])
        ->name('reports.monthly');
    Route::get('/proyectos/{project}/informes/anual', [ReportController::class, 'annual'])
        ->name('reports.annual');
    Route::get('/proyectos/{project}/informes/comparar', [ReportController::class, 'compare'])
        ->name('reports.compare');
    Route::get('/proyectos/{project}/cuentas', [FinancialAccountController::class, 'index'])
        ->name('financial-accounts.index');
    Route::post('/proyectos/{project}/cuentas', [FinancialAccountController::class, 'store'])
        ->name('financial-accounts.store');
    Route::get('/proyectos/{project}/cuentas/{account}/editar', [FinancialAccountController::class, 'edit'])
        ->name('financial-accounts.edit');
    Route::patch('/proyectos/{project}/cuentas/{account}', [FinancialAccountController::class, 'update'])
        ->name('financial-accounts.update');
    Route::post('/proyectos/{project}/cuentas/{account}/archivar', [FinancialAccountController::class, 'archive'])
        ->name('financial-accounts.archive');
    Route::post('/proyectos/{project}/cuentas/{account}/restaurar', [FinancialAccountController::class, 'restore'])
        ->name('financial-accounts.restore');
    Route::get('/proyectos/{project}/movimientos', [MovementController::class, 'index'])
        ->name('movements.index');
    Route::get('/proyectos/{project}/movimientos/exportar', [MovementExportController::class, 'export'])
        ->name('movements.export');
    Route::get('/proyectos/{project}/movimientos/nuevo', [MovementController::class, 'create'])
        ->name('movements.create');
    Route::post('/proyectos/{project}/movimientos', [MovementController::class, 'store'])
        ->name('movements.store');
    Route::get('/proyectos/{project}/movimientos/transferencia', [MovementController::class, 'createTransfer'])
        ->name('movements.transfer.create');
    Route::post('/proyectos/{project}/movimientos/transferencia', [MovementController::class, 'storeTransfer'])
        ->name('movements.transfer.store');
    Route::get('/proyectos/{project}/movimientos/{movement}/devolucion', [MovementController::class, 'createRefund'])
        ->name('movements.refund.create');
    Route::post('/proyectos/{project}/movimientos/{movement}/devolucion', [MovementController::class, 'storeRefund'])
        ->name('movements.refund.store');
    Route::get('/proyectos/{project}/movimientos/{movement}/editar', [MovementController::class, 'edit'])
        ->name('movements.edit');
    Route::patch('/proyectos/{project}/movimientos/{movement}', [MovementController::class, 'update'])
        ->name('movements.update');
    Route::delete('/proyectos/{project}/movimientos/{movement}', [MovementController::class, 'destroy'])
        ->name('movements.destroy');
    Route::get('/proyectos/{project}/papelera', [MovementController::class, 'trash'])
        ->name('movements.trash');
    Route::get('/proyectos/{project}/papelera/exportar', [MovementExportController::class, 'trash'])
        ->name('movements.trash.export');
    Route::post('/proyectos/{project}/papelera/{movement}/restaurar', [MovementController::class, 'restore'])
        ->name('movements.restore');
    Route::get('/proyectos/{project}/recurrentes', [RecurrenceController::class, 'index'])
        ->name('recurrences.index');
    Route::get('/proyectos/{project}/recurrentes/nuevo', [RecurrenceController::class, 'create'])
        ->name('recurrences.create');
    Route::post('/proyectos/{project}/recurrentes', [RecurrenceController::class, 'store'])
        ->name('recurrences.store');
    Route::get('/proyectos/{project}/recurrentes/{recurrence}/editar', [RecurrenceController::class, 'edit'])
        ->name('recurrences.edit');
    Route::put('/proyectos/{project}/recurrentes/{recurrence}', [RecurrenceController::class, 'update'])
        ->name('recurrences.update');
    Route::post('/proyectos/{project}/recurrentes/{recurrence}/pausar', [RecurrenceController::class, 'pause'])
        ->name('recurrences.pause');
    Route::post('/proyectos/{project}/recurrentes/{recurrence}/reanudar', [RecurrenceController::class, 'resume'])
        ->name('recurrences.resume');
    Route::post('/proyectos/{project}/recurrentes/{recurrence}/omitir', [RecurrenceController::class, 'skip'])
        ->name('recurrences.skip');
    Route::get('/proyectos/{project}/objetivos', [SavingsGoalController::class, 'index'])
        ->name('savings-goals.index');
    Route::post('/proyectos/{project}/objetivos', [SavingsGoalController::class, 'store'])
        ->name('savings-goals.store');
    Route::get('/proyectos/{project}/objetivos/{goal}/editar', [SavingsGoalController::class, 'edit'])
        ->name('savings-goals.edit');
    Route::put('/proyectos/{project}/objetivos/{goal}', [SavingsGoalController::class, 'update'])
        ->name('savings-goals.update');
    Route::post('/proyectos/{project}/objetivos/{goal}/archivar', [SavingsGoalController::class, 'archive'])
        ->name('savings-goals.archive');
    Route::get('/proyectos/{project}/objetivos/{goal}/movimiento', [SavingsGoalController::class, 'contribute'])
        ->name('savings-goals.contribute');
    Route::post('/proyectos/{project}/avisos-recurrencia/{notice}/revisado', [RecurrenceRecoveryNoticeController::class, 'dismiss'])
        ->name('recurrence-notices.dismiss');
    Route::get('/proyectos/{project}/etiquetas', [TagController::class, 'index'])
        ->name('tags.index');
    Route::post('/proyectos/{project}/etiquetas', [TagController::class, 'store'])
        ->name('tags.store');
    Route::patch('/proyectos/{project}/etiquetas/{tag}', [TagController::class, 'update'])
        ->name('tags.update');
    Route::post('/proyectos/{project}/etiquetas/{tag}/archivar', [TagController::class, 'archive'])
        ->name('tags.archive');
    Route::post('/proyectos/{project}/etiquetas/{tag}/restaurar', [TagController::class, 'restore'])
        ->name('tags.restore');
    Route::post('/proyectos/{project}/etiquetas/{tag}/fusionar', [TagController::class, 'merge'])
        ->name('tags.merge');
    Route::get('/proyectos/{project}/auditoria', [AuditLogController::class, 'index'])
        ->name('audit-logs.index');
    Route::post('/salir', [LoginController::class, 'destroy'])->name('logout');
});
