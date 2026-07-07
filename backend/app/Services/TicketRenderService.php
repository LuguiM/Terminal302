<?php

namespace App\Services;

use App\Models\Ticket;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

class TicketRenderService
{
    private const DEFAULT_TEMPLATE_WIDTH = 1000;
    private const DEFAULT_TEMPLATE_HEIGHT = 500;
    private const DEFAULT_FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    public function render(Ticket $ticket): Ticket
    {
        $ticket->loadMissing([
            'ticketPlantilla',
            'ventaHorario.horario.ruta',
            'ventaHorario.horario.operador',
        ]);

        if (! $ticket->ticketPlantilla || ! $ticket->ticketPlantilla->image_path) {
            return $ticket;
        }

        if (! Storage::exists($ticket->ticketPlantilla->image_path)) {
            return $ticket;
        }

        $qrPath = $this->storeQr($ticket);
        $ticketImagePath = $this->storeTicketImage($ticket, $qrPath);

        $ticket->forceFill([
            'qr_path' => $qrPath,
            'ticket_image_path' => $ticketImagePath,
        ])->save();

        return $ticket->fresh([
            'estado',
            'tipoEnvio',
            'procesamientoEstado',
            'ticketPlantilla',
            'ventaHorario.horario.ruta',
            'ventaHorario.horario.operador',
            'ventaHorario.horario.bus',
            'ventaHorario.horario.dia',
            'vendedor',
        ]);
    }

    private function storeQr(Ticket $ticket): string
    {
        $writer = new PngWriter();
        $qrCode = new QrCode(
            data: $ticket->codigo_ticket,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 512,
            margin: 16,
            foregroundColor: new Color(0, 18, 51),
            backgroundColor: new Color(255, 255, 255),
        );

        $result = $writer->write($qrCode);

        $path = "ticket-qrs/{$ticket->codigo_ticket}.png";

        Storage::put($path, $result->getString());

        return $path;
    }

    private function storeTicketImage(Ticket $ticket, string $qrPath): string
    {
        $png = $this->buildTicketPng($ticket, $qrPath);
        $path = "tickets/final/{$ticket->codigo_ticket}.png";

        Storage::put($path, $png);

        return $path;
    }

    private function buildTicketPng(Ticket $ticket, string $qrPath): string
    {
        $width = (int) config('ticket.ticket_template_width', self::DEFAULT_TEMPLATE_WIDTH);
        $height = (int) config('ticket.ticket_template_height', self::DEFAULT_TEMPLATE_HEIGHT);
        $template = $ticket->ticketPlantilla;
        $values = $this->ticketValues($ticket);
        $canvas = imagecreatetruecolor($width, $height);

        if (! $canvas) {
            throw new \RuntimeException('No se pudo crear la imagen del ticket.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        $background = imagecreatefromstring(Storage::get($template->image_path));

        if (! $background) {
            throw new \RuntimeException('No se pudo leer la plantilla del ticket.');
        }

        imagecopyresampled(
            $canvas,
            $background,
            0,
            0,
            0,
            0,
            $width,
            $height,
            imagesx($background),
            imagesy($background),
        );

        $elements = [
            ['key' => 'operador_location', 'value' => $values['operador']],
            ['key' => 'codigo_ticket_location', 'value' => $values['codigo_ticket']],
            ['key' => 'ruta_location', 'value' => $values['ruta']],
            ['key' => 'asiento_location', 'value' => $values['asiento']],
            ['key' => 'salida_location', 'value' => $values['salida']],
            ['key' => 'fecha_hora_location', 'value' => $values['fecha_hora']],
            ['key' => 'precio_location', 'value' => $values['precio']],
        ];

        foreach ($elements as $element) {
            $location = $template->{$element['key']};

            if (! is_array($location)) {
                continue;
            }

            $this->drawTextElement($canvas, $location, $element['value']);
        }

        if (is_array($template->qr_location)) {
            $this->drawImageElement($canvas, $template->qr_location, $qrPath);
        }

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($background);

        return $png;
    }

    /**
     * @return array<string, string>
     */
    private function ticketValues(Ticket $ticket): array
    {
        $ventaHorario = $ticket->ventaHorario;
        $horario = $ventaHorario?->horario;
        $ruta = $horario?->ruta;
        $operador = $horario?->operador;
        $fechaOperacion = $ventaHorario?->fecha_operacion;
        $horaSalida = $this->formatTime((string) $horario?->hora_salida);

        return [
            'operador' => $operador?->nombre_comercial ?? '-',
            'codigo_ticket' => $ticket->codigo_ticket,
            'ruta' => trim(($ruta?->ruta ?? '-').' '.($ruta?->denominacion ?? '')),
            'asiento' => (string) ($ticket->numero_asiento ?? '-'),
            'salida' => $horaSalida,
            'fecha_hora' => trim($this->formatDate($fechaOperacion).' '.$horaSalida),
            'precio' => '$'.number_format((float) ($ruta?->tarifa ?? 0), 2),
        ];
    }

    /**
     * @param array<string, mixed> $location
     */
    private function drawTextElement(\GdImage $canvas, array $location, string $value): void
    {
        $x = (float) ($location['x'] ?? 0);
        $y = (float) ($location['y'] ?? 0);
        $width = (float) ($location['width'] ?? 180);
        $height = (float) ($location['height'] ?? 32);
        $fontSize = (float) ($location['font_size'] ?? 18);
        $color = $this->allocateColor($canvas, (string) ($location['color'] ?? '#001233'));
        $align = (string) ($location['align'] ?? 'center');
        $lineHeight = max($fontSize * 1.15, 1);
        $lines = $this->wrapText($value, $width, $fontSize, max((int) floor($height / $lineHeight), 1));
        $totalHeight = count($lines) * $lineHeight;
        $startY = $y + (($height - $totalHeight) / 2) + ($fontSize * 0.85);

        foreach ($lines as $index => $line) {
            $lineWidth = $this->textWidth($line, $fontSize);
            $lineX = match ($align) {
                'left', 'start' => $x,
                'right', 'end' => $x + $width - $lineWidth,
                default => $x + (($width - $lineWidth) / 2),
            };

            imagettftext(
                $canvas,
                $fontSize,
                0,
                (int) round($lineX),
                (int) round($startY + ($index * $lineHeight)),
                $color,
                $this->fontPath(),
                $line,
            );
        }
    }

    /**
     * @param array<string, mixed> $location
     */
    private function drawImageElement(\GdImage $canvas, array $location, string $path): void
    {
        $image = imagecreatefromstring(Storage::get($path));

        if (! $image) {
            return;
        }

        imagecopyresampled(
            $canvas,
            $image,
            (int) round((float) ($location['x'] ?? 0)),
            (int) round((float) ($location['y'] ?? 0)),
            0,
            0,
            (int) round((float) ($location['width'] ?? 100)),
            (int) round((float) ($location['height'] ?? 100)),
            imagesx($image),
            imagesy($image),
        );

        imagedestroy($image);
    }

    private function formatTime(?string $time): string
    {
        if (! $time) {
            return '-';
        }

        $parts = explode(':', $time);
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);
        $suffix = $hour >= 12 ? 'pm' : 'am';
        $displayHour = $hour % 12 ?: 12;

        return sprintf('%d:%02d %s', $displayHour, $minute, $suffix);
    }

    private function formatDate(mixed $date): string
    {
        if (! $date) {
            return '-';
        }

        if ($date instanceof DateTimeInterface) {
            return $date->format('d/m/y');
        }

        return CarbonImmutable::parse($date)->format('d/m/y');
    }

    /**
     * @return array<int, string>
     */
    private function wrapText(string $text, float $width, float $fontSize, int $maxLines): array
    {
        $charactersPerLine = max((int) floor($width / max($fontSize * 0.58, 1)), 1);
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $candidate = trim($currentLine.' '.$word);

            if (mb_strlen($candidate) <= $charactersPerLine || $currentLine === '') {
                $currentLine = $candidate;
                continue;
            }

            $lines[] = $currentLine;
            $currentLine = $word;

            if (count($lines) >= $maxLines) {
                break;
            }
        }

        if ($currentLine !== '' && count($lines) < $maxLines) {
            $lines[] = $currentLine;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
        }

        if (count($lines) === $maxLines && mb_strlen((string) end($lines)) > $charactersPerLine) {
            $lines[$maxLines - 1] = mb_substr($lines[$maxLines - 1], 0, max($charactersPerLine - 3, 1)).'...';
        }

        return $lines ?: [''];
    }

    private function allocateColor(\GdImage $canvas, string $hex): int
    {
        $normalized = ltrim($hex, '#');

        if (strlen($normalized) === 3) {
            $normalized = $normalized[0].$normalized[0].$normalized[1].$normalized[1].$normalized[2].$normalized[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $normalized)) {
            $normalized = '001233';
        }

        return imagecolorallocate(
            $canvas,
            hexdec(substr($normalized, 0, 2)),
            hexdec(substr($normalized, 2, 2)),
            hexdec(substr($normalized, 4, 2)),
        );
    }

    private function textWidth(string $text, float $fontSize): float
    {
        $box = imagettfbbox($fontSize, 0, $this->fontPath(), $text);

        if (! is_array($box)) {
            return mb_strlen($text) * $fontSize * 0.58;
        }

        return abs($box[2] - $box[0]);
    }

    private function fontPath(): string
    {
        return file_exists(self::DEFAULT_FONT) ? self::DEFAULT_FONT : __DIR__.'/../../vendor/endroid/qr-code/assets/open_sans.ttf';
    }
}
