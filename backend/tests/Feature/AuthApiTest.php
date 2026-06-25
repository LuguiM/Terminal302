<?php

namespace Tests\Feature;

use App\Models\Estado;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_read_profile_change_initial_password_and_logout(): void
    {
        $role = Role::query()->create(['nombre' => 'administrador']);
        $estado = Estado::query()->create(['id' => Estado::ACTIVO_ID, 'nombre' => 'activo']);

        $user = User::query()->create([
            'role_id' => $role->id,
            'estado_id' => $estado->id,
            'name' => 'Admin Test',
            'email' => 'admin@example.test',
            'password' => Hash::make('Temporal123'),
            'must_change_password' => true,
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Temporal123',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.must_change_password', true)
            ->assertJsonStructure(['access_token']);

        $token = $loginResponse->json('access_token');

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->withToken($token)
            ->postJson('/api/change-initial-password', [
                'current_password' => 'Temporal123',
                'password' => 'Nueva123',
                'password_confirmation' => 'Nueva123',
            ])
            ->assertOk()
            ->assertJsonPath('user.must_change_password', false);

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $role = Role::query()->create(['nombre' => 'vendedor']);
        $estado = Estado::query()->create(['id' => Estado::DESACTIVADO_ID, 'nombre' => 'inactivo']);

        User::query()->create([
            'role_id' => $role->id,
            'estado_id' => $estado->id,
            'name' => 'Usuario Inactivo',
            'email' => 'inactive@example.test',
            'password' => Hash::make('Temporal123'),
            'must_change_password' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'inactive@example.test',
            'password' => 'Temporal123',
        ])->assertForbidden();
    }

    public function test_api_errors_are_json_without_accept_header(): void
    {
        $this->get('/api/user')
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json');

        $this->post('/api/login', [])
            ->assertUnprocessable()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
