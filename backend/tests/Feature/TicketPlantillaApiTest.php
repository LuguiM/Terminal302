<?php

namespace Tests\Feature;

use App\Models\Estado;
use App\Models\Role;
use App\Models\TicketPlantilla;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketPlantillaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ticket.ticket_template_width' => 1000,
            'ticket.ticket_template_height' => 500,
            'ticket.ticket_template_max_size_kb' => 10240,
        ]);

        Storage::fake(config('filesystems.default'));
    }

    public function test_admin_can_list_ticket_templates_paginated_with_filters(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $matching = $this->createTicketPlantilla('Plantilla Principal', esPredeterminada: true);
        $this->createTicketPlantilla('Plantilla Alterna');
        $this->createTicketPlantilla('Plantilla Inactiva', Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/ticket-plantillas?search=Principal&estado_id='.Estado::ACTIVO_ID.'&es_predeterminada=1')
            ->assertOk()
            ->assertJsonStructure([
                'ticket_plantillas' => [
                    [
                        'id',
                        'nombre',
                        'image_path',
                        'image_url',
                        'download_url',
                        'image_size_bytes',
                        'qr_location',
                        'precio_location',
                        'fecha_hora_location',
                        'asiento_location',
                        'codigo_ticket_location',
                        'ruta_location',
                        'salida_location',
                        'operador_location',
                        'estado',
                        'es_predeterminada',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'pagination' => [
                    'page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ])
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('ticket_plantillas.0.id', $matching->id)
            ->assertJsonPath('ticket_plantillas.0.es_predeterminada', true);
    }

    public function test_admin_can_create_active_ticket_template_with_image_locations_and_unique_default(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $previousDefault = $this->createTicketPlantilla('Plantilla Anterior', esPredeterminada: true);

        Sanctum::actingAs($admin);

        $response = $this->post('/api/admin/ticket-plantillas', [
            'nombre' => 'Plantilla Nueva',
            'image' => $this->validImage(),
            'qr_location' => json_encode([
                'x' => 650,
                'y' => 40,
                'width' => 120,
                'height' => 120,
            ]),
            'precio_location' => json_encode([
                'x' => 80,
                'y' => 150,
                'font_size' => 18,
                'color' => '#000000',
                'align' => 'left',
            ]),
            'es_predeterminada' => true,
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Plantilla de ticket creada correctamente.')
            ->assertJsonPath('ticket_plantilla.nombre', 'Plantilla Nueva')
            ->assertJsonPath('ticket_plantilla.estado.nombre', 'Activo')
            ->assertJsonPath('ticket_plantilla.es_predeterminada', true)
            ->assertJsonPath('ticket_plantilla.qr_location.width', 120)
            ->assertJsonPath('ticket_plantilla.precio_location.color', '#000000');

        $imagePath = $response->json('ticket_plantilla.image_path');

        Storage::assertExists($imagePath);
        $this->assertDatabaseHas('ticket_plantillas', [
            'nombre' => 'Plantilla Nueva',
            'estado_id' => Estado::ACTIVO_ID,
            'es_predeterminada' => true,
        ]);
        $this->assertDatabaseHas('ticket_plantillas', [
            'id' => $previousDefault->id,
            'es_predeterminada' => false,
        ]);
    }

    public function test_store_rejects_missing_invalid_image_forbidden_fields_and_invalid_location_json(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');

        Sanctum::actingAs($admin);

        $this->post('/api/admin/ticket-plantillas', [
            'nombre' => 'Sin imagen',
        ], [
            'Accept' => 'application/json',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->post('/api/admin/ticket-plantillas', [
            'nombre' => 'Imagen incorrecta',
            'image' => $this->pngImage('ticket.png', 900, 500),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image'])
            ->assertJsonFragment([
                'La imagen debe tener dimensiones exactas de 1000x500 px.',
            ]);

        $this->post('/api/admin/ticket-plantillas', [
            'nombre' => 'Campos prohibidos',
            'image' => $this->validImage(),
            'estado_id' => Estado::ACTIVO_ID,
            'image_path' => 'manual/path.png',
        ], [
            'Accept' => 'application/json',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estado_id', 'image_path']);

        $this->post('/api/admin/ticket-plantillas', [
            'nombre' => 'Location invalido',
            'image' => $this->validImage(),
            'precio_location' => '{invalid',
        ], [
            'Accept' => 'application/json',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['precio_location']);

        config(['ticket.ticket_template_max_size_kb' => 0]);

        $this->post('/api/admin/ticket-plantillas', [
            'nombre' => 'Imagen pesada',
            'image' => $this->validImage(),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image'])
            ->assertJsonFragment([
                'La imagen no debe superar 1 MB.',
            ]);
    }

    public function test_admin_can_show_ticket_template_and_missing_template_returns_friendly_message(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $ticketPlantilla = $this->createTicketPlantilla('Plantilla Detalle');

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/ticket-plantillas/{$ticketPlantilla->id}")
            ->assertOk()
            ->assertJsonPath('ticket_plantilla.id', $ticketPlantilla->id)
            ->assertJsonPath('ticket_plantilla.nombre', 'Plantilla Detalle');

        $this->getJson('/api/admin/ticket-plantillas/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'La plantilla de ticket solicitada no existe.');
    }

    public function test_admin_can_update_template_replace_image_and_make_default(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $previousDefault = $this->createTicketPlantilla('Plantilla Default', esPredeterminada: true);
        $ticketPlantilla = $this->createTicketPlantilla('Plantilla Editable');
        $oldImagePath = $ticketPlantilla->image_path;

        Sanctum::actingAs($admin);

        $this->call('PUT', "/api/admin/ticket-plantillas/{$ticketPlantilla->id}", [
            'nombre' => 'Plantilla Actualizada',
            'ruta_location' => json_encode([
                'x' => 100,
                'y' => 200,
                'font_size' => 20,
            ]),
            'salida_location' => json_encode([
                'x' => 180,
                'y' => 230,
                'font_size' => 16,
            ]),
            'es_predeterminada' => true,
        ], [], [
            'image' => $this->validImage('ticket-updated.png'),
        ], [
            'HTTP_ACCEPT' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Plantilla de ticket actualizada correctamente.')
            ->assertJsonPath('ticket_plantilla.nombre', 'Plantilla Actualizada')
            ->assertJsonPath('ticket_plantilla.es_predeterminada', true)
            ->assertJsonPath('ticket_plantilla.ruta_location.font_size', 20)
            ->assertJsonPath('ticket_plantilla.salida_location.font_size', 16);

        $ticketPlantilla->refresh();

        Storage::assertMissing($oldImagePath);
        Storage::assertExists($ticketPlantilla->image_path);
        $this->assertDatabaseHas('ticket_plantillas', [
            'id' => $previousDefault->id,
            'es_predeterminada' => false,
        ]);
    }

    public function test_update_rejects_setting_inactive_template_as_default(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $ticketPlantilla = $this->createTicketPlantilla('Plantilla Inactiva', Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/ticket-plantillas/{$ticketPlantilla->id}", [
            'nombre' => 'Plantilla Inactiva',
            'es_predeterminada' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La plantilla de ticket debe estar activa para marcarse como predeterminada.');
    }

    public function test_admin_can_delete_non_default_template_and_image(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $ticketPlantilla = $this->createTicketPlantilla('Plantilla Eliminable');
        $imagePath = $ticketPlantilla->image_path;

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/ticket-plantillas/{$ticketPlantilla->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Plantilla de ticket eliminada correctamente.');

        $this->assertDatabaseMissing('ticket_plantillas', [
            'id' => $ticketPlantilla->id,
        ]);
        $this->assertFalse(TicketPlantilla::query()->where('es_predeterminada', true)->exists());
        Storage::assertMissing($imagePath);
    }

    public function test_admin_cannot_delete_default_template(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $ticketPlantilla = $this->createTicketPlantilla('Plantilla Default', esPredeterminada: true);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/ticket-plantillas/{$ticketPlantilla->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No se puede eliminar una plantilla predeterminada.');

        $this->assertDatabaseHas('ticket_plantillas', [
            'id' => $ticketPlantilla->id,
        ]);
    }

    public function test_admin_can_download_template_image(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $ticketPlantilla = $this->createTicketPlantilla('Plantilla Descargable');

        Sanctum::actingAs($admin);

        $this->get("/api/admin/ticket-plantillas/{$ticketPlantilla->id}/download")
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_toggle_status_alternates_status_and_removes_default_when_inactivated(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $ticketPlantilla = $this->createTicketPlantilla('Plantilla Default', esPredeterminada: true);
        $this->estado(Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/ticket-plantillas/{$ticketPlantilla->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('message', 'Estado de la plantilla de ticket actualizado correctamente.')
            ->assertJsonPath('ticket_plantilla.estado.nombre', 'Desactivado')
            ->assertJsonPath('ticket_plantilla.es_predeterminada', false);

        $this->patchJson("/api/admin/ticket-plantillas/{$ticketPlantilla->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('ticket_plantilla.estado.nombre', 'Activo');
    }

    public function test_set_default_requires_active_template_and_keeps_single_default(): void
    {
        $admin = $this->createUser('administrador', 'admin@example.test');
        $previousDefault = $this->createTicketPlantilla('Plantilla Anterior', esPredeterminada: true);
        $newDefault = $this->createTicketPlantilla('Plantilla Nueva');
        $inactive = $this->createTicketPlantilla('Plantilla Inactiva', Estado::DESACTIVADO_ID, 'Desactivado');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/ticket-plantillas/{$inactive->id}/set-default")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La plantilla de ticket debe estar activa para marcarse como predeterminada.');

        $this->patchJson("/api/admin/ticket-plantillas/{$newDefault->id}/set-default")
            ->assertOk()
            ->assertJsonPath('message', 'Plantilla de ticket predeterminada actualizada correctamente.')
            ->assertJsonPath('ticket_plantilla.es_predeterminada', true);

        $this->assertDatabaseHas('ticket_plantillas', [
            'id' => $previousDefault->id,
            'es_predeterminada' => false,
        ]);
        $this->assertSame(1, TicketPlantilla::query()->where('es_predeterminada', true)->count());
    }

    public function test_security_rules_for_ticket_template_endpoints(): void
    {
        $seller = $this->createUser('vendedor', 'seller@example.test');
        $adminMustChangePassword = $this->createUser(
            roleName: 'administrador',
            email: 'must-change@example.test',
            mustChangePassword: true,
        );

        $this->getJson('/api/admin/ticket-plantillas')
            ->assertUnauthorized();

        Sanctum::actingAs($seller);

        $this->getJson('/api/admin/ticket-plantillas')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para acceder a este recurso.');

        Sanctum::actingAs($adminMustChangePassword);

        $this->getJson('/api/admin/ticket-plantillas')
            ->assertForbidden()
            ->assertJsonPath('message', 'Debe cambiar la contrasena inicial antes de continuar.');
    }

    private function validImage(string $name = 'ticket.png'): UploadedFile
    {
        return $this->pngImage($name, 1000, 500);
    }

    private function pngImage(string $name, int $width, int $height): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ticket-template-');

        file_put_contents($path, $this->pngBinary($width, $height));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function pngBinary(int $width, int $height): string
    {
        $signature = "\x89PNG\r\n\x1a\n";
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $row = "\x00".str_repeat("\x00\x00\x00", $width);
        $raw = str_repeat($row, $height);

        return $signature
            .$this->pngChunk('IHDR', $ihdr)
            .$this->pngChunk('IDAT', gzcompress($raw))
            .$this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data))
            .$type
            .$data
            .pack('N', crc32($type.$data));
    }

    private function createTicketPlantilla(
        string $nombre,
        int $estadoId = Estado::ACTIVO_ID,
        string $estadoName = 'Activo',
        bool $esPredeterminada = false,
    ): TicketPlantilla {
        $estado = $this->estado($estadoId, $estadoName);
        $imagePath = 'ticket-plantillas/'.str($nombre)->slug().'.png';

        Storage::put($imagePath, 'fake-image');

        return TicketPlantilla::query()->create([
            'nombre' => $nombre,
            'image_path' => $imagePath,
            'qr_location' => [
                'x' => 650,
                'y' => 40,
                'width' => 120,
                'height' => 120,
            ],
            'estado_id' => $estado->id,
            'es_predeterminada' => $esPredeterminada,
        ]);
    }

    private function createUser(
        string $roleName,
        string $email,
        bool $mustChangePassword = false,
    ): User {
        $role = Role::query()->firstOrCreate(['nombre' => $roleName]);
        $estado = $this->estado(Estado::ACTIVO_ID, 'Activo');

        return User::query()->create([
            'role_id' => $role->id,
            'estado_id' => $estado->id,
            'name' => 'Usuario '.str_replace('@example.test', '', $email),
            'email' => $email,
            'password' => Hash::make('Temporal123'),
            'must_change_password' => $mustChangePassword,
        ]);
    }

    private function estado(int $id, string $nombre): Estado
    {
        return Estado::query()->firstOrCreate(['id' => $id], ['nombre' => $nombre]);
    }
}
