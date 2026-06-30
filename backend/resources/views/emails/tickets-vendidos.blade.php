<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Tickets emitidos</title>
</head>
<body>
    <h1>Tickets emitidos en Terminal302</h1>

    <p>Se emitieron {{ $tickets->count() }} ticket(s).</p>

    <ul>
        @foreach ($tickets as $ticket)
            <li>
                Codigo: {{ $ticket->codigo_ticket }}
                @if ($ticket->es_sobreventa)
                    (sobreventa)
                @endif
            </li>
        @endforeach
    </ul>

    <p>
        Venta horario: {{ $ventaHorario->id }}.
    </p>
</body>
</html>
