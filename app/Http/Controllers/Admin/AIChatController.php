<?php

namespace App\Http\Controllers\Admin;

class AIChatController extends BaseAdminController
{
    public function index()
    {
        return viewShow('AI/AIChat');
    }
}
