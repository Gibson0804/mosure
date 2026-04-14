<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenApiAuthTest extends TestCase
{
    public function test_open_api_requires_api_key(): void
    {
        $response = $this->getJson('/open/content/list/article');

        $response
            ->assertStatus(401)
            ->assertJson([
                'code' => 401,
                'message' => 'API Key is required',
                'data' => null,
            ]);
    }

    public function test_open_api_does_not_accept_query_api_key(): void
    {
        $response = $this->getJson('/open/content/list/article?api_key=fake-api-key');

        $response
            ->assertStatus(401)
            ->assertJson([
                'code' => 401,
                'message' => 'API Key is required',
                'data' => null,
            ]);
    }
}
