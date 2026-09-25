@php
    $limitCents = (int) ($limits->get($category->id)?->limit_cents ?? 0);
    $categorySpent = (int) ($spentByCategory->get($category->id) ?? 0);
    $categoryPercentage = $limitCents > 0
        ? max(0, (int) round(($categorySpent / $limitCents) * 100))
        : ($categorySpent > 0 ? 100 : 0);
    $categoryProgress = min(100, $categoryPercentage);
    $childLimitCents = (int) $category->children->sum(
        fn ($child) => (int) ($limits->get($child->id)?->limit_cents ?? 0),
    );
    $unassignedCents = $limitCents - $childLimitCents;
    $withoutSubcategoryCents = (int) ($spentWithoutSubcategory->get($category->id) ?? 0);
    $openChildren = $category->children->contains(function ($child) use ($limits, $spentBySubcategory): bool {
        $childLimit = (int) ($limits->get($child->id)?->limit_cents ?? 0);
        $childSpent = (int) ($spentBySubcategory->get($child->id) ?? 0);
        $childPercentage = $childLimit > 0
            ? max(0, (int) round(($childSpent / $childLimit) * 100))
            : ($childSpent > 0 ? 100 : 0);

        return $childLimit > 0 || $childPercentage >= 80;
    });
@endphp

<article
    class="budget-category {{ $category->isArchived() ? 'budget-category--archived' : '' }}"
    data-budget-category="{{ $category->id }}"
    data-budget-category-state="{{ $categoryPercentage >= 100 ? 'exceeded' : ($categoryPercentage >= 80 ? 'warning' : 'available') }}"
>
    <div class="budget-category__identity">
        <span class="category-item__icon" style="--category-color: {{ $category->color }}" aria-hidden="true">{{ $category->iconSymbol() }}</span>
        <div>
            <span class="budget-category__title-row">
                <h3 class="budget-category__name">{{ $category->name }}</h3>
                @if ($category->isArchived())
                    <span class="badge badge--archived">Archivada</span>
                @endif
            </span>
            <p class="budget-category__spent">{{ $formatMoney($categorySpent) }} gastados</p>
        </div>
    </div>

    @if ($canManage && ! $category->isArchived())
        <div class="field budget-category__field">
            <label class="field__label" for="limit-{{ $category->id }}">Límite principal</label>
            <div class="money-field">
                <input
                    class="field__control money-field__control"
                    id="limit-{{ $category->id }}"
                    name="limits[{{ $category->id }}]"
                    inputmode="decimal"
                    value="{{ old('limits.'.$category->id, $formatInput($limitCents)) }}"
                >
                <span class="money-field__suffix">€</span>
            </div>
            @error('limits.'.$category->id)<p class="field__error">{{ $message }}</p>@enderror
        </div>
    @else
        <div class="budget-category__limit-value"><small>Límite principal</small><strong>{{ $formatMoney($limitCents) }}</strong></div>
    @endif

    <div
        class="progress-bar"
        role="progressbar"
        aria-label="Presupuesto consumido en {{ $category->name }}"
        aria-valuemin="0"
        aria-valuemax="100"
        aria-valuenow="{{ $categoryProgress }}"
        aria-valuetext="{{ $categoryPercentage }} % consumido"
    ><span class="progress-bar__value {{ $categoryPercentage >= 100 ? 'progress-bar__value--danger' : ($categoryPercentage >= 80 ? 'progress-bar__value--warning' : '') }}" style="width: {{ $categoryProgress }}%"></span></div>

    @if ($category->children->isNotEmpty())
        <details class="budget-subcategories" data-open-state="{{ $openChildren ? 'open' : 'closed' }}" @if($openChildren) open @endif>
            <summary class="budget-subcategories__summary">
                <span>
                    <strong>Subcategorías</strong>
                    <small>{{ $category->children->count() }} disponibles</small>
                </span>
                <span class="budget-subcategories__allocation {{ $unassignedCents < 0 ? 'budget-subcategories__allocation--danger' : '' }}">
                    {{ $unassignedCents < 0 ? 'Exceso asignado' : 'Sin asignar' }}: {{ $formatMoney(abs($unassignedCents)) }}
                </span>
            </summary>

            <div class="budget-subcategories__list">
                @foreach ($category->children as $child)
                    @php
                        $childLimit = (int) ($limits->get($child->id)?->limit_cents ?? 0);
                        $childSpent = (int) ($spentBySubcategory->get($child->id) ?? 0);
                        $childPercentage = $childLimit > 0
                            ? max(0, (int) round(($childSpent / $childLimit) * 100))
                            : ($childSpent > 0 ? 100 : 0);
                        $childProgress = min(100, $childPercentage);
                        $canEditChild = $canManage && ! $category->isArchived() && ! $child->isArchived();
                    @endphp
                    <article
                        class="budget-subcategory {{ $child->isArchived() ? 'budget-subcategory--archived' : '' }}"
                        data-budget-subcategory="{{ $child->id }}"
                        data-budget-subcategory-state="{{ $childPercentage >= 100 ? 'exceeded' : ($childPercentage >= 80 ? 'warning' : 'available') }}"
                    >
                        <div class="budget-subcategory__identity">
                            <span class="budget-subcategory__dot" style="--category-color: {{ $child->color }}" aria-hidden="true"></span>
                            <span>
                                <strong>{{ $child->name }}</strong>
                                <small>{{ $formatMoney($childSpent) }} gastados</small>
                            </span>
                            @if ($child->isArchived())
                                <span class="badge badge--archived">Archivada</span>
                            @endif
                        </div>

                        @if ($canEditChild)
                            <div class="field budget-subcategory__field">
                                <label class="field__label" for="limit-{{ $child->id }}">Límite</label>
                                <div class="money-field">
                                    <input
                                        class="field__control money-field__control"
                                        id="limit-{{ $child->id }}"
                                        name="limits[{{ $child->id }}]"
                                        inputmode="decimal"
                                        value="{{ old('limits.'.$child->id, $formatInput($childLimit)) }}"
                                    >
                                    <span class="money-field__suffix">€</span>
                                </div>
                                @error('limits.'.$child->id)<p class="field__error">{{ $message }}</p>@enderror
                            </div>
                        @else
                            <strong class="budget-subcategory__limit">{{ $formatMoney($childLimit) }}</strong>
                        @endif

                        <span class="budget-subcategory__percentage {{ $childPercentage >= 100 ? 'budget-subcategory__percentage--danger' : ($childPercentage >= 80 ? 'budget-subcategory__percentage--warning' : '') }}">{{ $childPercentage }}%</span>
                        <div
                            class="progress-bar budget-subcategory__progress"
                            role="progressbar"
                            aria-label="Presupuesto consumido en {{ $child->name }}"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="{{ $childProgress }}"
                            aria-valuetext="{{ $childPercentage }} % consumido"
                        ><span class="progress-bar__value {{ $childPercentage >= 100 ? 'progress-bar__value--danger' : ($childPercentage >= 80 ? 'progress-bar__value--warning' : '') }}" style="width: {{ $childProgress }}%"></span></div>
                    </article>
                @endforeach

                <div class="budget-subcategory budget-subcategory--unassigned">
                    <div class="budget-subcategory__identity">
                        <span class="budget-subcategory__dot" aria-hidden="true"></span>
                        <span><strong>Sin subcategoría</strong><small>Consume únicamente el límite principal</small></span>
                    </div>
                    <strong class="budget-subcategory__limit">{{ $formatMoney($withoutSubcategoryCents) }}</strong>
                </div>
            </div>
        </details>
    @else
        <div class="budget-subcategory budget-subcategory--unassigned budget-subcategory--standalone">
            <div class="budget-subcategory__identity">
                <span class="budget-subcategory__dot" aria-hidden="true"></span>
                <span><strong>Sin subcategoría</strong><small>Todo el gasto se imputa al límite principal</small></span>
            </div>
            <strong class="budget-subcategory__limit">{{ $formatMoney($withoutSubcategoryCents) }}</strong>
        </div>
    @endif
</article>
