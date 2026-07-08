@php
    $isReset = $purpose === \App\Mail\InitialUserCredentialsMail::PURPOSE_RESET;
    $title = $isReset
        ? 'Contrasena restablecida en Terminal302'
        : 'Credenciales de acceso a Terminal302';
    $frontendUrl = config('app.frontend_url');
    $loginUrl = $frontendUrl ? rtrim($frontendUrl, '/').'/login' : null;
@endphp

@extends('emails.layouts.terminal302', [
    'title' => $title,
    'preheader' => $isReset
        ? 'Tu contrasena temporal de Terminal302 esta lista.'
        : 'Tu usuario de acceso a Terminal302 fue creado.',
])

@section('content')
    <h1 style="margin:0 0 12px; color:#001233; font-size:28px; line-height:1.2; font-weight:800; text-align:center;">
        {{ $title }}
    </h1>

    <p style="margin:0 0 24px; color:#33415c; font-size:16px; line-height:1.6; text-align:center;">
        Hola {{ $user->name }}, {{ $isReset ? 'tu contrasena de acceso fue restablecida.' : 'se ha creado tu usuario de acceso al sistema.' }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:0 0 24px; background:#f7f9fc; border:1px solid #d8deea; border-radius:14px;">
        <tr>
            <td style="padding:20px;">
                <div style="margin-bottom:14px;">
                    <div style="color:#64748b; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em;">
                        Correo de acceso
                    </div>
                    <div style="color:#001233; font-size:18px; font-weight:800; word-break:break-word;">
                        {{ $user->email }}
                    </div>
                </div>

                <div>
                    <div style="color:#64748b; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em;">
                        Contrasena temporal
                    </div>
                    <div style="display:inline-block; margin-top:6px; padding:10px 14px; background:#001233; border-radius:10px; color:#ffffff; font-size:20px; font-weight:800; letter-spacing:0.04em; word-break:break-word;">
                        {{ $temporaryPassword }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 24px; color:#33415c; font-size:15px; line-height:1.6; text-align:center;">
        Por seguridad, deberas cambiar esta contrasena en tu proximo inicio de sesion.
    </p>

    @if ($loginUrl)
        <table role="presentation" align="center" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:0 auto 24px;">
            <tr>
                <td align="center" bgcolor="#001233" style="border-radius:12px;">
                    <a href="{{ $loginUrl }}" style="display:inline-block; padding:14px 28px; color:#ffffff; font-size:16px; font-weight:800; text-decoration:none;">
                        Ir a Terminal302
                    </a>
                </td>
            </tr>
        </table>

        <p style="margin:0; color:#64748b; font-size:13px; line-height:1.6; text-align:center; word-break:break-word;">
            Si el boton no funciona, copia este enlace en tu navegador:<br>
            <a href="{{ $loginUrl }}" style="color:#023e7d; text-decoration:underline;">{{ $loginUrl }}</a>
        </p>
    @endif
@endsection
