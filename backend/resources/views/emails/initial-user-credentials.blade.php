<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>
        @if ($purpose === \App\Mail\InitialUserCredentialsMail::PURPOSE_RESET)
            Contrasena restablecida en Terminal302
        @else
            Credenciales de acceso a Terminal302
        @endif
    </title>
</head>
<body>
    <p>Hola {{ $user->name }},</p>

    @if ($purpose === \App\Mail\InitialUserCredentialsMail::PURPOSE_RESET)
        <p>Tu contrasena de acceso a Terminal302 fue restablecida.</p>
    @else
        <p>Se ha creado tu usuario de acceso a Terminal302.</p>
    @endif

    <p>
        Correo de acceso: <strong>{{ $user->email }}</strong><br>
        Contrasena temporal: <strong>{{ $temporaryPassword }}</strong>
    </p>

    <p>Por seguridad, debes cambiar esta contrasena en tu proximo inicio de sesion.</p>

    <p>Terminal302</p>
</body>
</html>
