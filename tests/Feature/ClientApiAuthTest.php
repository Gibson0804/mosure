<?php

namespace Tests\Feature;

use Tests\TestCase;

class ClientApiAuthTest extends TestCase
{
    public function test_client_api_does_not_accept_query_token(): void
    {
        $response = $this->getJson('/client/me?token=fake-client-token');

        $response
            ->assertStatus(401)
            ->assertJson([
                'code' => 401,
                'message' => 'Missing client token',
                'data' => null,
            ]);
    }
}
