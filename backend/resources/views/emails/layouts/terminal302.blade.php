<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Terminal302' }}</title>
</head>
<body style="margin:0; padding:0; background:#f4f6fb; color:#001233; font-family:Arial, Helvetica, sans-serif;">
    @php
        $logoPath = public_path('logo.png');
        $logoSrc = isset($message) && file_exists($logoPath) ? $message->embed($logoPath) : null;
    @endphp

    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
        {{ $preheader ?? 'Notificacion de Terminal302' }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; background:#f4f6fb;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; max-width:640px;">
                    <tr>
                        <td style="background:#001233; border-radius:18px 18px 0 0; padding:28px 32px; text-align:center;">
                            @if ($logoSrc)
                                <img src="{{ $logoSrc }}" alt="Terminal 302" width="150" style="display:block; margin:0 auto 12px; width:150px; max-width:70%; height:auto;">
                            @endif
                            <div style="color:#ffffff; font-size:24px; font-weight:800; letter-spacing:0.5px;">
                                Terminal302
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff; border:1px solid #d8deea; border-top:0; padding:32px; border-radius:0 0 18px 18px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 24px 0; text-align:center; color:#64748b; font-size:13px; line-height:1.6;">
                            Este mensaje fue enviado automaticamente por Terminal302.<br>
                            Por favor, no respondas a este correo.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
