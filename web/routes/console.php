<?php

use App\Actions\Audit\RecordProjectAudit;
use App\Actions\Recurrences\GenerateDueRecurrences;
use App\Models\Movement;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('smartwallet:environment', function () {
    $connection = (string) config('database.default');
    $database = (string) DB::scalar('SELECT DATABASE()');

    $this->table(['Dato', 'Valor'], [
        ['Entorno', app()->environment()],
        ['Conexión', $connection],
        ['Servidor', config("database.connections.{$connection}.host")],
        ['Base de datos', $database],
    ]);
})->purpose('Muestra el entorno y la base activa sin revelar credenciales');

Artisan::command('smartwallet:purge-trash', function (RecordProjectAudit $audit) {
    $purged = 0;
    Movement::query()
        ->with('project')
        ->whereNotNull('trashed_at')
        ->where('purge_at', '<=', now())
        ->orderByRaw('original_movement_id is null')
        ->orderBy('purge_at')
        ->each(function (Movement $movement) use ($audit, &$purged): void {
            DB::transaction(function () use ($movement, $audit, &$purged): void {
                $audit->handle($movement->project, null, 'movement', $movement->id, 'purged', $movement->auditSnapshot(), null);
                $movement->delete();
                $purged++;
            });
        });

    $this->info($purged.' movimiento(s) eliminado(s) definitivamente.');
})->purpose('Elimina de forma definitiva los movimientos que llevan 30 días en la papelera');

Artisan::command('smartwallet:generate-recurrences {--recovery}', function (GenerateDueRecurrences $generate) {
    $result = $generate->handle(createRecoveryNotice: (bool) $this->option('recovery'));
    $this->info($result['generated'].' aparición(es) recurrente(s) generada(s).');
})->purpose('Crea una sola vez cada aparición recurrente que ya ha llegado');

Schedule::command('smartwallet:purge-trash')->hourly()->withoutOverlapping();
Schedule::command('smartwallet:generate-recurrences')->everyMinute()->withoutOverlapping();
