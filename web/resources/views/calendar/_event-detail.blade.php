<article class="calendar-event calendar-event--{{ $event['status'] }}">
    <div class="calendar-event__heading">
        <div class="calendar-event__badges">
            <span class="calendar-status calendar-status--{{ $event['status'] }}">{{ $event['status_label'] }}</span>
            @if($event['punctuality_label'])
                <span class="calendar-status calendar-status--punctuality">{{ $event['punctuality_label'] }}</span>
            @endif
        </div>
        <span class="movement-type movement-type--{{ $event['type'] }}">{{ $event['type_label'] }}</span>
    </div>
    <a class="calendar-event__title" href="{{ $event['url'] }}">{{ $event['concept'] }}</a>
    <strong class="calendar-event__amount calendar-event__amount--{{ $event['type'] }}">{{ $event['amount_formatted'] }}</strong>
    <dl class="calendar-event__details">
        <div><dt>Origen</dt><dd>{{ $event['source_label'] }}</dd></div>
        @if($event['category_name'])
            <div><dt>Categoría</dt><dd>{{ $event['category_name'] }}@if($event['subcategory_name']) · {{ $event['subcategory_name'] }}@endif</dd></div>
        @endif
        @if($event['account_name'])
            <div><dt>Cuenta</dt><dd>{{ $event['account_name'] }}@if($event['destination_account_name']) → {{ $event['destination_account_name'] }}@endif</dd></div>
        @endif
        @if($event['member_name'])
            <div><dt>Miembro</dt><dd>{{ $event['member_name'] }}</dd></div>
        @endif
        @if($event['effective_on'] && $event['effective_on'] !== $event['scheduled_on'])
            <div><dt>Fecha real</dt><dd>{{ \Carbon\CarbonImmutable::parse($event['effective_on'])->format('d/m/Y') }}</dd></div>
        @endif
    </dl>
    <a class="calendar-event__action" href="{{ $event['url'] }}">Abrir detalle <span aria-hidden="true">→</span></a>
</article>
