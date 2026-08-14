<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Models\Estado;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordRecoveryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_request_does_not_reveal_accounts_and_throttles_resends(): void
    {
        Mail::fake();
        $user = $this->createUser();
        $expectedMessage = 'Si el correo está registrado, recibirá un enlace para restablecer la contraseña.';

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', $expectedMessage);

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', $expectedMessage);

        $this->postJson('/api/forgot-password', ['email' => 'unknown@example.test'])
            ->assertOk()
            ->assertJsonPath('message', $expectedMessage);

        Mail::assertSent(PasswordResetMail::class, 1);
    }

    public function test_valid_token_resets_password_revokes_sessions_and_cannot_be_reused(): void
    {
        Mail::fake();
        $user = $this->createUser(mustChangePassword: true);
        $user->createToken('first-device');
        $user->createToken('second-device');

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        /** @var PasswordResetMail $mail */
        $mail = Mail::sent(PasswordResetMail::class)->first();
        $payload = [
            'email' => $user->email,
            'token' => $mail->token,
            'password' => 'NuevaClave1!',
            'password_confirmation' => 'NuevaClave1!',
        ];

        $this->postJson('/api/reset-password', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Contraseña restablecida correctamente. Ya puede iniciar sesión.');

        $user->refresh();
        $this->assertTrue(Hash::check('NuevaClave1!', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->postJson('/api/reset-password', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.token.0', 'El enlace de recuperación no es válido o ha vencido.');
    }

    public function test_expired_token_and_weak_password_are_rejected(): void
    {
        Mail::fake();
        $user = $this->createUser();

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        /** @var PasswordResetMail $mail */
        $mail = Mail::sent(PasswordResetMail::class)->first();

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $mail->token,
            'password' => 'debil123',
            'password_confirmation' => 'debil123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->travel(61)->minutes();

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $mail->token,
            'password' => 'NuevaClave1!',
            'password_confirmation' => 'NuevaClave1!',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.token.0', 'El enlace de recuperación no es válido o ha vencido.');
    }

    public function test_inactive_user_can_reset_password_but_cannot_login(): void
    {
        Mail::fake();
        $user = $this->createUser(active: false);

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        /** @var PasswordResetMail $mail */
        $mail = Mail::sent(PasswordResetMail::class)->first();

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $mail->token,
            'password' => 'NuevaClave1!',
            'password_confirmation' => 'NuevaClave1!',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'NuevaClave1!',
        ])->assertForbidden()->assertJsonPath('message', 'El usuario no esta activo.');
    }

    private function createUser(bool $active = true, bool $mustChangePassword = false): User
    {
        $role = Role::query()->firstOrCreate(['nombre' => 'vendedor']);
        $estadoId = $active ? Estado::ACTIVO_ID : Estado::DESACTIVADO_ID;
        $estado = Estado::query()->firstOrCreate(
            ['id' => $estadoId],
            ['nombre' => $active ? 'activo' : 'inactivo'],
        );

        return User::query()->create([
            'role_id' => $role->id,
            'estado_id' => $estado->id,
            'name' => 'Usuario Recuperación',
            'email' => $active ? 'active@example.test' : 'inactive@example.test',
            'password' => Hash::make('Temporal123'),
            'must_change_password' => $mustChangePassword,
        ]);
    }
}
