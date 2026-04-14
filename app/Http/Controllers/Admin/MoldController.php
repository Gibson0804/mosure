<?php

namespace App\Http\Controllers\Admin;

use App\Repository\MoldRepository;
use App\Services\MoldService;
use App\Services\ProjectConfigService;
use App\Services\SystemConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;

class MoldController extends BaseAdminController
{
    private $moldService;

    private ProjectConfigService $projectConfigService;

    private SystemConfigService $systemConfigService;

    public function __construct(MoldService $moldService, ProjectConfigService $projectConfigService, SystemConfigService $systemConfigService)
    {
        $this->moldService = $moldService;
        $this->projectConfigService = $projectConfigService;
        $this->systemConfigService = $systemConfigService;
    }

    public function moldAdd(Request $request)
    {

        if ($request->isMethod('post')) {

            $this->validate($request, [
                'name' => ['required', 'max:100'],
                'table_name' => ['required', 'max:100'],
                'mold_type' => ['required'],
                'fields' => ['required'],
            ], [
                'name.required' => '页面名称不能为空',
                'name.max' => '页面名称最多10个字符',
                'table_name.required' => '标识ID不能为空',
                'table_name.max' => '标识ID最多150个字符',
                'mold_type.required' => '类型不能为空',
                'fields.required' => '字段不能为空',
            ]);

            $data = $request->input();

            $moldId = $this->moldService->addForm($data);

            return Redirect::route('mold.list');
        }

        $initSchemas = [
            'page_name' => $this->moldService->getDefaultName(),
            'page_id' => $this->moldService->getDefaultTableName(),
            'mold_type' => MoldRepository::CONTENT_MOLD_TYPE,
            'children' => [
                [
                    'label' => '文本域1',
                    'type' => 'input',
                    'field' => 'input_0juljn6w',
                    'id' => 'input_0juljn6w',
                ],
                [
                    'label' => '文本域2',
                    'type' => 'input',
                    'field' => 'input_0juljn6w333',
                    'id' => 'input_0juljn6w333',
                ],
            ],
        ];

        return viewShow('Mold/MoldAdd', ['info' => $initSchemas]);
    }

    public function moldEdit(Request $request, $id)
    {

        if ($request->isMethod('post')) {
            $request->validate([
                'name' => ['required', 'max:100'],
                'table_name' => ['required', 'max:100'],
                'mold_type' => ['required'],
                'fields' => ['required'],
            ], [
                'name.required' => '页面名称不能为空',
                'name.max' => '页面名称最多100个字符',
                'table_name.required' => '标识ID不能为空',
                'table_name.max' => '标识ID最多100个字符',
                'mold_type.required' => '类型不能为空',
                'fields.required' => '字段不能为空',
            ]);

            $data = $request->input();
            $fields = $data['fields'];
            $data['fields'] = json_encode($data['fields']);

            $moldInfo = $this->moldService->getMoldInfo($id);
            $data['table_name'] = $moldInfo['table_name'];

            $this->moldService->editFormById($data, $id);

            if ($data['mold_type'] = MoldRepository::CONTENT_MOLD_TYPE) {
                $this->moldService->getTableByField($data['table_name'], $fields);
            }
        }

        $info = $this->moldService->getMoldInfo($id);

        $res = [
            'page_id' => removeMcPrefix($info['table_name']),
            'page_name' => $info['name'],
            'mold_type' => $info['mold_type'],
            'children' => json_decode($info['fields'], true),
        ];

        return viewShow('Mold/MoldEdit', [
            'info' => $res,
            'id' => $id,
        ]);
    }

    public function updateMoldById(Request $request, int $id)
    {

        $data = $request->input();
        // 参数过滤及校验
        $data = Arr::only($data, ['list_show_fields', 'filter_show_fields']);
        $this->moldService->editFormById($data, $id);

        return success('修改成功');
    }

    public function moldList(): \Inertia\Response
    {

        $info = $this->moldService->getAllMold();

        $resInfo = [];
        foreach ($info as $key => $one) {
            $resInfo[] = [
                'key' => $one['id'],
                'name' => $one['name'],
                'table_name' => removeMcPrefix($one['table_name']),
                'mold_type' => $one['mold_type'],
                'created_at' => $one['created_at']->format('Y-m-d H:i:s'),
            ];
        }

        return viewShow('Mold/MoldList', [
            'info' => $resInfo,
        ]);
    }

    public function suggestMold(Request $request)
    {
        $requestedBy = optional($request->user())->id;
        $res = $this->moldService->suggestMold($request->input('suggest'), '', $requestedBy);

        return success($res);
    }

    public function deleteCheck(Request $request, $id)
    {
        $res = $this->moldService->deleteCheck($id);

        return success($res);
    }

    public function delete(Request $request, $id)
    {
        $res = $this->moldService->delete($id);

        return Redirect::route('mold.list');
    }

    /**
     * 前端表单构建器：获取当前项目全部模型（molds）
     * 返回字段尽量精简以便前端展示
     */
    public function builderModelsAndFields(Request $request)
    {
        $list = $this->moldService->getAllMold();

        $models = [];
        foreach ($list as $m) {

            $fields = [];
            $arr = is_array($m['fields']) ? $m['fields'] : json_decode($m['fields'], true);
            if (is_array($arr)) {
                foreach ($arr as $f) {
                    $fields[] = [
                        'field' => $f['field'] ?? '',
                        'label' => $f['label'] ?? ($f['field'] ?? ''),
                        'type' => $f['type'] ?? 'input',
                    ];
                }
            }
            $models[] = [
                'id' => $m['id'] ?? $m->id,
                'name' => $m['name'] ?? $m->name,
                'table_name' => removeMcPrefix($m['table_name'] ?? $m->table_name),
                'mold_type' => $m['mold_type'] ?? $m->mold_type,
                'fields' => $fields,
            ];
        }

        return success(['models' => $models]);
    }

    /**
     * 前端表单构建器：获取指定模型的字段列表
     * 只返回基础字段信息：field、label、type
     */
    public function builderModelFields(Request $request, int $id)
    {
        $info = $this->moldService->getMoldInfo($id);
        $fields = [];

        if ($info && ! empty($info['fields'])) {
            $arr = is_array($info['fields']) ? $info['fields'] : json_decode($info['fields'], true);
            if (is_array($arr)) {
                foreach ($arr as $f) {
                    $fields[] = [
                        'field' => $f['field'] ?? '',
                        'label' => $f['label'] ?? ($f['field'] ?? ''),
                        'type' => $f['type'] ?? 'input',
                    ];
                }
            }
        }

        return success(['fields' => $fields]);
    }
}
