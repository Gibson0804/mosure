// 项目相关路由常量（对齐后端 routes/web.php 配置）

export const INSTALL_ROUTES = {
    doInstall: '/api/install/doInstall',
    installConfig: '/api/install/installConfig',
    testDbConnection: '/api/install/testDbConnection',
}

export const AUTH_ROUTES = {
    login: '/login',
    doLogin: '/doLogin',
    logout: '/logout',
    forgotPassword: '/forgot-password',
    resetPassword: '/reset-password',
    changePassword: '/change-password',
}


export const PROJECT_ROUTES = {
  index: '/project', // 项目首页（列表）
  create: '/project/create', // 创建项目页
  edit: (id: number | string) => `/project/edit/${id}`, // 编辑项目页
  select: (id: number | string) => `/project/select/${id}`, // 选择项目
  delete: (id: number | string) => `/project/delete/${id}`, // 删除项目（DELETE）
  generatePrefix: '/project/generate-prefix', // 生成前缀API
};

export const MOLD_ROUTES = {
  list: '/mold/list',
  add: '/mold/add',
  edit: (moldId: string | number) => `/mold/edit/${moldId}`,
  update: (moldId: string | number) => `/mold/update/${moldId}`,
  deleteCheck: (moldId: string | number) => `/mold/delete_check/${moldId}`,
  delete: (moldId: string | number) => `/mold/delete/${moldId}`,
  suggest: '/mold/suggest',
  modelsAndFields: '/mold/builder/models_and_fields',
};

export const CONTENT_ROUTES = {
  list: (id: string | number) => `/content/list/${id}`,
  edit: (moldId: string | number, id: string | number) => `/content/edit/${moldId}/${id}`,
  add: (moldId: string | number) => `/content/add/${moldId}`,
  countByMold: (moldId: string | number) => `/content/count/${moldId}`,
  detail: (moldId: string | number, id: string | number) => `/content/detail/${moldId}/${id}`,
  delete: (moldId: string | number, id: string | number) => `/content/delete/${moldId}/${id}`,
  fieldOptions: (modelId: string | number) => `/content/field-options/${modelId}`,
};

export const SUBJECT_ROUTES = {
  edit: (id: string | number) => `/subject/edit/${id}`,
};

export const MEDIA_ROUTES = {
    index: '/media',
    create: '/media/create',
    edit: (id: string | number) => `/media/edit/${id}`,
    delete: (id: string | number) => `/media/delete/${id}`,
    upload: '/media/upload',
    detail: (id: string | number) => `/media/detail/${id}`,
    batchDelete:'/media/batch-delete',
    batchMove: '/media/batch-move',
}

export const MEDIA_FOLDER_ROUTES = {
  tree: '/media/folders/tree',
  create: '/media/folders',
  rename: (id: string | number) => `/media/folders/${id}/rename`,
  move: (id: string | number) => `/media/folders/${id}/move`,
  delete: (id: string | number) => `/media/folders/${id}/delete`,
};

export const MEDIA_TAG_ROUTES = {
  list: '/media/tags/list',
  create: '/media/tags',
  update: (id: string | number) => `/media/tags/${id}`,
  delete: (id: string | number) => `/media/tags/${id}/delete`,
};

export const EXPORT_ROUTES = {
  export: '/manage/export',
}

export const API_KEY_ROUTES = {
    index: '/api-key',
    create: '/api-key/create',
    edit: (id: string | number) => `/api-key/edit/${id}`,
    delete: (id: string | number) => `/api-key/delete/${id}`,
    generate: '/api-key/generate',
}

export const SYSTEM_CONFIG_ROUTES = {
  index: '/system-config',
  data: '/system-config/data',
  save: '/system-config/save',
  testMail: '/system-config/test-mail',
  testProvider: '/system-config/test-provider',
  testStorage: '/system-config/test-storage',
}

export const KB_ROUTES = {
    index: '/kb',
    detail: (id: number | string) => `/kb/detail/${id}`,
    editor: (id?: number | string) => id ? `/kb/editor/${id}` : '/kb/editor',
    categoryTree: '/kb/categories/tree',
    createCategory: '/kb/categories/create',
    updateCategory: (id: number | string) => `/kb/categories/update/${id}`,
    deleteCategory: (id: number | string) => `/kb/categories/delete/${id}`,
    articleList: '/kb/articles/list',
    articleDetail: (id: number | string) => `/kb/articles/detail/${id}`,
    createArticle: '/kb/articles/create',
    updateArticle: (id: number | string) => `/kb/articles/update/${id}`,
    deleteArticle: (id: number | string) => `/kb/articles/delete/${id}`,
    toggleArticle: (id: number | string) => `/kb/articles/toggle/${id}`,
    uploadImage: '/kb/upload-image',
    publicView: (slug: string) => `/kb/share/${slug}`,
};

export const HOOK_ROUTES = {
    availableHandlers: '/hook/available-handlers',
    bind: (moldId: string | number) => `/hook/bind/${moldId}`,
    delete: (moldId: string | number) => `/hook/delete/${moldId}`,
    list: '/hook/list',
    unbind: (moldId: string | number) => `/hook/unbind/${moldId}`,
    update: (moldId: string | number) => `/hook/update/${moldId}`,
    moldBindings: (moldId: string | number) => `/hook/mold/${moldId}`,

}
