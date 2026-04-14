import React, { useEffect, useState } from 'react';
import { Button, Form, Input, Space, message, Card, Row, Col, Divider, Alert, Switch, Select } from 'antd';
import api from '../../util/Service';
import { usePage } from '@inertiajs/react';
import Editor from '@monaco-editor/react';

interface FnDetail {
  id: number;
  name: string;
  slug?: string;
  type: 'endpoint' | 'hook' | 'cron';
  http_method?: string;
  code?: string | null;
}

const FunctionCodePage: React.FC = () => {
  const page = usePage<any>();
  const id: number = page?.props?.id;
  const type: 'endpoint' | 'hook' | 'cron' = page?.props?.type || 'endpoint';
  const prefix: string = page?.props?.project_info?.prefix || '';

  const [detail, setDetail] = useState<FnDetail | null>(null);
  const [code, setCode] = useState<string>('<?php \n\n');
  const [originalCode, setOriginalCode] = useState<string>('<?php \n\n');
  const [loading, setLoading] = useState<boolean>(false);
  const [saving, setSaving] = useState<boolean>(false);
  const [testing, setTesting] = useState<boolean>(false);
  const [payload, setPayload] = useState<string>(`{
  "userId": 123,
  "action": "publish",
  "data": {
    "title": "Hello",
    "tags": ["a","b"]
  }
}`);
  const [result, setResult] = useState<string>('');
  const [err, setErr] = useState<string>('');
  const [editorTheme, setEditorTheme] = useState<'vs' | 'vs-dark'>('vs-dark');

  const templates: Record<string, string> = {
    hello: `<?php\nreturn ['message' => 'Hello World', 'time' => time()];` ,
    params: `<?php\n$userId = (int)($payload['userId'] ?? 0);\n$action = (string)($payload['action'] ?? '');\n$data = (array)($payload['data'] ?? []);\nreturn [\n  'ok' => true,\n  'env' => $env,\n  'userId' => $userId,\n  'action' => $action,\n  'echo' => $data,\n];` ,
    http: `<?php\n$city = (string)($payload['city'] ?? 'Beijing');\n$resp = $Http->get('https://api.open-meteo.com/v1/forecast', [\n  'latitude' => 39.9042,\n  'longitude' => 116.4074,\n  'current_weather' => true,\n]);\nreturn [\n  'city' => $city,\n  'status' => $resp->status(),\n  'data' => $resp->json(),\n];` ,
    db: `<?php\n$table = (string)($payload['table'] ?? ($prefix . '_users'));\n$count = 0;\ntry { $count = (int)$db->count($table, ['active' => 1]); } catch (\\Throwable $e) { $count = 0; }\n$items = [];\ntry { $items = $db->select($table, ['active' => 1], ['id','name'], 10); } catch (\\Throwable $e) {}\nreturn ['table' => $table, 'count' => $count, 'items' => $items];` ,
    plugin: `<?php\nreturn $plugin->call('Plugins\\Demo\\Hello@run', [$payload, $env]);` ,
  };

  const importTemplate = (tplKey: string) => {
    if (!tplKey) { message.warning('请选择一个模板'); return; }
    const codeStr = templates[tplKey];
    setCode(codeStr || '');
    message.success('模板已导入');
  };

  const fetchDetail = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/manage/functions/detail/${id}`, { params: { type } });
      const d: FnDetail = res.data;
      setDetail(d);
      setCode(d?.code || '<?php \n\n');
      setOriginalCode(d?.code || '<?php \n\n');
    } catch (e: any) {
      message.error(e?.message || '获取详情失败');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchDetail(); /* eslint-disable-next-line */ }, [id, type]);

  const saveCode = async () => {
    if (!detail) return;
    setSaving(true);
    try {
      await api.post(`/manage/functions/update/${detail.id}`, { type, code });
      message.success('代码已保存');
      setErr('');
      // 避免重新渲染编辑器：不再重新拉取详情，直接更新本地基准
      setOriginalCode(code);
      setDetail(prev => prev ? { ...prev, code } : prev);
    } catch (e: any) {
      const msg = e?.response?.data?.errors?.message || e?.response?.data?.error || e?.message || '保存失败';
      setErr(String(msg));
      message.error(String(msg));
    } finally { setSaving(false); }
  };

  const runTest = async () => {
    if (!detail) return;
    if (!prefix) { message.error('未选择项目，无法测试'); return; }
    if (!detail.slug && type === 'endpoint') { message.error('缺少 Slug，无法测试'); return; }
    setTesting(true);
    try {
      let bodyObj: any = {};
      try { bodyObj = payload ? JSON.parse(payload) : {}; } catch { const m = '请求体 JSON 解析失败'; setErr(m); message.error(m); setTesting(false); return; }
      if ((detail.type || type) === 'hook') {
        const resp = await fetch(`/manage/functions/test/${detail.id}`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
          },
          body: JSON.stringify({ payload: bodyObj })
        });
        const text = await resp.text();
        let json: any = null; try { json = JSON.parse(text); } catch {}
        const pretty = json ? JSON.stringify(json, null, 2) : text;
        setResult(pretty || '');
        setErr('');
      } else {
        // 直接调用管理端 invoke 接口（允许测试禁用的函数）
        const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || '');
        const resp = await fetch(`/manage/functions/invoke/${encodeURIComponent(detail.slug || '')}`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
          },
          body: JSON.stringify(bodyObj)
        });
        const text = await resp.text();
        let json: any = null; try { json = JSON.parse(text); } catch {}
        const pretty = json ? JSON.stringify(json, null, 2) : text;
        setResult(pretty || '');
        setErr('');
      }
    } catch (e: any) {
      const msg = String(e?.message || e);
      setErr(msg);
      setResult('');
    } finally { setTesting(false); }
  };

  return (
    <div style={{ padding: 24 }}>
      <Space style={{ marginBottom: 12 }}>
        <span style={{ color: '#999' }}>仅支持 PHP 运行时代码</span>
        <Space size={8} wrap>
          <span style={{ color: '#666' }}>编辑器主题</span>
          <Switch
            checkedChildren="暗色"
            unCheckedChildren="亮色"
            checked={editorTheme === 'vs-dark'}
            onChange={(checked) => setEditorTheme(checked ? 'vs-dark' : 'vs')}
          />
          <span style={{ color: '#666' }}>模板</span>
          <Select
            onChange={(v) => importTemplate(v as any)}
            placeholder="选择模板"
            style={{ width: 180 }}
            options={[
              { label: 'HelloWorld', value: 'hello' },
              { label: '基础参数处理', value: 'params' },
              { label: '远程请求', value: 'http' },
              { label: '数据库操作', value: 'db' },
              { label: '插件调用', value: 'plugin' },
            ]}
          />
        </Space>
      </Space>

      <Card loading={loading} style={{ marginBottom: 16 }} title={detail ? `函数代码：${detail.name}${detail.slug ? ` (${detail.slug})` : ''}` : '函数代码'}>
        <div style={{ border: '1px solid #f0f0f0', borderRadius: 6, overflow: 'hidden' }}>
          <Editor
            height="420px"
            language="php"
            theme={editorTheme}
            value={code}
            options={{ fontSize: 12, minimap: { enabled: false }, automaticLayout: true, wordWrap: 'on', padding: { top: 12, bottom: 12 } }}
            onChange={(v) => setCode(v ?? '')}
          />
        </div>
        <Space style={{ marginTop: 12 }}>
          <Button type="primary" onClick={saveCode} loading={saving} disabled={code === originalCode}>保存代码</Button>
          <Button onClick={() => { setCode(detail?.code || ''); message.success('已重置为已保存的代码'); }}>重置</Button>
        </Space>
        {err ? <Alert style={{ marginTop: 12 }} type="error" showIcon message={err} /> : null}
      </Card>

      <Card title="测试">
        <Row gutter={12}>
          <Col span={10}>
            <Space direction="vertical" style={{ width: '100%' }}>
              {(detail?.type || type) !== 'hook' ? (
                <>
                  <Input addonBefore="方法" value={(detail?.http_method || 'POST').toUpperCase()} readOnly />
                  <Input addonBefore="URL" value={detail && prefix ? `/open//func/${prefix}_${detail.slug || ''}` : ''} readOnly />
                </>
              ) : null}
              <Form layout="vertical">
                <Form.Item label="请求体 (JSON) ">
                  <Input.TextArea rows={10} value={payload} onChange={(e) => setPayload(e.target.value)} />
                </Form.Item>
              </Form>
            </Space>
          </Col>
          <Col span={4}>
            <div style={{ display: 'flex', height: '100%', alignItems: 'start', justifyContent: 'center', flexDirection: 'column', gap: 8 }}>
              <Button type="primary" onClick={runTest} loading={testing} disabled={!detail}>测试</Button>
              <span style={{ color: '#999', fontSize: 12 }}>请先保存代码后再测试</span>
            </div>
          </Col>
          <Col span={10}>
            <Form layout="vertical">
              <Form.Item label="执行结果">
                <pre style={{ background: '#fafafa', padding: 12, border: '1px solid #f0f0f0', borderRadius: 4, minHeight: 240, maxHeight: 340, overflow: 'auto', whiteSpace: 'pre-wrap' }}>{result || '（无）'}</pre>
              </Form.Item>
            </Form>
          </Col>
        </Row>
      </Card>
    </div>
  );
};

export default FunctionCodePage;
