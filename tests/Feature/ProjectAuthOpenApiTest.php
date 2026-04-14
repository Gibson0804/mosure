<?php

namespace Tests\Feature;

use App\Constants\ProjectConstants;
use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\Media;
use App\Models\Mold;
use App\Models\Project;
use App\Models\ProjectConfig;
use App\Models\ProjectFunction;
use App\Models\ProjectFunctionExecution;
use App\Services\ProjectAuthService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectAuthOpenApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = sys_get_temp_dir().'/mosure_project_auth_test.sqlite';
        if (file_exists($database)) {
            unlink($database);
        }
        touch($database);
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $database,
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('sys_projects', Project::getTableSchema());
        Project::create(['name' => 'Demo', 'prefix' => 'demo', 'template' => 'blank', 'description' => '', 'user_id' => 1]);

        session(['current_project_prefix' => 'demo']);
        Schema::create(ProjectConfig::getfullTableNameByPrefix('demo'), ProjectConfig::getTableSchema());
        Schema::create(ApiKey::getfullTableNameByPrefix('demo'), ApiKey::getTableSchema());
        Schema::create(ApiLog::getfullTableNameByPrefix('demo'), ApiLog::getTableSchema());
        Schema::create(Media::getfullTableNameByPrefix('demo'), Media::getTableSchema());
        Schema::create(ProjectFunction::getfullTableNameByPrefix('demo'), ProjectFunction::getTableSchema());
        Schema::create(ProjectFunctionExecution::getfullTableNameByPrefix('demo'), ProjectFunctionExecution::getTableSchema());
        Schema::create(Mold::getfullTableNameByPrefix('demo'), Mold::getTableSchema());
        Mold::create([
            'name' => 'Article',
            'table_name' => 'demo'.ProjectConstants::MODEL_CONTENT_PREFIX.'article',
            'mold_type' => 'list',
            'fields' => json_encode([['field' => 'title', 'label' => 'Title', 'type' => 'input']], JSON_UNESCAPED_UNICODE),
        ]);
        Schema::create('demo'.ProjectConstants::MODEL_CONTENT_PREFIX.'article', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('content_status')->default('published');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        ProjectConfig::create(['config_group' => 'auth', 'config_key' => 'enabled', 'config_value' => '1']);
    }

    public function test_project_user_can_login_and_call_open_api_with_limited_permissions(): void
    {
        $auth = app(ProjectAuthService::class);
        $auth->ensureSchema();
        $editorRoleId = (int) collect($auth->listRoles())->firstWhere('code', 'editor')['id'];
        $auth->createUser([
            'email' => 'writer@example.com',
            'name' => 'Writer',
            'password' => 'secret123',
            'role_ids' => [$editorRoleId],
        ]);

        $login = $this->postJson('/open/auth/demo/login', [
            'account' => 'writer@example.com',
            'password' => 'secret123',
        ]);
        $login->assertStatus(200)->assertJsonPath('code', 200);
        $userToken = $login->json('data.token');
        $this->assertNotEmpty($userToken);

        $this->assertStringStartsWith('pu_demo_', $userToken);

        $allowed = $this->withHeaders(['Authorization' => 'Bearer '.$userToken])->postJson('/open/content/create/article', ['title' => 'hello']);
        $this->assertSame(200, $allowed->status(), $allowed->getContent());
        $created = DB::table('demo'.ProjectConstants::MODEL_CONTENT_PREFIX.'article')->where('title', 'hello')->first();
        $this->assertNotNull($created);
        $this->assertSame((string) $auth->serializeUser($auth->authenticateToken($userToken))['id'], (string) $created->created_by);
        $this->assertSame((string) $auth->serializeUser($auth->authenticateToken($userToken))['id'], (string) $created->updated_by);
        $this->assertSame('published', $created->content_status);

        $updated = $this->withHeaders(['Authorization' => 'Bearer '.$userToken])->putJson('/open/content/update/article/'.$created->id, ['title' => 'hello updated']);
        $this->assertSame(200, $updated->status(), $updated->getContent());
        $updatedRow = DB::table('demo'.ProjectConstants::MODEL_CONTENT_PREFIX.'article')->where('id', $created->id)->first();
        $this->assertSame((string) $auth->serializeUser($auth->authenticateToken($userToken))['id'], (string) $updatedRow->updated_by);

        DB::table('demo'.ProjectConstants::MODEL_CONTENT_PREFIX.'article')->insert([
            'title' => 'other user content',
            'created_by' => '999',
            'updated_by' => '999',
            'content_status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $list = $this->withHeaders(['Authorization' => 'Bearer '.$userToken])->getJson('/open/content/list/article?fields=*&page_size=10');
        $list->assertStatus(200);
        $this->assertEqualsCanonicalizing(['hello updated'], collect($list->json('data.items'))->pluck('title')->all());

        $other = DB::table('demo'.ProjectConstants::MODEL_CONTENT_PREFIX.'article')->where('title', 'other user content')->first();
        $this->withHeaders(['Authorization' => 'Bearer '.$userToken])
            ->getJson('/open/content/detail/article/'.$other->id)
            ->assertStatus(403);
        $this->withHeaders(['Authorization' => 'Bearer '.$userToken])
            ->putJson('/open/content/update/article/'.$other->id, ['title' => 'hacked'])
            ->assertStatus(403);

        $denied = $this->withHeaders(['Authorization' => 'Bearer '.$userToken])->deleteJson('/open/content/delete/article/1');
        $denied->assertStatus(403)->assertJsonPath('message', 'Project user does not have permission to access this endpoint');
    }

    public function test_project_user_media_requests_are_limited_to_own_records(): void
    {
        $auth = app(ProjectAuthService::class);
        $auth->ensureSchema();
        $auth->createUser(['email' => 'media-owner@example.com', 'password' => 'secret123']);

        $login = $this->postJson('/open/auth/demo/login', [
            'account' => 'media-owner@example.com',
            'password' => 'secret123',
        ]);
        $login->assertStatus(200);
        $userToken = (string) $login->json('data.token');
        $projectUserId = (string) $auth->serializeUser($auth->authenticateToken($userToken))['id'];

        $own = Media::create([
            'filename' => 'own.jpg',
            'original_filename' => 'own',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'path' => 'media/image/own.jpg',
            'disk' => 'public',
            'url' => '/storage/media/image/own.jpg',
            'size' => 100,
            'user_id' => 1,
            'type' => 'image',
            'description' => 'own media',
            'created_by' => $projectUserId,
            'updated_by' => $projectUserId,
        ]);
        $other = Media::create([
            'filename' => 'other.jpg',
            'original_filename' => 'other',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'path' => 'media/image/other.jpg',
            'disk' => 'public',
            'url' => '/storage/media/image/other.jpg',
            'size' => 100,
            'user_id' => 1,
            'type' => 'image',
            'description' => 'other media',
            'created_by' => '999',
            'updated_by' => '999',
        ]);

        $list = $this->withHeaders(['Authorization' => 'Bearer '.$userToken])->getJson('/open/media/list?page_size=10');
        $list->assertStatus(200);
        $this->assertEqualsCanonicalizing([$own->id], collect($list->json('data.data'))->pluck('id')->all());

        $this->withHeaders(['Authorization' => 'Bearer '.$userToken])
            ->getJson('/open/media/detail/'.$other->id)
            ->assertStatus(403);
        $this->withHeaders(['Authorization' => 'Bearer '.$userToken])
            ->putJson('/open/media/update/'.$other->id, ['description' => 'hacked'])
            ->assertStatus(403);

        $updated = $this->withHeaders(['Authorization' => 'Bearer '.$userToken])
            ->putJson('/open/media/update/'.$own->id, ['description' => 'updated own']);
        $updated->assertStatus(200);
        $this->assertSame($projectUserId, (string) Media::find($own->id)->updated_by);
    }

    public function test_project_user_context_is_available_to_open_cloud_functions(): void
    {
        $auth = app(ProjectAuthService::class);
        $auth->ensureSchema();
        $auth->createUser(['email' => 'function-user@example.com', 'password' => 'secret123']);

        $login = $this->postJson('/open/auth/demo/login', [
            'account' => 'function-user@example.com',
            'password' => 'secret123',
        ]);
        $login->assertStatus(200);
        $userToken = (string) $login->json('data.token');
        $projectUserId = (int) $auth->serializeUser($auth->authenticateToken($userToken))['id'];

        ProjectFunction::create([
            'name' => 'Auth Context',
            'slug' => 'auth-context',
            'enabled' => true,
            'code' => 'return ["auth_subject_type" => $ctx["auth_subject_type"] ?? null, "project_user_id" => $ctx["project_user_id"] ?? null, "api_key_id" => $ctx["api_key_id"] ?? null];',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$userToken])
            ->postJson('/open/func/auth-context', []);

        $response->assertStatus(200)
            ->assertJsonPath('data.auth_subject_type', 'project_user')
            ->assertJsonPath('data.project_user_id', $projectUserId)
            ->assertJsonPath('data.api_key_id', null);
    }


    public function test_project_user_can_register_when_enabled(): void
    {
        ProjectConfig::create(['config_group' => 'auth', 'config_key' => 'allow_register', 'config_value' => '1']);

        $response = $this->postJson('/open/auth/demo/register', [
            'email' => 'new-user@example.com',
            'password' => 'secret123',
            'name' => 'New User',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('code', 200)
            ->assertJsonPath('message', 'register_success');
        $this->assertStringStartsWith('pu_demo_', (string) $response->json('data.token'));
        $this->assertEqualsCanonicalizing(
            ProjectAuthService::DEFAULT_PERMISSIONS,
            $response->json('data.user.permissions')
        );
    }

    public function test_project_auth_login_uses_project_api_cors_config(): void
    {
        $this->setApiAllowedOrigins(['https://allowed.example']);
        app(ProjectAuthService::class)->createUser(['email' => 'cors-login@example.com', 'password' => 'secret123']);

        $allowed = $this->withHeaders(['Origin' => 'https://allowed.example'])->postJson('/open/auth/demo/login', [
            'account' => 'cors-login@example.com',
            'password' => 'secret123',
        ]);

        $allowed->assertStatus(200);
        $this->assertSame('https://allowed.example', $allowed->headers->get('Access-Control-Allow-Origin'));

        $blocked = $this->withHeaders(['Origin' => 'https://blocked.example'])->postJson('/open/auth/demo/login', [
            'account' => 'cors-login@example.com',
            'password' => 'secret123',
        ]);

        $blocked->assertStatus(403)->assertJsonPath('message', 'Origin not allowed');
    }

    public function test_project_user_token_uses_project_api_cors_config(): void
    {
        $this->setApiAllowedOrigins(['https://allowed.example']);
        $auth = app(ProjectAuthService::class);
        $auth->ensureSchema();
        $auth->createUser(['email' => 'cors-user@example.com', 'password' => 'secret123']);

        $login = $this->postJson('/open/auth/demo/login', [
            'account' => 'cors-user@example.com',
            'password' => 'secret123',
        ]);
        $login->assertStatus(200);
        $userToken = (string) $login->json('data.token');

        $allowed = $this->withHeaders([
            'Origin' => 'https://allowed.example',
            'Authorization' => 'Bearer '.$userToken,
        ])->getJson('/open/content/list/article');

        $this->assertNotSame(403, $allowed->status(), $allowed->getContent());
        $this->assertSame('https://allowed.example', $allowed->headers->get('Access-Control-Allow-Origin'));

        $blocked = $this->withHeaders([
            'Origin' => 'https://blocked.example',
            'Authorization' => 'Bearer '.$userToken,
        ])->getJson('/open/content/list/article');

        $blocked->assertStatus(403)->assertJsonPath('message', 'Origin not allowed');
    }

    public function test_api_key_request_uses_project_api_cors_config(): void
    {
        $this->setApiAllowedOrigins(['https://allowed.example']);
        $apiKey = 'ak_demo_ABCDEFGHIJKLM_1234567890';
        ApiKey::create([
            'name' => 'CORS key',
            'key' => $apiKey,
            'rate_limit' => 1000,
            'is_active' => true,
            'scopes' => [ApiKey::SCOPE_CONTENT_READ],
        ]);

        $allowed = $this->withHeaders([
            'Origin' => 'https://allowed.example',
            'X-API-KEY' => $apiKey,
        ])->getJson('/open/content/list/article');

        $this->assertNotSame(403, $allowed->status(), $allowed->getContent());
        $this->assertSame('https://allowed.example', $allowed->headers->get('Access-Control-Allow-Origin'));

        $blocked = $this->withHeaders([
            'Origin' => 'https://blocked.example',
            'X-API-KEY' => $apiKey,
        ])->getJson('/open/content/list/article');

        $blocked->assertStatus(403)->assertJsonPath('message', 'Origin not allowed');
    }

    public function test_project_auth_rejects_login_when_disabled(): void
    {
        ProjectConfig::where('config_group', 'auth')->where('config_key', 'enabled')->update(['config_value' => '0']);
        app(ProjectAuthService::class)->createUser(['email' => 'user@example.com', 'password' => 'secret123']);

        $response = $this->postJson('/open/auth/demo/login', ['account' => 'user@example.com', 'password' => 'secret123']);

        $response->assertStatus(401)->assertJsonPath('message', '当前项目未启用用户认证');
    }

    private function setApiAllowedOrigins(array $origins): void
    {
        ProjectConfig::updateOrCreate(
            ['config_group' => 'api', 'config_key' => 'allowed_origins'],
            ['config_value' => json_encode($origins, JSON_UNESCAPED_UNICODE)]
        );
    }
}
