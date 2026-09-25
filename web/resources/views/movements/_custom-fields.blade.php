@php
    $valuesByDefinition = ($customFieldValues ?? collect())->keyBy('custom_field_definition_id');
    $hasCustomFields = ($customFieldDefinitions ?? collect())->isNotEmpty();
    $hasSavedValues = $valuesByDefinition->isNotEmpty();
@endphp

@if ($hasCustomFields)
    <details class="custom-fields-panel field--wide" data-custom-fields @if($errors->has('custom_fields') || $errors->has('custom_fields.*') || $hasSavedValues) open @endif>
        <summary class="custom-fields-panel__summary">
            <span><strong>Información adicional</strong><small>Campos opcionales configurados para este proyecto</small></span>
            <span class="badge">{{ $customFieldDefinitions->whereNull('archived_at')->count() }}</span>
        </summary>
        <div class="custom-fields-panel__grid">
            @foreach ($customFieldDefinitions as $definition)
                @php
                    $savedValue = $valuesByDefinition->get($definition->id);
                    $historical = $definition->isArchived() && $savedValue !== null;
                    $defaultValue = $savedValue?->displayValue() ?? '';
                    if ($savedValue && $definition->type === \App\Enums\CustomFieldType::Date) {
                        $defaultValue = $savedValue->value_date?->toDateString() ?? '';
                    } elseif ($savedValue && $definition->type === \App\Enums\CustomFieldType::Boolean) {
                        $defaultValue = $savedValue->value_boolean ? '1' : '0';
                    }
                    $fieldValue = old('custom_fields.'.$definition->id, $defaultValue);
                @endphp
                <div
                    class="field custom-field-input {{ $historical ? 'custom-field-input--historical' : '' }}"
                    data-custom-field
                    data-custom-field-types="{{ implode(' ', $definition->applicable_movement_types) }}"
                    @if($historical) data-custom-field-historical @endif
                >
                    <label class="field__label" for="custom-field-{{ $definition->id }}">
                        {{ $definition->name }}
                        <span class="field__optional">opcional</span>
                    </label>
                    @if ($historical)
                        <div class="custom-field-input__historical" id="custom-field-{{ $definition->id }}">
                            <span>{{ $savedValue->displayValue() }}</span><small>Campo archivado · solo lectura</small>
                        </div>
                    @elseif ($definition->type === \App\Enums\CustomFieldType::Text)
                        <input class="field__control" id="custom-field-{{ $definition->id }}" name="custom_fields[{{ $definition->id }}]" value="{{ $fieldValue }}" maxlength="255">
                    @elseif ($definition->type === \App\Enums\CustomFieldType::Number)
                        <input class="field__control" id="custom-field-{{ $definition->id }}" name="custom_fields[{{ $definition->id }}]" value="{{ $fieldValue }}" inputmode="decimal" placeholder="0,00">
                    @elseif ($definition->type === \App\Enums\CustomFieldType::Date)
                        <input class="field__control" id="custom-field-{{ $definition->id }}" name="custom_fields[{{ $definition->id }}]" value="{{ $fieldValue }}" type="date">
                    @else
                        <select class="field__control" id="custom-field-{{ $definition->id }}" name="custom_fields[{{ $definition->id }}]">
                            <option value="" @selected($fieldValue === '')>Sin indicar</option>
                            <option value="1" @selected((string) $fieldValue === '1')>Sí</option>
                            <option value="0" @selected((string) $fieldValue === '0')>No</option>
                        </select>
                    @endif
                    @error('custom_fields.'.$definition->id)<p class="field__error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
        @error('custom_fields')<p class="field__error">{{ $message }}</p>@enderror
    </details>
@endif
