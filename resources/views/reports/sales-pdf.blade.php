@extends('reports.layout')

@section('title', 'Reporte de ventas')
@section('heading', 'Reporte de ventas')

@section('meta')
    Desde: {{ $filters['from'] ?? 'el inicio' }} &middot;
    Hasta: {{ $filters['to'] ?? 'hoy' }} &middot;
    Sucursal: {{ $branch?->name ?? 'Todas' }}
@endsection

@section('content')
    <table class="cards">
        <tr>
            <td>
                <div class="label">Ventas</div>
                <div class="value">{{ $summary['count'] }}</div>
            </td>
            <td>
                <div class="label">Monto base</div>
                <div class="value">{{ $base_currency }} {{ number_format($summary['total_base_amount'], 2) }}</div>
            </td>
            <td>
                <div class="label">Impuestos</div>
                <div class="value">{{ $base_currency }} {{ number_format($summary['total_taxes'], 2) }}</div>
            </td>
            <td>
                <div class="label">Descuentos</div>
                <div class="value">{{ $base_currency }} {{ number_format($summary['total_discount'], 2) }}</div>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Vehículo</th>
                <th>Sucursal</th>
                <th>Pago</th>
                <th>Estado</th>
                <th class="right">Monto base</th>
                <th class="right">Total pagado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sales as $sale)
                @php
                    $status = match ($sale->status) {
                        \App\Enums\Sales\SaleStatus::COMPLETED => 'Completada',
                        \App\Enums\Sales\SaleStatus::PENDING => 'Pendiente',
                        \App\Enums\Sales\SaleStatus::CANCELLED => 'Cancelada',
                    };
                @endphp
                <tr>
                    <td>{{ $sale->id }}</td>
                    <td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y') }}</td>
                    <td>{{ $sale->user?->name }} {{ $sale->user?->last_name }}</td>
                    <td>
                        {{ $sale->car?->carModel?->brand?->name }}
                        {{ $sale->car?->carModel?->name }}
                        @if ($sale->car?->vin)
                            <span class="muted">({{ $sale->car->vin }})</span>
                        @endif
                    </td>
                    <td>{{ $sale->branch?->name }}</td>
                    <td>{{ $sale->paymentMethod?->name }}</td>
                    <td>{{ $status }}</td>
                    <td class="right">{{ number_format((float) $sale->total_base_amount, 2) }}</td>
                    <td class="right">
                        {{ $sale->paymentCurrency?->code }} {{ number_format((float) $sale->total_amount_paid, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="muted">No hay ventas para los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
