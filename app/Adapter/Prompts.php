<?php

namespace App\Adapter;

class Prompts
{
    // 清洗用户提问内容，格式转义等
    public static function formatQuestion($question)
    {

        $question = str_replace("'", "\\'", $question);
        $question = str_replace('"', '\\"', $question);

        return $question;
    }

    public static function getRichTextEditPrompt(string $instruction, string $html): string
    {
        $instruction = self::formatQuestion($instruction);
        // 原始HTML内容无需转义换引号，放置在多行字符串中

        $content = <<<HOC
你是一名专业的文案编辑与排版助手。请根据“编辑需求”对“原始HTML富文本”进行改写与优化。

编辑需求：{$instruction}

原始HTML富文本：
{$html}

输出要求：
1. 仅输出一个 JSON 对象（不要包含任何解释、注释或 Markdown 代码块标记）。
2. 对象必须包含以下键：
   - result_text：string，对你的修改思路与效果的简短说明，便于在对话窗口展示。
   - html：string，改写后的 HTML 富文本内容。
3. html 中仅保留基础排版标签（如 p、ul、ol、li、strong、em、h1-h6、a、img 等），避免引入外部脚本或样式。
4. 如果编辑需求含糊，请在 result_text 中给出合理的假设或补充。
HOC;

        return $content;
    }

    public static function getMarkdownEditPrompt(string $instruction, string $markdown): string
    {
        $instruction = self::formatQuestion($instruction);

        $content = <<<HOC
你是一名专业的文案编辑助手。请根据"编辑需求"对"原始 Markdown 文本"进行改写与优化。

编辑需求：{$instruction}

原始 Markdown 文本：
{$markdown}

输出要求：
1. 仅输出一个 JSON 对象（不要包含任何解释、注释或 Markdown 代码块标记）。
2. 对象必须包含以下键：
   - result_text：string，对你的修改思路与效果的简短说明，便于在对话窗口展示。
   - markdown：string，改写后的 Markdown 文本内容。
3. 保持 Markdown 格式规范，合理使用标题、列表、加粗、斜体、代码块等语法。
4. 不要改变原文的 Markdown 结构层级（如标题级别），除非编辑需求明确要求。
5. 如果编辑需求含糊，请在 result_text 中给出合理的假设或补充。
HOC;

        return $content;
    }

    public static function getRichTextEditStepTextPrompt(string $instruction, string $sourceChunk, string $summary, string $recentText, int $seq, int $totalSteps, int $targetChunkLength): string
    {
        $instruction = self::formatQuestion($instruction);
        $sourceChunk = self::formatQuestion($sourceChunk);
        $summary = self::formatQuestion($summary);
        $recentText = self::formatQuestion($recentText);

        $content = <<<HOC
你是一名专业的文案编辑与写作助手。你正在把一篇较长的内容按“分段”逐步生成。

编辑需求：{$instruction}

原文片段（本段待改写）：
{$sourceChunk}

已生成内容摘要（用于保持一致性）：
{$summary}

最近片段（用于延续语气与上下文）：
{$recentText}

当前任务：生成第 {$seq} 段（共 {$totalSteps} 段），目标约 {$targetChunkLength} 字。

输出要求：
1. 仅输出纯文本正文（不要输出任何解释、标题序号、Markdown、代码块、JSON）。
2. 必须基于“原文片段”进行改写与优化，不要脱离原文另起炉灶。
3. 与已生成内容语气一致、逻辑连贯，避免重复已生成内容。
3. 保持段落清晰（可用空行分段）。
HOC;

        return $content;
    }

    public static function getRichTextTextToHtmlPrompt(string $instruction, string $text): string
    {
        $instruction = self::formatQuestion($instruction);
        $text = self::formatQuestion($text);

        $content = <<<HOC
你是一名专业的排版助手。请根据“编辑需求”将“纯文本正文”排版为安全的 HTML 富文本。

编辑需求：{$instruction}

纯文本正文：
{$text}

输出要求：
1. 仅输出一个 JSON 对象（不要包含任何解释、注释或 Markdown 代码块标记）。
2. 对象必须包含以下键：
   - html：string，排版后的 HTML 富文本内容。
3. html 中仅保留基础排版标签（如 p、ul、ol、li、strong、em、h1-h6、a 等），避免引入外部脚本或样式。
HOC;

        return $content;
    }

    public static function getBatchContentPrompt(string $userPrompt, array $fields, int $count = 5): string
    {
        $userPrompt = self::formatQuestion($userPrompt);
        $count = max(1, min(50, (int) $count));

        $fieldsJson = json_encode(array_values($fields), JSON_UNESCAPED_UNICODE);

        $content = <<<HOC
你是一名内容批量生成助手。根据“用户意图”和“字段定义”，一次性生成 {$count} 条结构化内容，并严格按字段英文名（field）作为键输出。

用户意图：{$userPrompt}

字段定义：{$fieldsJson}

输出要求：
1. 仅输出 JSON 数组，长度为 {$count}。数组每个元素为一个对象（表示一条记录）。
2. 每个对象的键必须是字段定义中的 field 值，对应的 value 为生成结果。
3. 根据 type 控制内容：
   - input：简短文本，贴近用户意图。
   - textarea：不超过 400 字的自然语言段落。
   - richText：不超过 1200 字，可包含基础 HTML 段落标签（<p>、<ul><li> 等）。
   - numInput：阿拉伯数字。
4. 必须与用户意图高度相关，不要空值；严格遵守长度限制；不同记录之间要有差异性。

示例（结构示意，键名请使用字段定义中的 field）：
[
  {"title":"...","desc":"..."},
  {"title":"...","desc":"..."}
]
HOC;

        return $content;
    }

    public static function getContentPrompt(string $userPrompt, array $fields): string
    {
        $userPrompt = self::formatQuestion($userPrompt);

        $fieldsJson = json_encode(array_values($fields), JSON_UNESCAPED_UNICODE);

        $content = <<<HOC
你是一名内容生成助手，请严格按照以下要求输出：

目标：根据“用户意图”和“字段定义”生成内容。

用户意图：{$userPrompt}

字段定义（顺序即为输出顺序）：{$fieldsJson}

输出要求：
1. 仅输出 JSON 数组（不要包含任何解释、注释或 Markdown 代码块标记）。
2. 数组中的每一项必须为对象，且必须包含字段：id、type、label、value。
3. 根据 type 生成 value：
   - input：简短文本，贴近用户意图。
   - textarea：不超过 400 字的自然语言段落。
   - richText：不超过 1200 字，可包含少量基础 HTML 段落标签（例如 <p>、<ul><li>）。
   - numInput：阿拉伯数字。
4. 内容需与用户意图高度相关，不要空值；严格遵守长度限制。
5. 保持与“字段定义”相同的顺序。

示例（示意结构，勿照抄具体值）：
[
  {"id":"title","type":"input","label":"标题","value":"..."},
  {"id":"desc","type":"textarea","label":"描述","value":"..."}
]
HOC;

        return $content;
    }

    public static function getTopicsPrompt(string $userPrompt, int $count = 10): string
    {
        $userPrompt = self::formatQuestion($userPrompt);
        $count = max(1, min(50, (int) $count));

        $content = <<<HOC
你是一名新媒体选题助手。根据“用户意图”，生成 {$count} 个紧密相关且不重复的内容主题。

用户意图：{$userPrompt}

输出要求：
1. 仅输出 JSON 数组（不要包含任何解释、注释或 Markdown 代码块标记）。
2. 数组元素为字符串，每个元素是一条主题标题，长度建议不超过 30 字。
3. 主题应彼此区分、避免高度相似和空泛表达。
HOC;

        return $content;
    }

    public static function getChildContentPrompt(string $parentPrompt, string $topic): string
    {
        $parentPrompt = self::formatQuestion($parentPrompt);
        $topic = self::formatQuestion($topic);

        $content = <<<HOC
你是一名内容创作助手。请根据"父任务需求"和"子主题"，生成一段符合主题的内容。

父任务需求：{$parentPrompt}

子主题：{$topic}

输出要求：
1. 仅输出 JSON 对象（不要包含任何解释、注释或 Markdown 代码块标记）。
2. 对象包含键值对，键为字段名，值为字段内容。
3. 内容应紧扣子主题，同时符合父任务的整体要求。
HOC;

        return $content;
    }

    public static function getSmartCapturePrompt(string $fieldsJson, string $elementsJson): string
    {
        $content = <<<HOC
你是一名数据采集助手。请根据"页面元素"提取数据，并映射到"表单字段"。

表单字段定义：
{$fieldsJson}

页面元素：
{$elementsJson}

输出要求：
1. 仅输出 JSON 对象（不要包含任何解释、注释或 Markdown 代码块标记）。
2. 对象的键为字段名，值为提取的数据。
3. 如果某个字段没有对应的数据，该字段值为 null。
HOC;

        return $content;
    }

    public static function getSimpleCapturePrompt(string $fieldsJson, string $pageText): string
    {
        $content = <<<HOC
你是一名数据采集助手。请根据"页面内容"提取数据，并填充到"表单字段"。

表单字段定义：
{$fieldsJson}

页面内容：
{$pageText}

输出要求：
1. 仅输出 JSON 对象（不要包含任何解释、注释或 Markdown 代码块标记）。
2. 对象的键为字段名，值为提取的数据。
3. 如果某个字段没有对应的数据，该字段值为 null。
HOC;

        return $content;
    }

    public static function getSchemaPrompt($question)
    {

        $question = self::formatQuestion($question);

        $content =
<<<HOC
设计一个表单，根据需求判断表单包含哪些字段，
需求是{$question}

要求：
1.仅返回结果，不要有其他的内容输出
2.请用JSON纯文本格式返回，JSON中不要有注释
3.JSON中不要有换行

下面是例子
需求：设计一个广告宣传页
回答：{"page_id":"root","page_name":"广告宣传页","mold_type":"list","children":[{"label":"广告标题","type":"input","field":"title"},{"label":"广告描述","type":"textarea","field":"description"},{"label":"广告图片","type":"picUpload","field":"pic"},{"label":"广告链接","type":"input","field":"link"},{"label":"开始时间","type":"datePicker","field":"start_time"},{"label":"结束时间","type":"datePicker","field":"end_time"},{"label":"展示位置","type":"select","field":"position","options":["首页","列表页"]}]}

说明：
返回内容中page_id是需求的英文简称，page_name是需求的中文名称，mold_type是表单类型写死为list就行，children是字段列表， lable是字段名，field是字段英文名，type是字段表单类型包含下面这些类型
[input,textarea,radio,switch,checkbox,select,numInput,colorPicker,datePicker,timePicker,fileUpload,picUpload,picGallery,richText]
HOC;

        return $content;
    }

    public static function getPageGenerationPrompt(string $userPrompt, array $models = []): string
    {
        $userPrompt = self::formatQuestion($userPrompt);
        $modelsInfo = '';
        if (! empty($models)) {
            $modelsJson = json_encode($models, JSON_UNESCAPED_UNICODE);
            $modelsInfo = <<<INFO

当前项目的内容模型（如果页面需要数据交互，请使用这些模型的 table_name）：
{$modelsJson}
INFO;
        }

        $content = <<<HOC
你是一名前端页面生成助手。请根据用户需求生成一个完整的单文件 HTML 页面。
{$modelsInfo}
用户需求：{$userPrompt}

## Mosure SDK（系统会自动注入，你不需要引入）
页面中可以通过 window.Mosure 对象与内容模型进行数据交互。根据模型类型不同，使用不同的方法：

### 内容列表模型（list 类型）的方法
- Mosure.getList(tableName, params) — 获取列表，params 可选如 {page:1, page_size:10}，返回 {code:200, data:{items:[{id,field1,...},...], total:100, page:1, page_size:15, page_count:7, fields:[...]}}
- Mosure.getItem(tableName, id) — 获取单条详情，返回 {code:200, data:{id, field1, field2, created_at, updated_at, ...}}，不存在返回 {code:404, message:'内容不存在'}
- Mosure.createItem(tableName, data) — 创建，data 为字段键值对，返回 {code:200, data:{message:'创建成功'}}
- Mosure.updateItem(tableName, id, data) — 更新，返回 {code:200, data:{message:'更新成功'}}
- Mosure.deleteItem(tableName, id) — 删除，返回 {code:200, data:{message:'删除成功'}}

### 内容单页模型（single 类型）的方法
- Mosure.getPage(tableName) — 获取单页内容（整个页面只有一条数据，键值对形式），返回 {code:200, data:{field1:value1, field2:value2, ...}}
- Mosure.updatePage(tableName, data) — 更新单页内容，data 为字段键值对，返回 {code:200, data:[]}

所有方法都是 async 的，需要 await 调用。code=200 表示成功，否则失败（message 字段含错误信息）。
tableName 参数使用上面内容模型中的 table_name。list 类型模型使用 getList/getItem/createItem/updateItem/deleteItem，single 类型模型使用 getPage/updatePage。

## 输出要求
仅输出一个 JSON 对象（不要包含任何解释、注释或 Markdown 代码块标记），包含以下字段：
1. slug（string）：页面 URL 标识，仅小写字母、数字和短横线，如 "todo-app"
2. title（string）：页面标题
3. description（string）：页面简短描述
4. html_content（string）：完整的 HTML 页面代码

## HTML 页面规范
1. 必须是完整的 HTML 文档（含 <!DOCTYPE html>、<html>、<head>、<body>）
2. 所有 CSS 使用内联 <style> 标签，所有 JS 使用内联 <script> 标签
3. 页面设计应简洁美观，使用现代化 UI 风格，适配移动端
4. 不要引入任何外部 CSS/JS 库（不要用 CDN）
5. 不要在 HTML 中包含 Mosure SDK 的 script 标签（系统自动注入）
6. 如果页面需要与数据交互，使用 window.Mosure 的方法
7. 确保页面可以独立运行，所有功能完整可用
HOC;

        return $content;
    }

    public static function getFrontendPagePlanPrompt(string $userPrompt, array $pages = [], ?string $currentPageSlug = null): string
    {
        $userPrompt = self::formatQuestion($userPrompt);
        $pagesJson = json_encode(array_values($pages), JSON_UNESCAPED_UNICODE);
        $currentPageSlug = self::formatQuestion((string) ($currentPageSlug ?? ''));

        return <<<HOC
你是一名前端页面开发助手的任务规划器。请根据用户需求，判断这是：
1. 新建页面
2. 修改现有页面
3. 需要澄清
4. 与前端页面无关，应拒绝

当前项目已有页面：
{$pagesJson}

当前会话页面焦点：
{$currentPageSlug}

用户需求：
{$userPrompt}

输出要求：
1. 仅输出一个 JSON 对象。
2. JSON 必须包含以下字段：
   - action: create | update | clarify | reject
   - target_slug: string|null
   - target_title: string|null
   - clarification: string
   - description: string
   - requirements: string[]
   - change_summary: string[]
3. 规则：
   - 如果用户说“再新建一个”“新建另一个”，即使当前有页面焦点，也优先判断为 create。
   - 如果用户说“修改刚才那个页面”“改一下这个页面”，优先参考当前会话页面焦点。
   - 如果页面指向不明确，action 输出 clarify，并在 clarification 中给出追问。
   - 如果问题与前端页面无关，action 输出 reject。
   - target_slug 只能使用小写字母、数字和短横线。
HOC;
    }

    public static function getFrontendPageCreatePrompt(string $userPrompt, array $models, string $targetSlug, string $targetTitle, array $requirements = [], ?string $description = null): string
    {
        $userPrompt = self::formatQuestion($userPrompt);
        $targetSlug = self::formatQuestion($targetSlug);
        $targetTitle = self::formatQuestion($targetTitle);
        $description = self::formatQuestion((string) ($description ?? ''));
        $requirementsJson = json_encode(array_values($requirements), JSON_UNESCAPED_UNICODE);
        $modelsJson = json_encode(array_values($models), JSON_UNESCAPED_UNICODE);

        $designBlock = self::frontendDesignRequirementsBlock();
        $sdkBlock = self::frontendMosureSdkBlock();

        return <<<HOC
你是一名前端页面开发助手，请为 Mosure 生成一个新的托管页面。

用户原始需求：
{$userPrompt}

目标页面：
- slug: {$targetSlug}
- title: {$targetTitle}
- description: {$description}

页面需求拆解：
{$requirementsJson}

当前项目内容模型（用于页面真实数据渲染）：
{$modelsJson}

{$sdkBlock}

{$designBlock}

Mosure 专属约束：
1. 只输出 HTML/CSS/JavaScript。
2. 不输出 Vue、React、Svelte 等源码。
3. 不输出构建脚本。
4. 所有 CSS 使用 <style>，所有 JS 使用 <script>，优先内联。
5. 只要当前项目存在内容模型，页面必须使用 window.Mosure 获取真实数据并渲染，不能只展示静态假数据。
6. 页面将保存到单页托管系统，因此必须输出完整 HTML 文档。
7. 不要引入外部 CDN。
8. 如果使用了内容模型，必须在 html_content 的 <script> 中体现真实调用（如 getList/getItem/getPage），table 参数必须来自上方模型的 table_name，不得臆造。
9. 页面初始可以有 loading/empty/error 状态，但成功态必须以 window.Mosure 返回的数据驱动渲染。

输出要求：
1. 仅输出一个 JSON 对象。
2. 必须包含：
   - slug
   - title
   - description
   - html_content
   - change_summary
3. html_content 必须是完整 HTML 文档。
4. 若存在内容模型，change_summary 中必须明确写出使用了哪个 table_name，以及通过哪个 window.Mosure 方法取数。
HOC;
    }

    public static function getFrontendPageUpdatePrompt(string $userPrompt, array $models, string $targetSlug, string $targetTitle, string $existingHtml, array $changeSummary = []): string
    {
        $userPrompt = self::formatQuestion($userPrompt);
        $targetSlug = self::formatQuestion($targetSlug);
        $targetTitle = self::formatQuestion($targetTitle);
        $modelsJson = json_encode(array_values($models), JSON_UNESCAPED_UNICODE);
        $changeSummaryJson = json_encode(array_values($changeSummary), JSON_UNESCAPED_UNICODE);

        $designBlock = self::frontendDesignRequirementsBlock();
        $sdkBlock = self::frontendMosureSdkBlock();

        return <<<HOC
你是一名前端页面开发助手，请在保留原有页面主要结构的前提下，按用户要求修改页面。

用户原始需求：
{$userPrompt}

目标页面：
- slug: {$targetSlug}
- title: {$targetTitle}

本次变更要点：
{$changeSummaryJson}

当前项目内容模型（用于页面真实数据渲染）：
{$modelsJson}

{$sdkBlock}

当前页面 HTML：
{$existingHtml}

{$designBlock}

Mosure 专属约束：
1. 只输出 HTML/CSS/JavaScript。
2. 不输出 Vue、React、Svelte 等源码。
3. 不输出构建脚本。
4. 所有 CSS 使用 <style>，所有 JS 使用 <script>，优先内联。
5. 只要当前项目存在内容模型，修改后页面必须继续或补充使用 window.Mosure 获取真实数据并渲染，不能退化为静态假数据。
6. 必须尽量保留现有页面结构与功能，只做与需求相关的最小必要修改。
7. 不要引入外部 CDN。
8. 如果涉及内容模型，必须在 html_content 的 <script> 中体现真实调用（如 getList/getItem/getPage），table 参数必须来自上方模型的 table_name，不得臆造。
9. 页面初始可以有 loading/empty/error 状态，但成功态必须以 window.Mosure 返回的数据驱动渲染。

输出要求：
1. 仅输出一个 JSON 对象。
2. 必须包含：
   - slug
   - title
   - description
   - html_content
   - change_summary
3. html_content 必须是完整 HTML 文档。
4. 若存在内容模型，change_summary 中必须明确写出使用了哪个 table_name，以及通过哪个 window.Mosure 方法取数。
HOC;
    }

    private static function frontendDesignRequirementsBlock(): string
    {
        return <<<'TXT'
前端设计约束：
1. 页面必须有明确视觉层级，不要做成通用后台模板感。
2. 配色要收敛，主色、辅色、中性色清晰。
3. 避免默认紫色系、默认暗黑风、默认系统字体组合。
4. 背景需要有层次，避免纯白空板。
5. 动效只保留少量必要动画，不堆微交互。
6. 移动端必须优先可用。
TXT;
    }

    private static function frontendMosureSdkBlock(): string
    {
        return <<<'TXT'
Mosure SDK（系统会自动注入到页面中，你不需要引入）：
页面中通过 window.Mosure 进行数据交互。所有方法都是 async，需要 await。

list 类型模型方法：
1. Mosure.getList(tableName, params) 获取列表，params 可选 { page, page_size }
2. Mosure.getItem(tableName, id) 获取单条
3. Mosure.createItem(tableName, data) 创建
4. Mosure.updateItem(tableName, id, data) 更新
5. Mosure.deleteItem(tableName, id) 删除

single 类型模型方法：
6. Mosure.getPage(tableName) 获取单页数据
7. Mosure.updatePage(tableName, data) 更新单页数据

通用规则：
- code=200 表示成功，非 200 按失败处理并展示 message
- tableName 必须使用上方内容模型里的 table_name
- list 模型只能使用 getList/getItem/createItem/updateItem/deleteItem
- single 模型只能使用 getPage/updatePage
TXT;
    }
}
