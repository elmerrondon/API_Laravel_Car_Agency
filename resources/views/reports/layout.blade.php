<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Reporte')</title>
    <style>
        * {
            font-family: "DejaVu Sans", sans-serif;
        }

        body {
            color: #1f2937;
            font-size: 11px;
            margin: 0;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 4px;
        }

        .muted {
            color: #6b7280;
        }

        .header {
            border-bottom: 2px solid #0f766e;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .meta {
            font-size: 10px;
        }

        .cards {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 12px;
        }

        .cards td {
            background: #f3f4f6;
            padding: 8px;
            width: 25%;
        }

        .cards .label {
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
        }

        .cards .value {
            font-size: 14px;
            font-weight: bold;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th {
            background: #0f766e;
            color: #ffffff;
            padding: 5px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
        }

        table.data td {
            border-bottom: 1px solid #e5e7eb;
            padding: 4px 5px;
        }

        table.data tr:nth-child(even) td {
            background: #f9fafb;
        }

        .right {
            text-align: right;
        }

        .footer {
            margin-top: 14px;
            font-size: 9px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>@yield('heading', 'Reporte')</h1>
        <div class="meta muted">@yield('meta')</div>
    </div>

    @yield('content')

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} &mdash; API Laravel Car Agency
    </div>
</body>
</html>
