@php($calendarManagedAutomatically = $movement?->recurrence_template_id !== null || $movement?->plannedMovement !== null)

@if($calendarManagedAutomatically)
    <div class="calendar-toggle calendar-toggle--automatic">
        <span class="calendar-toggle__icon" aria-hidden="true">✓</span>
        <span class="calendar-toggle__copy">
            <strong>Visible automáticamente en el calendario</strong>
            <small>Este movimiento está vinculado a {{ $movement->recurrence_template_id ? 'una serie recurrente' : 'una planificación con vencimiento' }} y no necesita una selección adicional.</small>
        </span>
    </div>
@else
    <label class="calendar-toggle">
        <input name="show_in_calendar" type="hidden" value="0">
        <input class="check__control" name="show_in_calendar" type="checkbox" value="1" @checked((bool) old('show_in_calendar', $movement?->show_in_calendar ?? false))>
        <span class="calendar-toggle__copy">
            <strong>Mostrar en el calendario</strong>
            <small>Añádelo solo si esta operación tiene relevancia temporal. Podrás cambiar esta opción más adelante.</small>
        </span>
    </label>
@endif
