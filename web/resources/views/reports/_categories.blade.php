@php
    $categoryMoney = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €';
    $positiveCategories = $report['categories']->filter(fn ($item) => $item['expense_cents'] > 0);
    $topCategories = $positiveCategories->take(5);
    $otherCents = (int) $positiveCategories->skip(5)->sum('expense_cents');
    $categoryMax = max(1, (int) $topCategories->max('expense_cents'), $otherCents);
@endphp
<section class="content-section" aria-labelledby="category-report-title">
    <div class="section-heading"><div><p class="eyebrow">Destino del dinero</p><h2 class="section-heading__title" id="category-report-title">Gasto por categoría</h2></div></div>
    @if($report['categories']->isEmpty())
        <div class="compact-empty"><p>No hay gastos ni devoluciones con estos filtros.</p></div>
    @else
        <div class="category-chart" aria-label="Cinco categorías principales y resto">
            @foreach($topCategories as $item)
                @php($detailQuery = array_filter([...$detailBase, 'category' => $item['category_id']], fn ($value) => $value !== null))
                <a class="category-chart__row" href="{{ route('movements.index', ['project' => $project, ...$detailQuery]) }}"><span class="category-chart__label">{{ $item['name'] }}</span><span class="category-chart__track"><span class="category-chart__bar" style="--bar-width: {{ max(2, round(($item['expense_cents'] / $categoryMax) * 100, 2)) }}%; --bar-color: {{ $item['color'] }}"></span></span><strong>{{ $categoryMoney($item['expense_cents']) }}</strong></a>
            @endforeach
            @if($otherCents > 0)<div class="category-chart__row"><span class="category-chart__label">Otras</span><span class="category-chart__track"><span class="category-chart__bar" style="--bar-width: {{ max(2, round(($otherCents / $categoryMax) * 100, 2)) }}%; --bar-color: #78958e"></span></span><strong>{{ $categoryMoney($otherCents) }}</strong></div>@endif
        </div>
        <div class="movement-table-wrap report-table-wrap"><table class="movement-table report-table"><thead><tr><th>Categoría</th><th class="movement-table__amount">Gasto bruto</th><th class="movement-table__amount">Devoluciones</th><th class="movement-table__amount">Gasto neto</th></tr></thead><tbody>@foreach($report['categories'] as $item)@php($detailQuery = array_filter([...$detailBase, 'category' => $item['category_id']], fn ($value) => $value !== null))<tr><td data-label="Categoría"><a class="text-link" href="{{ route('movements.index', ['project' => $project, ...$detailQuery]) }}">{{ $item['name'] }}</a></td><td class="movement-table__amount" data-label="Gasto bruto">{{ $categoryMoney($item['gross_cents']) }}</td><td class="movement-table__amount" data-label="Devoluciones">{{ $categoryMoney($item['refund_cents']) }}</td><td class="movement-table__amount" data-label="Gasto neto"><strong>{{ $categoryMoney($item['expense_cents']) }}</strong></td></tr>@endforeach</tbody></table></div>
    @endif
</section>
