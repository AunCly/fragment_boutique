<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $faqCount = Faq::count();

        return view('admin.dashboard', compact('faqCount'));
    }
}
