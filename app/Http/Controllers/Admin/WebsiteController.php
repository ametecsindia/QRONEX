<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SiteController;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;

/** Super Admin → Website: headline, sub-headline, announcement banner, FAQ (also shown in the portal's Help page). */
class WebsiteController extends Controller
{
    public function edit()
    {
        return view('admin.website', [
            'cms' => SiteController::cms(),
            'faqText' => collect(SiteController::faqs())->map(fn ($f) => $f[0]."\n".$f[1])->implode("\n\n"),
        ]);
    }

    public function update(Request $request)
    {
        $d = $request->validate([
            'hero_title' => 'required|string|max:120', 'hero_sub' => 'required|string|max:300',
            'announcement' => 'nullable|string|max:200', 'faqs' => 'required|string|max:20000',
        ]);
        // FAQ format: question on one line, answer on the next, blank line between entries.
        $faqs = collect(preg_split('/\R\s*\R/', trim($d['faqs'])))->map(fn ($block) => array_map('trim', preg_split('/\R/', trim($block), 2)))
            ->filter(fn ($f) => count($f) === 2 && $f[0] !== '' && $f[1] !== '')->values()->all();
        if (! $faqs) {
            return back()->withErrors(['faqs' => 'Write each FAQ as a question line, then an answer line, with a blank line between entries.'])->withInput();
        }
        PlatformSetting::put('cms', ['hero_title' => $d['hero_title'], 'hero_sub' => $d['hero_sub'], 'announcement' => $d['announcement']]);
        PlatformSetting::put('cms_faqs', $faqs);
        AuditLog::record('website.updated', null, ['faqs' => count($faqs)]);

        return back()->with('ok', 'Website updated ('.count($faqs).' FAQs).');
    }
}
