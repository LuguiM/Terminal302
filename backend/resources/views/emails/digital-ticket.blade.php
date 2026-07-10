@php
    $route = $ticket->ventaHorario?->horario?->ruta;
    $routeLabel = trim(($route?->ruta ?? '').' '.($route?->denominacion ?? ''));
    $departure = $ticket->ventaHorario?->horario?->hora_salida;
@endphp

@extends('emails.layouts.terminal302', [
    'title' => 'Tu ticket digital de Terminal302',
    'preheader' => 'Tu ticket digital fue emitido y va adjunto como imagen PNG.',
])

@section('content')
    <h1 style="margin:0 0 12px; color:#001233; font-size:28px; line-height:1.2; font-weight:800; text-align:center;">
        Tu ticket digital esta listo
    </h1>

    <p style="margin:0 0 24px; color:#33415c; font-size:16px; line-height:1.6; text-align:center;">
        Adjuntamos tu ticket como imagen PNG. Puedes descargarlo, guardarlo o imprimirlo.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:0 0 24px; border:1px solid #d8deea; border-radius:14px;">
        <tr>
            <td style="padding:18px 20px; border-bottom:1px solid #e6ebf3;">
                <div style="color:#64748b; font-size:13px; font-weight:700; text-transform:uppercase;">Codigo</div>
                <div style="color:#001233; font-size:18px; font-weight:800; word-break:break-word;">{{ $ticket->codigo_ticket }}</div>
            </td>
        </tr>
        <tr>
            <td style="padding:18px 20px; border-bottom:1px solid #e6ebf3;">
                <div style="color:#64748b; font-size:13px; font-weight:700; text-transform:uppercase;">Ruta</div>
                <div style="color:#001233; font-size:16px; font-weight:700;">{{ $routeLabel ?: 'No disponible' }}</div>
            </td>
        </tr>
        <tr>
            <td style="padding:18px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                    <tr>
                        <td width="50%" style="padding-right:10px;">
                            <div style="color:#64748b; font-size:13px; font-weight:700; text-transform:uppercase;">Salida</div>
                            <div style="color:#001233; font-size:16px; font-weight:700;">{{ $departure ?? 'No disponible' }}</div>
                        </td>
                        <td width="50%" style="padding-left:10px;">
                            <div style="color:#64748b; font-size:13px; font-weight:700; text-transform:uppercase;">Asiento</div>
                            <div style="color:#001233; font-size:16px; font-weight:700;">{{ $ticket->numero_asiento ?? 'No disponible' }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0; padding:16px 18px; background:#eef6ff; border:1px solid #cfe4ff; border-radius:12px; color:#023e7d; font-size:15px; line-height:1.6; text-align:center;">
        Presenta el codigo QR de la imagen adjunta al abordar.
    </p>
@endsection
