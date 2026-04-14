<?php

namespace App\Http\Controllers\Admin;

use App\Services\ApiKeyService;
use Illuminate\Http\Request;

class ApiKeyController extends BaseAdminController
{
    private $apiKeyService;

    public function __construct(ApiKeyService $apiKeyService)
    {
        $this->apiKeyService = $apiKeyService;
    }

    /**
     * 显示API密钥管理页面
     */
    public function index()
    {
        $apiKeysData = $this->apiKeyService->getList(15, 1);

        return viewShow('ApiKey/ApiKeyList', [
            'title' => 'API密钥管理',
            'apiKeys' => $apiKeysData['data'],
            'total' => $apiKeysData['total'],
        ]);
    }

    /**
     * 创建API密钥
     */
    public function create(Request $request)
    {
        try {
            $this->apiKeyService->create($request->all());

            return back();
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['message' => $e->getMessage()]);
        }
    }

    /**
     * 编辑API密钥
     */
    public function edit(Request $request, $id)
    {
        try {
            $this->apiKeyService->update($id, $request->all());

            return back();
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['message' => $e->getMessage()]);
        }
    }

    /**
     * 删除API密钥
     */
    public function delete($id)
    {
        try {
            $this->apiKeyService->delete($id);

            return back();
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    /**
     * 生成新的API密钥
     */
    public function generate(Request $request)
    {
        try {
            $result = $this->apiKeyService->generateKey($request->all());

            return success($result, 'API密钥生成成功');
        } catch (\Exception $e) {
            return error([], $e->getMessage());
        }
    }

    /**
     * 禁用/启用API密钥
     */
    public function toggle($id)
    {
        try {
            $this->apiKeyService->toggle($id);

            return back();
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }
}
