<?php

use App\Http\Controllers\Admin\AIAgentController;
use App\Http\Controllers\Admin\AIChatController;
use App\Http\Controllers\Admin\AISessionController;
use App\Http\Controllers\Admin\ApiDocumentationController;
use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CloudCronController;
use App\Http\Controllers\Admin\CloudEnvController;
use App\Http\Controllers\Admin\CloudFunctionController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\ContentVersionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GptController;
use App\Http\Controllers\Admin\Install\InstallController;
use App\Http\Controllers\Admin\KnowledgeBaseController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MediaFolderController;
use App\Http\Controllers\Admin\MediaTagController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MoldController;
use App\Http\Controllers\Admin\PageHostingController;
use App\Http\Controllers\Admin\PluginController;
use App\Http\Controllers\Admin\ProjectConfigController;
use App\Http\Controllers\Admin\ProjectAuthController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectExportController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\SysTaskController;
use App\Http\Controllers\Admin\SystemConfigController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\TriggerController;
use App\Http\Controllers\PluginMarketplaceController;
use App\Http\Middleware\EnsureProjectSelected;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

// ========================
// 1. 安装相关路由
// ========================

Route::group(['prefix' => 'install', 'as' => 'install.'], function () {
    // 重定向到 step1 以避免访问 /install
    Route::get('/', function () {
        return redirect()->route('install.step1');
    });
    Route::get('/step1', [InstallController::class, 'installStep1'])->name('step1');
    Route::get('/step2', [InstallController::class, 'installStep2'])->name('step2');
    Route::get('/step3', [InstallController::class, 'installStep3'])->name('step3');
});

// 2. 项目管理相关路由
// ========================
Route::prefix('project')->middleware('auth')->group(function () {
    Route::any('/', [ProjectController::class, 'index'])->name('project.index');
    Route::any('/create', [ProjectController::class, 'create'])->name('project.create');
    Route::any('/edit/{id}', [ProjectController::class, 'edit'])->name('project.edit');
    Route::get('/select/{id}', [ProjectController::class, 'select'])->name('project.select');
    Route::delete('/delete/{id}', [ProjectController::class, 'delete'])->name('project.delete');
    // 项目相关API
    Route::get('/generate-prefix', [ProjectController::class, 'generatePrefix']);
});

// ========================
// 系统设置（无需选择项目）
// ========================
Route::prefix('system-config')->middleware(['auth'])->group(function () {
    Route::get('/', [SystemConfigController::class, 'index'])->name('system-config.index');
    Route::get('/data', [SystemConfigController::class, 'show'])->name('system-config.show');
    Route::post('/save', [SystemConfigController::class, 'save'])->name('system-config.save');
    Route::post('/test-mail', [SystemConfigController::class, 'testMail'])->name('system-config.test_mail');
    Route::post('/test-provider', [SystemConfigController::class, 'testProvider'])->name('system-config.test_provider');
    Route::post('/test-storage', [SystemConfigController::class, 'testStorage'])->name('system-config.test_storage');
});

Route::prefix('ai')->middleware(['auth'])->group(function () {
    Route::get('/chat', [AIChatController::class, 'index'])->name('ai.chat');

    // Agent 管理
    Route::get('/agents/list', [AIAgentController::class, 'list']);
    Route::post('/agents/create', [AIAgentController::class, 'create']);
    Route::put('/agents/update/{id}', [AIAgentController::class, 'update']);
    Route::post('/agents/preview-prompt', [AIAgentController::class, 'previewPrompt']);
    Route::post('/agents/test', [AIAgentController::class, 'test']);
    Route::post('/agents/{type}/{identifier}/private-chat', [AISessionController::class, 'privateChat']);

    // 会话管理
    Route::get('/sessions/list', [AISessionController::class, 'list']);
    Route::post('/sessions/create', [AISessionController::class, 'create']);
    Route::put('/sessions/update/{id}', [AISessionController::class, 'update']);
    Route::delete('/sessions/delete/{id}', [AISessionController::class, 'delete']);
    Route::delete('/sessions/{id}/messages', [AISessionController::class, 'clearMessages']);
    Route::get('/sessions/{id}/messages/list', [AISessionController::class, 'messages']);
    Route::post('/sessions/{id}/messages/send', [AISessionController::class, 'send']);
    Route::get('/sessions/{id}/poll', [AISessionController::class, 'poll']);
});

Route::prefix('manage')->middleware(['auth', EnsureProjectSelected::class])->group(function () {
    Route::get('/export', [ProjectExportController::class, 'index'])->name('project.export');
    Route::post('/export', [ProjectExportController::class, 'export'])->name('project.export.submit');
    Route::get('/export/download', [ProjectExportController::class, 'download'])->name('project.export.download')->middleware('signed');
    Route::post('/import/parse', [ProjectExportController::class, 'parseImport'])->name('project.import.parse');
    Route::post('/import', [ProjectExportController::class, 'import'])->name('project.import.submit');

    // 审计日志
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('manage.audit-logs');
    Route::get('/audit-logs/list', [AuditLogController::class, 'list'])->name('manage.audit-logs.list');

    // 函数管理
    Route::get('/web-functions', [CloudFunctionController::class, 'webFunctions'])->name('manage.web-functions');
    Route::get('/trigger-functions', [CloudFunctionController::class, 'triggerFunctions'])->name('manage.trigger-functions');
    Route::get('/functions/list', [CloudFunctionController::class, 'list'])->name('manage.functions.list');
    Route::post('/functions/create', [CloudFunctionController::class, 'create'])->name('manage.functions.create');
    Route::post('/functions/update/{id}', [CloudFunctionController::class, 'update'])->name('manage.functions.update');
    Route::post('/functions/toggle/{id}', [CloudFunctionController::class, 'toggle'])->name('manage.functions.toggle');
    Route::get('/functions/check-bindings/{id}', [CloudFunctionController::class, 'checkBindings'])->name('manage.functions.checkBindings');
    Route::post('/functions/delete/{id}', [CloudFunctionController::class, 'delete'])->name('manage.functions.delete');
    Route::get('/functions/executions/{id}', [CloudFunctionController::class, 'executions'])->name('manage.functions.executions');
    Route::get('/functions/trigger-executions/{id}', [CloudFunctionController::class, 'triggerExecutions'])->name('manage.functions.trigger-executions');
    // 函数代码编辑
    Route::get('/functions/code/{id}', [CloudFunctionController::class, 'code'])->name('manage.functions.code');
    Route::get('/functions/detail/{id}', [CloudFunctionController::class, 'detail'])->name('manage.functions.detail');
    // 管理端本地测试触发函数（不走 HTTP）
    Route::post('/functions/test/{id}', [CloudFunctionController::class, 'test'])->name('manage.functions.test');
    // 管理端测试：通过 slug 调用函数（允许测试禁用的函数）
    Route::post('/functions/invoke/{slug}', [CloudFunctionController::class, 'invoke'])->name('manage.functions.invoke');

    // 云函数环境变量
    Route::get('/cloud-env', [CloudEnvController::class, 'index'])->name('manage.cloud-env');
    Route::get('/cloud-env/list', [CloudEnvController::class, 'list'])->name('manage.cloud-env.list');
    Route::post('/cloud-env/create', [CloudEnvController::class, 'create'])->name('manage.cloud-env.create');
    Route::post('/cloud-env/update/{id}', [CloudEnvController::class, 'update'])->name('manage.cloud-env.update');
    Route::post('/cloud-env/delete/{id}', [CloudEnvController::class, 'delete'])->name('manage.cloud-env.delete');

    // 前端托管管理
    Route::get('/page-hosting', [PageHostingController::class, 'index'])->name('manage.page-hosting');
    Route::get('/pages/list', [PageHostingController::class, 'list'])->name('manage.pages.list');
    Route::get('/pages/get/{slug}', [PageHostingController::class, 'get'])->name('manage.pages.get');
    Route::post('/pages/create', [PageHostingController::class, 'create'])->name('manage.pages.create');
    Route::post('/pages/update/{slug}', [PageHostingController::class, 'update'])->name('manage.pages.update');
    Route::post('/pages/toggle/{slug}', [PageHostingController::class, 'toggle'])->name('manage.pages.toggle');
    Route::post('/pages/delete/{slug}', [PageHostingController::class, 'delete'])->name('manage.pages.delete');
    Route::post('/pages/deploy-zip', [PageHostingController::class, 'deployZip'])->name('manage.pages.deploy_zip');
    Route::post('/pages/ai-generate', [PageHostingController::class, 'aiGenerate'])->name('manage.pages.ai_generate');
    Route::get('/pages/models-summary', [PageHostingController::class, 'modelsSummary'])->name('manage.pages.models_summary');

    // 定时任务管理
    Route::get('/cloud-crons', [CloudCronController::class, 'index'])->name('manage.cloud-crons');
    Route::get('/crons/list', [CloudCronController::class, 'list'])->name('manage.crons.list');
    Route::get('/crons/get/{id}', [CloudCronController::class, 'get'])->name('manage.crons.get');
    Route::get('/crons/executions/{id}', [CloudCronController::class, 'executions'])->name('manage.crons.executions');
    Route::post('/crons/create', [CloudCronController::class, 'create'])->name('manage.crons.create');
    Route::post('/crons/update/{id}', [CloudCronController::class, 'update'])->name('manage.crons.update');
    Route::post('/crons/toggle/{id}', [CloudCronController::class, 'toggle'])->name('manage.crons.toggle');
    Route::post('/crons/delete/{id}', [CloudCronController::class, 'delete'])->name('manage.crons.delete');
    Route::post('/crons/run-now/{id}', [CloudCronController::class, 'runNow'])->name('manage.crons.run_now');

    // 触发器管理
    Route::get('/triggers', [TriggerController::class, 'index'])->name('manage.triggers');
    Route::get('/triggers/list', [TriggerController::class, 'list'])->name('manage.triggers.list');
    Route::get('/triggers/detail/{id}', [TriggerController::class, 'detail'])->name('manage.triggers.detail');
    Route::post('/triggers/create', [TriggerController::class, 'create'])->name('manage.triggers.create');
    Route::post('/triggers/update/{id}', [TriggerController::class, 'update'])->name('manage.triggers.update');
    Route::post('/triggers/toggle/{id}', [TriggerController::class, 'toggle'])->name('manage.triggers.toggle');
    Route::post('/triggers/delete/{id}', [TriggerController::class, 'delete'])->name('manage.triggers.delete');
    Route::get('/triggers/executions/{id}', [TriggerController::class, 'executions'])->name('manage.triggers.executions');

    // 项目用户认证（系统内置可选模块）
    Route::get('/project-auth', [ProjectAuthController::class, 'index'])->name('manage.project-auth');
    Route::get('/project-auth/users', [ProjectAuthController::class, 'users'])->name('manage.project-auth.users');
    Route::post('/project-auth/users', [ProjectAuthController::class, 'createUser'])->name('manage.project-auth.users.create');
    Route::post('/project-auth/users/{id}', [ProjectAuthController::class, 'updateUser'])->name('manage.project-auth.users.update');
    Route::post('/project-auth/users/{id}/delete', [ProjectAuthController::class, 'deleteUser'])->name('manage.project-auth.users.delete');
    Route::get('/project-auth/roles', [ProjectAuthController::class, 'roles'])->name('manage.project-auth.roles');
    Route::post('/project-auth/roles', [ProjectAuthController::class, 'createRole'])->name('manage.project-auth.roles.create');
    Route::post('/project-auth/roles/{id}', [ProjectAuthController::class, 'updateRole'])->name('manage.project-auth.roles.update');
    Route::post('/project-auth/roles/{id}/delete', [ProjectAuthController::class, 'deleteRole'])->name('manage.project-auth.roles.delete');

    // 项目配置
    Route::get('/project-config', [ProjectConfigController::class, 'index'])->name('manage.project-config');
    Route::get('/project-config/data', [ProjectConfigController::class, 'show'])->name('manage.project-config.show');
    Route::post('/project-config/save', [ProjectConfigController::class, 'save'])->name('manage.project-config.save');
    Route::post('/project-config/repair', [ProjectConfigController::class, 'repair'])->name('manage.project-config.repair');
    Route::post('/project-config/purge', [ProjectConfigController::class, 'purge'])->name('manage.project-config.purge');
    // MCP 配置
    Route::post('/project-config/mcp/generate-token', [ProjectConfigController::class, 'mcpGenerateToken'])->name('manage.project-config.mcp.generate_token');
    Route::get('/project-config/mcp/client-config', [ProjectConfigController::class, 'mcpClientConfig'])->name('manage.project-config.mcp.client_config');

    // 任务中心（系统任务）
    Route::get('/sys-tasks', [SysTaskController::class, 'index'])->name('manage.sys-tasks');
    Route::get('/sys-tasks/list', [SysTaskController::class, 'list'])->name('manage.sys-tasks.list');
    Route::get('/sys-tasks/detail/{id}', [SysTaskController::class, 'detail'])->name('manage.sys-tasks.detail');
    Route::get('/sys-tasks/children/{id}', [SysTaskController::class, 'children'])->name('manage.sys-tasks.children');
    Route::get('/sys-tasks/steps/{id}', [SysTaskController::class, 'steps'])->name('manage.sys-tasks.steps');
    Route::post('/sys-tasks/cancel/{id}', [SysTaskController::class, 'cancel'])->name('manage.sys-tasks.cancel');
    Route::post('/sys-tasks/retry/{id}', [SysTaskController::class, 'retry'])->name('manage.sys-tasks.retry');

    // 菜单管理
    Route::get('/menus', [MenuController::class, 'index'])->name('manage.menus');
    Route::get('/menus/tree', [MenuController::class, 'tree'])->name('manage.menus.tree');
    Route::get('/menus/models', [MenuController::class, 'models'])->name('manage.menus.models');
    Route::post('/menus', [MenuController::class, 'store'])->name('manage.menus.store');
    Route::post('/menus/{id}', [MenuController::class, 'update'])->name('manage.menus.update');
    Route::post('/menus/{id}/move', [MenuController::class, 'move'])->name('manage.menus.move');
    Route::post('/menus/{id}/delete', [MenuController::class, 'destroy'])->name('manage.menus.delete');
    Route::post('/menus/core-override', [MenuController::class, 'overrideCore'])->name('manage.menus.core_override');
});

// ========================
// 6. API文档路由
// ========================
Route::prefix('api-docs')->middleware(['auth', EnsureProjectSelected::class])->group(function () {
    Route::get('/', [ApiDocumentationController::class, 'index'])->name('api-docs.index');
    Route::get('/molds/{type}/{id}', [ApiDocumentationController::class, 'getApiExamples'])->name('api-docs.molds.show');
});

// ========================
// 7. API密钥管理路由
// ========================
Route::prefix('api-key')->middleware(['auth', EnsureProjectSelected::class])->group(function () {
    Route::get('/', [ApiKeyController::class, 'index'])->name('api-key.index');
    Route::post('/create', [ApiKeyController::class, 'create'])->name('api-key.store');
    Route::post('/edit/{id}', [ApiKeyController::class, 'edit'])->name('api-key.update');
    Route::post('/delete/{id}', [ApiKeyController::class, 'delete'])->name('api-key.destroy');

    // API接口
    Route::post('/generate', [ApiKeyController::class, 'generate'])->name('api-key.generate');
    Route::post('/toggle/{id}', [ApiKeyController::class, 'toggle'])->name('api-key.toggle');
});

// ========================
// 3. 仪表盘与首页
// ========================
Route::get('/', function () {
    return redirect()->route('project.index');
});
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware(['auth', EnsureProjectSelected::class]);

// ========================
// 4. 内容模型相关路由
// ========================
Route::prefix('mold')->middleware(['auth', EnsureProjectSelected::class])->group(function () {
    Route::any('/add', [MoldController::class, 'moldAdd'])->name('mold.add');
    Route::any('/list', [MoldController::class, 'moldList'])->name('mold.list');
    Route::any('/edit/{id}', [MoldController::class, 'moldEdit'])->name('mold.edit');

    Route::post('/update/{id}', [MoldController::class, 'updateMoldById'])->name('mold.update');
    Route::post('/suggest', [MoldController::class, 'suggestMold']);
    Route::post('/delete_check/{id}', [MoldController::class, 'deleteCheck']);
    Route::post('/delete/{id}', [MoldController::class, 'delete']);

    // 表单构建器内部使用：获取模型/字段
    Route::get('/builder/models_and_fields', [MoldController::class, 'builderModelsAndFields'])->name('mold.builder.models_and_fields');
    Route::get('/builder/{id}/fields', [MoldController::class, 'builderModelFields'])->name('mold.builder.fields');
});

Route::prefix('content')->middleware(['auth', EnsureProjectSelected::class])->group(function () {
    Route::any('/list/{moldId}', [ContentController::class, 'contentList'])->name('content.list');
    Route::any('/add/{moldId}', [ContentController::class, 'contentAdd'])->name('content.add');
    Route::any('/edit/{moldId}/{id}', [ContentController::class, 'contentEdit'])->name('content.edit');

    Route::post('/detail/{moldId}/{id}', [ContentController::class, 'contentDetail']);
    Route::post('/count/{moldId}', [ContentController::class, 'count']);
    Route::post('/delete/{moldId}/{id}', [ContentController::class, 'delete']);
    Route::post('/delete-batch/{moldId}', [ContentController::class, 'deleteBatch']);
    Route::post('/publish/{moldId}/{id}', [ContentController::class, 'publish']);
    Route::post('/unpublish/{moldId}/{id}', [ContentController::class, 'unpublish']);
    Route::post('/publish-batch/{moldId}', [ContentController::class, 'publishBatch']);
    Route::post('/unpublish-batch/{moldId}', [ContentController::class, 'unpublishBatch']);
    // AI 生成内容
    Route::post('/ai/generate/{moldId}', [ContentController::class, 'aiGenerate']);
    Route::post('/ai/generate-batch/{moldId}', [ContentController::class, 'aiGenerateBatch']); // 同步模拟
    Route::post('/ai/generate-batch-start/{moldId}', [ContentController::class, 'aiGenerateBatchStart']); // 异步父任务

    // 表单构建器：根据模型ID与字段名获取内容字段选项
    Route::post('/field-options/{moldId}', [ContentController::class, 'fieldOptions'])->name('content.field_options');

    // 版本控制
    Route::get('/versions/{moldId}/{id}', [ContentVersionController::class, 'list']);
    Route::get('/versions/{moldId}/{id}/{version}', [ContentVersionController::class, 'show']);
    Route::get('/versions/diff', [ContentVersionController::class, 'diff']);
    Route::post('/versions/rollback/{moldId}/{id}/{version}', [ContentVersionController::class, 'rollback']);
});

Route::prefix('task')->middleware(['auth', EnsureProjectSelected::class])->group(function () {
    Route::post('/ai/generate-status/{taskId}', [TaskController::class, 'aiGenerateStatus'])->name('content.ai_generate_status');
});

Route::prefix('gpt')->middleware(['auth', EnsureProjectSelected::class])->group(function () {
    Route::post('/ask', [GptController::class, 'doAsk'])->name('gpt.ask');
    Route::post('/list_models', [GptController::class, 'listModels'])->name('gpt.list_models');
    Route::post('/rich_text_edit', [GptController::class, 'richTextEdit'])->name('gpt.rich_text_edit');
    Route::post('/markdown_edit', [GptController::class, 'markdownEdit'])->name('gpt.markdown_edit');
});

Route::prefix('subject')->middleware(['auth', EnsureProjectSelected::class])->group(function () {
    Route::any('/edit/{moldId}', [SubjectController::class, 'subjectEdit'])->name('subject.edit');
});

// ========================
// 5. 媒体资源管理路由
// ========================
Route::prefix('media')->middleware(['auth', EnsureProjectSelected::class])->group(function () {
    Route::get('/', [MediaController::class, 'index'])->name('media.index');
    Route::any('/create', [MediaController::class, 'create'])->name('media.create');
    Route::any('/edit/{id}', [MediaController::class, 'edit'])->name('media.edit');
    Route::get('/show/{id}', [MediaController::class, 'show'])->name('media.show');
    Route::post('/delete/{id}', [MediaController::class, 'delete'])->name('media.destroy');

    Route::post('/upload', [MediaController::class, 'upload'])->name('media.upload');
    Route::post('/batch-delete', [MediaController::class, 'batchDelete'])->name('media.batchDelete');
    Route::post('/batch-move', [MediaController::class, 'batchMove'])->name('media.batchMove');

    // 虚拟文件夹管理
    Route::get('/folders/tree', [MediaFolderController::class, 'tree'])->name('media.folders.tree');
    Route::post('/folders', [MediaFolderController::class, 'store'])->name('media.folders.store');
    Route::post('/folders/{id}/rename', [MediaFolderController::class, 'rename'])->name('media.folders.rename');
    Route::post('/folders/{id}/move', [MediaFolderController::class, 'move'])->name('media.folders.move');
    Route::post('/folders/{id}/delete', [MediaFolderController::class, 'destroy'])->name('media.folders.delete');

    // 标签管理
    Route::get('/tags/list', [MediaTagController::class, 'list'])->name('media.tags.list');
    Route::post('/tags', [MediaTagController::class, 'create'])->name('media.tags.create');
    Route::post('/tags/{id}', [MediaTagController::class, 'update'])->name('media.tags.update');
    Route::post('/tags/{id}/delete', [MediaTagController::class, 'delete'])->name('media.tags.delete');
});

// ========================
// 知识库路由（系统级，无需选择项目）
// ========================
// 公开文章查看（无需登录）
Route::get('/kb/share/{slug}', [KnowledgeBaseController::class, 'publicView'])->name('kb.public-view');

// 移动端 WebView 页面（Token 认证，供 App 内嵌使用）
Route::prefix('m/kb')->middleware([\App\Http\Middleware\TokenWebAuth::class])->group(function () {
    Route::get('/edit/{id?}', [KnowledgeBaseController::class, 'mobileEditor'])->name('kb.mobile-editor');
    Route::get('/{id}', [KnowledgeBaseController::class, 'mobileView'])->name('kb.mobile-view')->where('id', '[0-9]+');
});

Route::prefix('kb')->middleware(['auth'])->group(function () {
    Route::get('/', [KnowledgeBaseController::class, 'index'])->name('kb.index');
    Route::get('/detail/{id}', [KnowledgeBaseController::class, 'detailView'])->name('kb.detail');
    Route::get('/editor/{id?}', [KnowledgeBaseController::class, 'editor'])->name('kb.editor');

    // 分类 API
    Route::get('/categories/tree', [KnowledgeBaseController::class, 'categoryTree'])->name('kb.categories.tree');
    Route::post('/categories/create', [KnowledgeBaseController::class, 'createCategory'])->name('kb.categories.create');
    Route::post('/categories/update/{id}', [KnowledgeBaseController::class, 'updateCategory'])->name('kb.categories.update');
    Route::post('/categories/delete/{id}', [KnowledgeBaseController::class, 'deleteCategory'])->name('kb.categories.delete');

    // 文章 API
    Route::get('/articles/list', [KnowledgeBaseController::class, 'articleList'])->name('kb.articles.list');
    Route::get('/articles/detail/{id}', [KnowledgeBaseController::class, 'articleDetail'])->name('kb.articles.detail');
    Route::post('/articles/create', [KnowledgeBaseController::class, 'createArticle'])->name('kb.articles.create');
    Route::post('/articles/update/{id}', [KnowledgeBaseController::class, 'updateArticle'])->name('kb.articles.update');
    Route::post('/articles/delete/{id}', [KnowledgeBaseController::class, 'deleteArticle'])->name('kb.articles.delete');
    Route::post('/articles/toggle/{id}', [KnowledgeBaseController::class, 'toggleArticle'])->name('kb.articles.toggle');

    // 图片上传
    Route::post('/upload-image', [KnowledgeBaseController::class, 'uploadImage'])->name('kb.upload-image');
});

// ========================
// 6. 插件管理路由
// ========================
Route::prefix('plugins')->middleware(['auth', EnsureProjectSelected::class])->group(function () {
    Route::get('/', [PluginController::class, 'index'])->name('plugins.index');
    Route::get('/list', [PluginController::class, 'list'])->name('plugins.list');
    Route::get('/detail', [PluginController::class, 'detail'])->name('plugins.detail');
    Route::post('/check-conflicts', [PluginController::class, 'checkConflicts'])->name('plugins.check-conflicts');
    Route::post('/install', [PluginController::class, 'install'])->name('plugins.install');
    Route::post('/uninstall', [PluginController::class, 'uninstall'])->name('plugins.uninstall');
    Route::post('/upload', [PluginController::class, 'upload'])->name('plugins.upload');
    Route::delete('/delete', [PluginController::class, 'delete'])->name('plugins.delete');

    // 插件截图图片
    Route::get('/snapshot-image', [PluginController::class, 'getSnapshotImage'])->name('plugins.snapshot-image');

    // 插件市场
    Route::get('/marketplace', function () {
        return inertia('Plugins/Marketplace');
    })->name('plugins.marketplace');
    // 插件市场 API
    Route::prefix('/marketplace')->name('marketplace.')->group(function () {
        Route::get('/list', [PluginMarketplaceController::class, 'index'])->name('plugins');
        Route::get('/detail/{pluginId}', [PluginMarketplaceController::class, 'detail'])->name('plugin.detail');
        Route::get('/search', [PluginMarketplaceController::class, 'search'])->name('search');
        Route::get('/snapshot-image', [PluginMarketplaceController::class, 'getSnapshotImage'])->name('snapshot-image');
        Route::post('/download', [PluginMarketplaceController::class, 'download'])->name('download');
        Route::post('/update', [PluginMarketplaceController::class, 'update'])->name('update');
        Route::get('/updates', [PluginMarketplaceController::class, 'checkUpdates'])->name('updates');
        Route::post('/clear-cache', [PluginMarketplaceController::class, 'clearCache'])->name('clear-cache');
    });
});
