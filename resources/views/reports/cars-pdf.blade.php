@extends('reports.layout')

@section('title', 'Reporte de inventario')
@section('heading', 'Reporte de inventario de vehículos')

@section('meta')
    Sucursal: {{ $branch ?? 'Todas' }} &middot;
    Vehículos en inventario: {{ $total_cars }}
@endsection

@section('content')
    <table class="cards">
        <tr>
            <td>
                <div class="label">Vehículos</div>
                <div class="value">{{ $total_cars }}</div>
            </td>
            <td>
                <div class="label">Valor del inventario</div>
                <div class="value">{{ $base_currency }} {{ number_format($total_price, 2) }}</div>
            </td>
        </tr>
    </table>

    <h2>Por estado</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Estado</th>
                <th class="right">Vehículos</th>
                <th class="right">Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($by_status as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="right">{{ $row['count'] }}</td>
                    <td class="right">{{ number_format($row['total_price'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="muted">No hay vehículos registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Por tipo</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Tipo</th>
                <th class="right">Vehículos</th>
                <th class="right">Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($by_type as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="right">{{ $row['count'] }}</td>
                    <td class="right">{{ number_format($row['total_price'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="muted">Sin datos por tipo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Por marca</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Marca</th>
                <th class="right">Vehículos</th>
                <th class="right">Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($by_brand as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="right">{{ $row['count'] }}</td>
                    <td class="right">{{ number_format($row['total_price'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="muted">Sin datos por marca.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
