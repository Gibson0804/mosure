<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithInstallLock;
use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    use InteractsWithInstallLock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupInstallLockState();
    }

    protected function tearDown(): void
    {
        $this->restoreInstallLockState();

        parent::tearDown();
    }

    public function test_login_redirects_to_install_when_application_is_not_locked(): void
    {
        $this->removeInstallLock();

        $response = $this->get('/login');

        $response->assertRedirect(route('install.step1'));
    }

    public function test_login_page_is_accessible_when_application_is_locked(): void
    {
        $this->createInstallLock();

        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_forgot_password_page_is_accessible(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertOk();
    }
}
