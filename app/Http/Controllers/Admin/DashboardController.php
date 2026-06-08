<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Format;
use App\Models\WoodTexture;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $faqCount = Faq::count();
        $woodTextureCount = WoodTexture::count();
        $formatCount = Format::count();

        return view('admin.dashboard', compact('faqCount', 'woodTextureCount', 'formatCount'));
    }
}
