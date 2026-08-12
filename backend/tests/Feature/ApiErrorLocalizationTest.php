<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiErrorLocalizationTest extends TestCase
{
    public function test_validation_errors_are_returned_in_spanish(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El campo correo electrónico es obligatorio. (y 1 error más)')
            ->assertJsonPath('errors.email.0', 'El campo correo electrónico es obligatorio.')
            ->assertJsonPath('errors.password.0', 'El campo contraseña es obligatorio.');
    }

    public function test_unauthenticated_errors_are_returned_in_spanish(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'No se ha autenticado.');
    }

    public function test_unknown_api_routes_are_returned_in_spanish(): void
    {
        $this->getJson('/api/recurso-inexistente')
            ->assertNotFound()
            ->assertJsonPath('message', 'El recurso solicitado no existe.');
    }
}
