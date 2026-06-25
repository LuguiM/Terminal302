<?php

namespace Tests\Feature;

use App\Mail\InitialUserCredentialsMail;
use App\Models\Estado;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        $admin = $this->createUser(roleName: 'administrador', email: 'admin@example.test');
        $this->createUser(roleName: 'vendedor', email: 'seller@example.test');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure([
                'users' => [
                    [
                        'id',
                        'name',
                        'email',
                        'role',
                        'estado',
                        'must_change_password',
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
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('pagination.page', 1)
            ->assertJsonPath('pagination.per_page', 15);
    }

    public function test_admin_can_create_user_and_credentials_email_is_sent(): void
    {
        Mail::fake();

        $admin = $this->createUser(roleName: 'administrador', email: 'admin@example.test');
        $role = Role::query()->firstOrCreate(['nombre' => 'vendedor']);
        $active = Estado::query()->firstOrCreate(['id' => Estado::ACTIVO_ID], ['nombre' => 'Activo']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Nuevo Vendedor',
            'email' => 'nuevo@example.test',
            'role_id' => $role->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Usuario creado correctamente.')
            ->assertJsonPath('user.email', 'nuevo@example.test')
            ->assertJsonPath('user.estado.id', $active->id)
            ->assertJsonPath('user.must_change_password', true);

        $user = User::query()->where('email', 'nuevo@example.test')->firstOrFail();

        $this->assertTrue($user->must_change_password);
        $this->assertSame($active->id, $user->estado_id);
        $this->assertNotSame('nuevo@example.test', $user->password);

        Mail::assertSent(InitialUserCredentialsMail::class, function (InitialUserCredentialsMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->user->is($user)
                && $mail->purpose === InitialUserCredentialsMail::PURPOSE_INITIAL
                && strlen($mail->temporaryPassword) === 14
                && Hash::check($mail->temporaryPassword, $user->password);
        });
    }

    public function test_admin_cannot_create_user_with_duplicate_email_or_manual_password(): void
    {
        $admin = $this->createUser(roleName: 'administrador', email: 'admin@example.test');
        $existing = $this->createUser(roleName: 'vendedor', email: 'existing@example.test');

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users', [
            'name' => 'Duplicado',
            'email' => $existing->email,
            'role_id' => $existing->role_id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/admin/users', [
            'name' => 'Con Password',
            'email' => 'manual@example.test',
            'role_id' => $existing->role_id,
            'password' => 'NoPermitida123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->postJson('/api/admin/users', [
            'name' => 'Con Estado',
            'email' => 'estado@example.test',
            'role_id' => $existing->role_id,
            'estado_id' => $existing->estado_id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estado_id']);
    }

    public function test_only_admin_users_can_access_admin_user_routes(): void
    {
        $seller = $this->createUser(roleName: 'vendedor', email: 'seller@example.test');

        $this->getJson('/api/admin/users')
            ->assertUnauthorized();

        Sanctum::actingAs($seller);

        $this->getJson('/api/admin/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para gestionar usuarios.');
    }

    public function test_admin_user_update_validates_unique_email_ignoring_current_user(): void
    {
        $admin = $this->createUser(roleName: 'administrador', email: 'admin@example.test');
        $user = $this->createUser(roleName: 'vendedor', email: 'user@example.test');
        $otherUser = $this->createUser(roleName: 'validador', email: 'other@example.test');

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Usuario Mismo Email',
            'email' => $user->email,
            'role_id' => $user->role_id,
        ])
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Usuario Email Duplicado',
            'email' => $otherUser->email,
            'role_id' => $user->role_id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_show_update_and_toggle_user_status(): void
    {
        $admin = $this->createUser(roleName: 'administrador', email: 'admin@example.test');
        $user = $this->createUser(roleName: 'vendedor', email: 'user@example.test');
        $newRole = Role::query()->firstOrCreate(['nombre' => 'validador']);
        Estado::query()->firstOrCreate(['id' => Estado::DESACTIVADO_ID], ['nombre' => 'Desactivado']);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Usuario Editado',
            'email' => 'editado@example.test',
            'role_id' => $newRole->id,
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Usuario Editado')
            ->assertJsonPath('user.email', 'editado@example.test')
            ->assertJsonPath('user.role.nombre', 'validador');

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Usuario Con Estado',
            'email' => 'con-estado@example.test',
            'role_id' => $newRole->id,
            'estado_id' => Estado::DESACTIVADO_ID,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estado_id']);

        $this->patchJson("/api/admin/users/{$user->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('user.estado.nombre', 'Desactivado');

        $this->patchJson("/api/admin/users/{$user->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('user.estado.nombre', 'Activo');
    }

    public function test_admin_can_reset_user_password(): void
    {
        Mail::fake();

        $admin = $this->createUser(roleName: 'administrador', email: 'admin@example.test');
        $user = $this->createUser(roleName: 'vendedor', email: 'user@example.test');
        $oldPasswordHash = $user->password;

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$user->id}/reset-password")
            ->assertOk()
            ->assertJsonPath('message', 'Contrasena restablecida correctamente.')
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.must_change_password', true)
            ->assertJsonMissing(['temporary_password']);

        $user->refresh();

        $this->assertTrue($user->must_change_password);
        $this->assertNotSame($oldPasswordHash, $user->password);

        Mail::assertSent(InitialUserCredentialsMail::class, function (InitialUserCredentialsMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->user->is($user)
                && $mail->purpose === InitialUserCredentialsMail::PURPOSE_RESET
                && strlen($mail->temporaryPassword) === 14
                && Hash::check($mail->temporaryPassword, $user->password);
        });
    }

    public function test_admin_cannot_toggle_own_status(): void
    {
        $admin = $this->createUser(roleName: 'administrador', email: 'admin@example.test');
        Estado::query()->firstOrCreate(['id' => Estado::DESACTIVADO_ID], ['nombre' => 'Desactivado']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$admin->id}/toggle-status")
            ->assertForbidden()
            ->assertJsonPath('message', 'No puede cambiar su propio estado.');

        $admin->refresh();

        $this->assertSame(Estado::ACTIVO_ID, $admin->estado_id);
    }

    public function test_user_must_change_initial_password_before_admin_access(): void
    {
        $admin = $this->createUser(
            roleName: 'administrador',
            email: 'admin@example.test',
            mustChangePassword: true,
        );

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'Debe cambiar la contrasena inicial antes de continuar.');
    }

    private function createUser(
        string $roleName,
        string $email,
        int $estadoId = Estado::ACTIVO_ID,
        string $estadoName = 'Activo',
        bool $mustChangePassword = false,
    ): User {
        $role = Role::query()->firstOrCreate(['nombre' => $roleName]);
        $estado = Estado::query()->firstOrCreate(['id' => $estadoId], ['nombre' => $estadoName]);

        return User::query()->create([
            'role_id' => $role->id,
            'estado_id' => $estado->id,
            'name' => 'Usuario '.str_replace('@example.test', '', $email),
            'email' => $email,
            'password' => Hash::make('Temporal123'),
            'must_change_password' => $mustChangePassword,
        ]);
    }
}
