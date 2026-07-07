<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket digital</title>
</head>
<body>
    <h1>Tu ticket digital de Terminal302</h1>

    <p>Hola, adjuntamos tu ticket digital como imagen PNG.</p>

    <p>
        <strong>Codigo:</strong> {{ $ticket->codigo_ticket }}<br>
        <strong>Ruta:</strong>
        {{ $ticket->ventaHorario?->horario?->ruta?->ruta }}
        {{ $ticket->ventaHorario?->horario?->ruta?->denominacion }}<br>
        <strong>Salida:</strong> {{ $ticket->ventaHorario?->horario?->hora_salida }}<br>
        <strong>Asiento:</strong> {{ $ticket->numero_asiento }}
    </p>

    <p>Presenta el codigo QR de la imagen adjunta al abordar. Tambien puedes descargarla o imprimirla.</p>
</body>
</html>
