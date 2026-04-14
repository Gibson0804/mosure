<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithInstallLock;
use Tests\TestCase;

class ProtectedRoutesTest extends TestCase
{
    use InteractsWithInstallLock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupInstallLockState();
        $this->createInstallLock();
    }

    protected function tearDown(): void
    {
        $this->restoreInstallLockState();

        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login_for_project_index(): void
    {
        $response = $this->get('/project');

        $response->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_for_system_config_page(): void
    {
        $response = $this->get('/system-config');

        $response->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_for_knowledge_base_page(): void
    {
        $response = $this->get('/kb');

        $response->assertRedirect(route('login'));
    }
}
