<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Modules\ModuleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/** Help Center & "How it works" pages (Roadmap 4h). Content: config/help.php. */
class HelpController extends Controller
{
    public function __construct(private readonly ModuleManager $modules)
    {
    }

    public function index()
    {
        $topics = collect($this->visibleTopics())->map(fn ($t, $key) => [
            'key' => $key, 'title' => $t['title'], 'icon' => $t['icon'], 'accent' => $t['accent'], 'summary' => $t['summary'],
            'steps' => count($t['steps']),
        ])->values();

        return Inertia::render('Help/Index', ['topics' => $topics]);
    }

    public function show(string $topic)
    {
        $topics = $this->visibleTopics();
        abort_unless(isset($topics[$topic]), 404);
        $t = $topics[$topic];

        return Inertia::render('Help/Show', [
            'topic' => [
                'key' => $topic,
                'title' => $t['title'],
                'icon' => $t['icon'],
                'accent' => $t['accent'],
                'summary' => $t['summary'],
                'steps' => array_map(fn ($s) => [
                    'title' => $s[0],
                    'text' => $s[1],
                    'icon' => $s[2] ?? 'circle',
                    'href' => isset($s[3]) ? $this->linkFor($s[3]) : null,
                ], $t['steps']),
                'examples' => array_map(fn ($e) => ['title' => $e[0], 'text' => $e[1]], $t['examples'] ?? []),
                'faqs' => array_map(fn ($f) => ['q' => $f[0], 'a' => $f[1]], $t['faqs'] ?? []),
            ],
            'others' => collect($topics)->except($topic)->map(fn ($o, $key) => ['key' => $key, 'title' => $o['title'], 'icon' => $o['icon']])->values(),
        ]);
    }

    /** Topics of modules the current business has switched on. */
    private function visibleTopics(): array
    {
        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));

        return array_filter(config('help', []), fn ($t) => ! $businessId || $this->modules->isActive($businessId, $t['module'] ?? 'core'));
    }

    /** A screen link, unless the screen belongs to a switched-off module. */
    private function linkFor(string $routeName): ?string
    {
        if (! Route::has($routeName)) {
            return null;
        }
        $module = $this->modules->moduleForRoute($routeName);
        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));
        if ($module && $businessId && ! $this->modules->isActive($businessId, $module)) {
            return null;
        }

        return route($routeName);
    }
}
