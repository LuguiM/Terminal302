<?php

use App\Services\TicketDigitalDeliveryService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tickets:process-digital-deliveries {--limit= : Cantidad maxima de eventos a procesar}', function (): int {
    $limit = $this->option('limit') !== null
        ? max((int) $this->option('limit'), 1)
        : null;

    $summary = app(TicketDigitalDeliveryService::class)->processPending($limit);

    $this->info(sprintf(
        'Procesados: %d | Completados: %d | Fallidos: %d | Omitidos: %d',
        $summary['processed'],
        $summary['completed'],
        $summary['failed'],
        $summary['skipped'],
    ));

    return $summary['failed'] > 0 ? 1 : 0;
})->purpose('Procesar entregas digitales de tickets pendientes');
