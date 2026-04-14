<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Mosure</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100">
        <main class="mx-auto flex min-h-screen max-w-6xl flex-col justify-center px-6 py-16 lg:px-12">
            <div class="inline-flex w-fit items-center rounded-full border border-cyan-400/30 bg-cyan-400/10 px-4 py-1 text-sm text-cyan-200">
                Mosure 开源内容管理系统
            </div>

            <div class="mt-8 grid gap-10 lg:grid-cols-[1.4fr_0.9fr] lg:items-center">
                <section>
                    <h1 class="text-4xl font-bold tracking-tight text-white lg:text-6xl">面向内容、表单、插件与 OpenAPI 的一体化 CMS</h1>
                    <p class="mt-6 max-w-3xl text-base leading-8 text-slate-300 lg:text-lg">
                        Mosure 提供安装向导、项目管理、低代码表单、内容建模、OpenAPI、知识库与插件能力，适合作为首版开源项目的演示入口页。
                    </p>

                    <div class="mt-8 flex flex-wrap gap-4">
                        <a href="{{ route('login') }}" class="rounded-lg bg-cyan-400 px-5 py-3 font-medium text-slate-950 transition hover:bg-cyan-300">进入后台</a>
                        <a href="{{ url('/install') }}" class="rounded-lg border border-slate-700 px-5 py-3 font-medium text-slate-100 transition hover:border-slate-500 hover:bg-slate-900">安装向导</a>
                    </div>
                </section>

                <aside class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-2xl shadow-cyan-950/20">
                    <h2 class="text-lg font-semibold text-white">首版开源建议入口</h2>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-300">
                        <li>• 默认访问后台登录：<span class="text-cyan-200">/login</span></li>
                        <li>• 未安装环境可直接进入：<span class="text-cyan-200">/install</span></li>
                        <li>• 推荐使用文档中的 <span class="text-cyan-200">bin/start.sh</span> / <span class="text-cyan-200">bin/start.bat</span> 启动</li>
                        <li>• 默认示例访问地址：<span class="text-cyan-200">http://localhost:9445</span></li>
                    </ul>
                </aside>
            </div>
        </main>
    </body>
</html>
