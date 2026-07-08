@extends('emails.layouts.terminal302', [
    'title' => 'Tickets emitidos en Terminal302',
    'preheader' => 'Resumen de tickets emitidos en Terminal302.',
])

@section('content')
    <h1 style="margin:0 0 12px; color:#001233; font-size:28px; line-height:1.2; font-weight:800; text-align:center;">
        Tickets emitidos
    </h1>

    <p style="margin:0 0 24px; color:#33415c; font-size:16px; line-height:1.6; text-align:center;">
        Se emitieron <strong>{{ $tickets->count() }}</strong> ticket(s) en Terminal302.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin:0 0 24px; border:1px solid #d8deea; border-radius:14px;">
        @foreach ($tickets as $ticket)
            <tr>
                <td style="padding:14px 18px; border-bottom:{{ $loop->last ? '0' : '1px solid #e6ebf3' }};">
                    <div style="color:#001233; font-size:16px; font-weight:800; word-break:break-word;">
                        {{ $ticket->codigo_ticket }}
                    </div>
                    @if ($ticket->es_sobreventa)
                        <div style="display:inline-block; margin-top:8px; padding:5px 10px; background:#fff4e5; border-radius:999px; color:#8a4b00; font-size:12px; font-weight:800;">
                            Sobreventa
                        </div>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    <p style="margin:0; color:#64748b; font-size:14px; line-height:1.6; text-align:center;">
        Venta horario: <strong style="color:#001233;">{{ $ventaHorario->id }}</strong>
    </p>
@endsection
