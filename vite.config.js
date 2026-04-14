import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

function getPackageName(id) {
    const modulePath = id.split('node_modules/')[1];

    if (! modulePath) {
        return null;
    }

    const parts = modulePath.split('/');

    if (parts[0].startsWith('@')) {
        return `${parts[0]}/${parts[1]}`;
    }

    return parts[0];
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: false,
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: 5174,
        strictPort: true,
        cors: true,
        watch: {
            ignored: [
                '.env',
                '.env.local',
                '.env.development',
                '.env.production',
            ],
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (! id.includes('node_modules')) {
                        return;
                    }

                    const pkg = getPackageName(id);

                    if (! pkg) {
                        return 'vendor-misc';
                    }

                    if (pkg === 'react' || pkg === 'react-dom' || pkg === 'scheduler') {
                        return 'vendor-react';
                    }

                    if (pkg.startsWith('@inertiajs/') || pkg === 'axios' || pkg === 'nprogress') {
                        return 'vendor-inertia';
                    }

                    if (
                        pkg === 'antd' ||
                        pkg.startsWith('@ant-design/') ||
                        pkg.startsWith('@rc-component/') ||
                        pkg.startsWith('rc-')
                    ) {
                        return 'vendor-antd';
                    }

                    if (pkg === 'dayjs') {
                        return 'vendor-dayjs';
                    }

                    if (pkg === 'monaco-editor' || pkg === '@monaco-editor/react') {
                        return 'vendor-monaco';
                    }

                    if (pkg.startsWith('@milkdown/')) {
                        return 'vendor-milkdown';
                    }

                    if (pkg.startsWith('prosemirror-')) {
                        return 'vendor-prosemirror';
                    }

                    if (pkg.startsWith('@codemirror/')) {
                        return 'vendor-codemirror';
                    }

                    if (pkg.startsWith('@lezer/')) {
                        return 'vendor-lezer';
                    }

                    if (pkg === 'katex') {
                        return 'vendor-katex';
                    }

                    if (
                        pkg === 'amis' ||
                        pkg.startsWith('amis-') ||
                        pkg === 'vue' ||
                        pkg.startsWith('@vue/') ||
                        pkg === 'lodash-es' ||
                        pkg === 'qs' ||
                        pkg === 'json2mq'
                    ) {
                        return 'vendor-amis';
                    }

                    if (pkg === 'quill' || pkg === 'react-quill' || pkg === 'quill-image-resize') {
                        return 'vendor-quill';
                    }

                    if (pkg === 'react-sortablejs' || pkg === 'sortablejs') {
                        return 'vendor-sortable';
                    }

                    if (
                        pkg === 'react-markdown' ||
                        pkg === 'remark-gfm' ||
                        pkg.startsWith('remark-') ||
                        pkg.startsWith('rehype-') ||
                        pkg.startsWith('mdast-') ||
                        pkg.startsWith('micromark-') ||
                        pkg.startsWith('hast-') ||
                        pkg === 'unified' ||
                        pkg.startsWith('unist-') ||
                        pkg === 'vfile' ||
                        pkg.startsWith('vfile-') ||
                        pkg === 'property-information' ||
                        pkg === 'hast-util-to-jsx-runtime'
                    ) {
                        return 'vendor-markdown';
                    }

                    if (pkg === 'lodash') {
                        return 'vendor-lodash';
                    }

                    return 'vendor-misc';
                },
            },
        },
    },
});
