<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyMcpAccess;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class McpAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(VerifyMcpAccess::class)->get('/__test/mcp-auth', function () {
            return response()->json(['ok' => true]);
        });
    }

    public function test_mcp_endpoint_does_not_accept_query_token(): void
    {
        $response = $this->getJson('/__test/mcp-auth?token=fake-mcp-token');

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => '缺少 MCP 访问令牌。请在请求头中提供 Authorization: Bearer <token> 或 X-MCP-Token。',
            ]);
    }
}
