<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    public function test_api_docs_are_available(): void
    {
        $this->get('/docs/api')
            ->assertOk()
            ->assertSee('Terminal302 API Docs');

        $this->get('/docs/api/openapi.yaml')
            ->assertOk()
            ->assertSee('openapi: 3.1.0')
            ->assertSee('/login:')
            ->assertSee('bearerAuth:');
    }
}
