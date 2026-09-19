@php($specialMoney = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €')
<section class="content-section" aria-labelledby="special-operations-title">
    <div class="section-heading"><div><p class="eyebrow">Sin doble contabilización</p><h2 class="section-heading__title" id="special-operations-title">Operaciones separadas</h2></div></div>
    <div class="special-operation-grid">
        <article class="special-operation"><span aria-hidden="true">↩</span><div><small>Gasto bruto / devoluciones</small><strong>{{ $specialMoney($report['expense_gross_cents']) }} / {{ $specialMoney($report['refund_cents']) }}</strong></div></article>
        <article class="special-operation"><span aria-hidden="true">↔</span><div><small>Transferencias internas</small><strong>{{ $specialMoney($report['transfer_cents']) }}</strong></div></article>
        <article class="special-operation"><span aria-hidden="true">◆</span><div><small>Inversión: aportado / retirado</small><strong>{{ $specialMoney($report['investment_contribution_cents']) }} / {{ $specialMoney($report['investment_withdrawal_cents']) }}</strong></div></article>
        <article class="special-operation"><span aria-hidden="true">◎</span><div><small>Objetivos: aportado / retirado</small><strong>{{ $specialMoney($report['savings_contribution_cents']) }} / {{ $specialMoney($report['savings_withdrawal_cents']) }}</strong></div></article>
    </div>
</section>
