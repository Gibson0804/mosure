import React from 'react'
import { createInertiaApp } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'
import BaseLayout from './Layouts/BaseLayout'
import MainLayout from './Layouts/MainLayout'
import ProjectLayout from './Layouts/ProjectLayout'

const app = document.getElementById('app')
const pages = import.meta.glob('./Pages/**/*.tsx')

const applyLayout = (name, page) => {
    if (name.startsWith('Project/')) {
        page.default.layout = page.default.layout || (page => <ProjectLayout children={page} />)
        return page
    }

    if (name.startsWith('System/')) {
        page.default.layout = page.default.layout || (page => <ProjectLayout children={page} />)
        return page
    }

    if (name === 'KnowledgeBase/KbPublicView') {
        page.default.layout = page.default.layout || (page => page)
        return page
    }

    if (name.startsWith('KnowledgeBase/') || name === 'AI/AIChat') {
        page.default.layout = page.default.layout || (page => <ProjectLayout children={page} />)
        return page
    }

    if (name.startsWith('Auth/') || name.startsWith('Install/')) {
        page.default.layout = page.default.layout || (page => <BaseLayout children={page} />)
        return page
    }

    page.default.layout = page.default.layout || (page => <MainLayout children={page} />)

    return page
}

createInertiaApp({
    resolve: async name => {
      const importPage = pages[`./Pages/${name}.tsx`]

      if (!importPage) {
        console.error(`找不到页面: ./Pages/${name}.tsx`)
        throw new Error(`找不到页面: ${name}`)
      }

      const page = await importPage()

      return applyLayout(name, page)
    },
    setup({ el, App, props }) {
      createRoot(el).render(<App {...props} />)
    },
})