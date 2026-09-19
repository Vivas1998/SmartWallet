@php($reportMoney = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €')
<div class="report-metrics">
    <article class="report-metric report-metric--budget"><small>Presupuesto</small><strong>{{ $reportMoney($report['budget_cents']) }}</strong><span>{{ $budgetCaption ?? 'del periodo' }}</span></article>
    <article class="report-metric report-metric--expense"><small>Gasto neto</small><strong>{{ $reportMoney($report['expense_cents']) }}</strong><span>{{ $reportMoney($report['refund_cents']) }} en devoluciones</span></article>
    <article class="report-metric report-metric--income"><small>Ingresos</small><strong>{{ $reportMoney($report['income_cents']) }}</strong><span>sin ampliar el presupuesto</span></article>
    <article class="report-metric {{ $report['balance_cents'] < 0 ? 'report-metric--danger' : 'report-metric--balance' }}"><small>Balance</small><strong>{{ $reportMoney($report['balance_cents']) }}</strong><span>ingresos menos gasto neto</span></article>
    <article class="report-metric {{ $report['remaining_cents'] < 0 ? 'report-metric--danger' : 'report-metric--remaining' }}"><small>Saldo presupuestario</small><strong>{{ $reportMoney($report['remaining_cents']) }}</strong><span>{{ $remainingCaption ?? 'presupuesto menos gasto' }}</span></article>
</div>
