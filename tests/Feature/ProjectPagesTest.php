<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\InteractsWithInstallLock;
use Tests\TestCase;

class ProjectPagesTest extends TestCase
{
    use InteractsWithInstallLock;

    private ?User $tempUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupInstallLockState();
        $this->createInstallLock();
    }

    protected function tearDown(): void
    {
        if ($this->tempUser) {
            $this->tempUser->delete();
        }

        $this->restoreInstallLockState();

        parent::tearDown();
    }

    public function test_non_admin_user_can_open_project_index_and_receive_empty_project_list(): void
    {
        $this->tempUser = User::factory()->create([
            'is_admin' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->tempUser)->get('/project');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Project/ProjectList')
            ->where('projects', [])
        );
    }
}
