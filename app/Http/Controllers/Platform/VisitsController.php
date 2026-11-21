<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Super admin: visitors and sign-ins per day (site_visits, login_events) and the live demo sessions. */
class VisitsController extends Controller
{
    public function index(Request $request)
    {
        $days = in_array($request->integer('days'), [7, 30, 90], true) ? $request->integer('days') : 30;
        $from = today()->subDays($days - 1);
        $people = DB::table('site_visits')->where('day', '>=', $from)->where('device', '!=', 'bot');

        $perDay = (clone $people)->selectRaw('day, count(distinct visitor_id) visitors, count(distinct case when is_demo = 1 then visitor_id end) demo, count(distinct case when user_id is not null and is_demo = 0 then user_id end) users, sum(hits) views')
            ->groupBy('day')->get()->keyBy(fn ($r) => Carbon::parse($r->day)->toDateString());
        $logins = DB::table('login_events')->where('created_at', '>=', $from)->selectRaw('date(created_at) d, count(*) n')->groupBy('d')->pluck('n', 'd');
        $series = [];
        for ($d = $from->copy(); $d->lte(today()); $d->addDay()) {
            $k = $d->toDateString();
            $series[] = ['day' => $d->format('d M'), 'visitors' => (int) ($perDay[$k]->visitors ?? 0), 'users' => (int) ($perDay[$k]->users ?? 0),
                'demo' => (int) ($perDay[$k]->demo ?? 0), 'logins' => (int) ($logins[$k] ?? 0), 'views' => (int) ($perDay[$k]->views ?? 0)];
        }
        $breakdown = fn (string $col) => (clone $people)->selectRaw("coalesce($col, 'Other') label, count(distinct visitor_id) n")->groupBy('label')->orderByDesc('n')->limit(6)->get();

        return Inertia::render('Platform/Visits', [
            'days' => $days,
            'series' => $series,
            'totals' => [
                'visitors' => (clone $people)->distinct()->count('visitor_id'),
                'today' => DB::table('site_visits')->where('day', today())->where('device', '!=', 'bot')->distinct()->count('visitor_id'),
                'logins' => DB::table('login_events')->where('created_at', '>=', $from)->count(),
                'demo' => (clone $people)->where('is_demo', true)->distinct()->count('visitor_id'),
                'views' => (int) (clone $people)->sum('hits'),
            ],
            'browsers' => $breakdown('browser'),
            'systems' => $breakdown('os'),
            'devices' => $breakdown('device'),
            'logins' => DB::table('login_events')->leftJoin('users', 'users.id', '=', 'login_events.user_id')->orderByDesc('login_events.id')->limit(40)
                ->get(['login_events.created_at', 'login_events.email', 'users.name', 'login_events.is_demo', 'login_events.browser', 'login_events.os', 'login_events.device', 'login_events.ip', 'login_events.visitor_id'])
                ->map(fn ($r) => ['at' => Carbon::parse($r->created_at)->format('d M, H:i'), 'name' => $r->name, 'email' => $r->email, 'demo' => (bool) $r->is_demo,
                    'browser' => $r->browser, 'os' => $r->os, 'device' => $r->device, 'ip' => $r->ip, 'visitor' => substr((string) $r->visitor_id, 0, 8)]),
            'visitors' => DB::table('site_visits')->leftJoin('users', 'users.id', '=', 'site_visits.user_id')->where('device', '!=', 'bot')->orderByDesc('last_seen_at')->limit(40)
                ->get(['site_visits.*', 'users.email'])
                ->map(fn ($r) => ['day' => Carbon::parse($r->day)->format('d M'), 'last' => Carbon::parse($r->last_seen_at)->format('H:i'), 'visitor' => substr($r->visitor_id, 0, 8),
                    'email' => $r->email, 'demo' => (bool) $r->is_demo, 'hits' => $r->hits, 'path' => $r->first_path, 'referrer' => $r->referrer,
                    'browser' => $r->browser, 'os' => $r->os, 'device' => $r->device, 'ip' => $r->ip]),
            'sandboxes' => fn () => \Illuminate\Support\Facades\Schema::hasTable('demo_sandboxes') ? \App\Services\Demo\DemoSandboxService::overview() : null,
        ]);
    }
}
