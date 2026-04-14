<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithInstallLock;
use Tests\TestCase;

class InstallPagesTest extends TestCase
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

    public function test_install_step1_is_accessible_when_application_is_not_locked(): void
    {
        $this->removeInstallLock();

        $response = $this->get('/install/step1');

        $response->assertOk();
    }

    public function test_install_step1_redirects_to_login_when_application_is_locked(): void
    {
        $this->createInstallLock();

        $response = $this->get('/install/step1');

        $response->assertRedirect(route('login'));
    }

    public function test_install_root_redirects_to_step1(): void
    {
        $this->removeInstallLock();

        $response = $this->get('/install');

        $response->assertRedirect(route('install.step1'));
    }
}
