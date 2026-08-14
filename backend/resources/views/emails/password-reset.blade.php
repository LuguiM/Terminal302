@extends('emails.layouts.terminal302', [
    'title' => 'Recuperación de contraseña',
    'preheader' => 'Recibimos una solicitud para cambiar tu contraseña de Terminal302.',
])

@section('content')
    <h1 style="margin:0 0 12px; color:#001233; font-size:28px; line-height:1.2; font-weight:800; text-align:center;">
        Recuperación de contraseña
    </h1>

    <p style="margin:0 0 24px; color:#33415c; font-size:16px; line-height:1.6; text-align:center;">
        Hola {{ $user->name }}, recibimos una solicitud para cambiar tu contraseña de acceso.
    </p>

    <table role="presentation" align="center" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:0 auto 24px;">
        <tr>
            <td align="center" bgcolor="#001233" style="border-radius:12px;">
                <a href="{{ $resetUrl }}" style="display:inline-block; padding:14px 28px; color:#ffffff; font-size:16px; font-weight:800; text-decoration:none;">
                    Restablecer contraseña
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 20px; color:#33415c; font-size:15px; line-height:1.6; text-align:center;">
        Este enlace vencerá en 60 minutos y solo puede utilizarse una vez.
        Si no solicitaste este cambio, puedes ignorar el mensaje.
    </p>

    <p style="margin:0; color:#64748b; font-size:13px; line-height:1.6; text-align:center; word-break:break-word;">
        Si el botón no funciona, copia este enlace en tu navegador:<br>
        <a href="{{ $resetUrl }}" style="color:#023e7d; text-decoration:underline;">{{ $resetUrl }}</a>
    </p>
@endsection
