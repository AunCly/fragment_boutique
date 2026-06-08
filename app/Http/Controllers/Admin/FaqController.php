<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        $faqs = Faq::ordered()->get();

        return view('admin.faqs.index', compact('faqs'));
    }

    public function create(): View
    {
        return view('admin.faqs.form', ['faq' => new Faq]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'position' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        Faq::create([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.faqs.index')->with('success', 'Question ajoutée.');
    }

    public function edit(Faq $faq): View
    {
        return view('admin.faqs.form', compact('faq'));
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'position' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $faq->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.faqs.index')->with('success', 'Question mise à jour.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return redirect()->route('admin.faqs.index')->with('success', 'Question supprimée.');
    }
}
