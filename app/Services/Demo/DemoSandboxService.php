<?php

namespace App\Services\Demo;

use App\Models\User;
use App\Support\Visitor;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Private demo copies (demo installations only, config demo.instance). Everyone signs in with the same public demo
 * login, but each browser is handed its own copy of the three demo businesses, so visitors never see each other's
 * changes and the seeded original is never touched. Copies are built ahead of time by `demo:pool`; a copy idle for
 * an hour (or older than the hard limit) is deleted with everything in it by `demo:cleanup`.
 */
class DemoSandboxService
{
    public const ROLES = ['owner', 'accountant', 'cashier'];

    public static function enabled(): bool
    {
        return (bool) config('demo.instance') && Schema::hasTable('demo_sandboxes');
    }

    /** Which demo role a public demo login stands for, or null. */
    public static function roleForEmail(string $email): ?string
    {
        $index = array_search(Str::lower(trim($email)), config('demo.emails', []), true);

        return $index === false ? null : (self::ROLES[$index] ?? null);
    }

    public static function publicPassword(): string
    {
        return (string) (config('demo.password') ?: DemoDataSeeder::PASSWORD);
    }

    /**
     * The person to sign in as for this browser: its own copy if it already has one, else a fresh ready copy, else
     * one built now (a few seconds; normally the pool has copies waiting).
     */
    public function userFor(Request $request, string $role): User
    {
        $visitor = Visitor::id($request);
        $sandbox = DB::table('demo_sandboxes')->where('status', 'in_use')->where('visitor_id', $visitor)->orderByDesc('id')->first();
        if (! $sandbox) {
            $sandbox = DB::transaction(function () use ($request, $visitor, $role) {
                $ready = DB::table('demo_sandboxes')->where('status', 'ready')->orderBy('id')->lockForUpdate()->first();
                if (! $ready) {
                    return null;
                }
                $who = ['visitor_id' => $visitor, 'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500)] + Visitor::agent($request->userAgent());
                $sessionId = DB::table('demo_sessions')->insertGetId($who + ['sandbox_id' => $ready->id, 'role' => $role, 'started_at' => now(), 'last_seen_at' => now()]);
                DB::table('demo_sandboxes')->where('id', $ready->id)->update($who + [
                    'status' => 'in_use', 'demo_session_id' => $sessionId, 'claimed_at' => now(), 'last_seen_at' => now(), 'updated_at' => now(),
                ]);

                return DB::table('demo_sandboxes')->find($ready->id);
            });
        }
        if (! $sandbox) {
            // Pool empty: build one in a separate process (seeding switches the signed-in user and session internally).
            $result = \Illuminate\Support\Facades\Process::path(base_path())->timeout(120)->run([PHP_BINARY, 'artisan', 'demo:pool', '--one']);
            if (! $result->successful()) {
                report(new RuntimeException('demo:pool --one failed: '.mb_substr($result->errorOutput(), 0, 500)));
                throw new RuntimeException('The demo is getting ready. Please try again in a minute.');
            }

            return $this->userFor($request, $role);
        }

        $user = User::query()->find($sandbox->{$role.'_id'});
        if (! $user) {
            throw new RuntimeException('The demo is getting ready. Please try again in a minute.');
        }

        return $user;
    }

    /** Builds one ready copy (console only). */
    public function build(): int
    {
        $id = DB::table('demo_sandboxes')->insertGetId(['status' => 'building', 'created_at' => now(), 'updated_at' => now()]);
        $people = [];
        foreach (['owner' => 'Demo Owner', 'accountant' => 'Demo Accountant', 'cashier' => 'Demo Cashier'] as $role => $name) {
            $user = new User(['name' => $name, 'email' => "sandbox{$id}.{$role}@demo.invalid"]);
            $user->forceFill(['password' => Hash::make(Str::random(40)), 'email_verified_at' => now(), 'theme' => 'dark', 'demo_sandbox_id' => $id])->save();
            $people[$role] = $user;
        }
        DB::table('demo_sandboxes')->where('id', $id)->update(['owner_id' => $people['owner']->id, 'accountant_id' => $people['accountant']->id, 'cashier_id' => $people['cashier']->id]);

        $seeder = app(DemoDataSeeder::class);
        $seeder->buildFor($people['owner'], $people['accountant'], $people['cashier']);
        DB::table('demo_sandboxes')->where('id', $id)->update(['status' => 'ready', 'updated_at' => now()]);

        return $id;
    }

    /** Keeps $size copies ready. @return int copies built */
    public function fill(int $size): int
    {
        $built = 0;
        while (DB::table('demo_sandboxes')->where('status', 'ready')->count() < $size) {
            $this->build();
            $built++;
        }

        return $built;
    }

    /** Mark this browser's copy as in use now (at most once a minute) and count the page view in its session log. */
    public static function touch(User $user, bool $pageView = true): void
    {
        if (! $user->demo_sandbox_id) {
            return;
        }
        $sessionId = self::sessionIdFor($user);
        if ($pageView && $sessionId) {
            DB::table('demo_sessions')->where('id', $sessionId)->update(['page_views' => DB::raw('page_views + 1'), 'last_seen_at' => now()]);
        }
        if (cache()->add('demo-touch:'.$user->demo_sandbox_id, 1, 60)) {
            DB::table('demo_sandboxes')->where('id', $user->demo_sandbox_id)->update(['last_seen_at' => now()]);
        }
    }

    /** Records one action (save / change / delete, or refused by the demo guard) in the visitor's session log. */
    public static function logAction(User $user, \Illuminate\Http\Request $request, ?int $status, bool $refused): void
    {
        $sessionId = self::sessionIdFor($user);
        if (! $sessionId) {
            return;
        }
        DB::table('demo_activities')->insert(['demo_session_id' => $sessionId, 'method' => $request->method(), 'route' => mb_substr((string) $request->route()?->getName(), 0, 120) ?: null,
            'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255), 'status' => $status, 'refused' => $refused, 'created_at' => now()]);
        DB::table('demo_sessions')->where('id', $sessionId)->update(['actions' => DB::raw('actions + 1'), 'last_seen_at' => now()]);
    }

    private static function sessionIdFor(User $user): ?int
    {
        return $user->demo_sandbox_id ? cache()->remember('demo-session:'.$user->demo_sandbox_id, 300,
            fn () => DB::table('demo_sandboxes')->where('id', $user->demo_sandbox_id)->value('demo_session_id')) : null;
    }

    /** Deletes copies idle for idle_minutes (or used longer than max_minutes), and builds that never finished. */
    public function cleanup(): int
    {
        $idle = now()->subMinutes((int) config('demo.idle_minutes', 60));
        $max = (int) config('demo.max_minutes', 0) > 0 ? now()->subMinutes((int) config('demo.max_minutes')) : null;
        $ids = DB::table('demo_sandboxes')
            ->where(fn ($q) => $q->where('status', 'in_use')->where(fn ($w) => $w->where('last_seen_at', '<', $idle)->when($max, fn ($m) => $m->orWhere('claimed_at', '<', $max))))
            ->orWhere(fn ($q) => $q->where('status', 'building')->where('created_at', '<', now()->subMinutes(30)))
            ->pluck('id');
        foreach ($ids as $id) {
            $this->destroy((int) $id);
        }

        return $ids->count();
    }

    /** Deletes one copy and everything in it: its businesses (every table with a business_id), people and sessions. */
    public function destroy(int $id): void
    {
        if (Schema::hasTable('demo_sessions')) {
            DB::table('demo_sessions')->where('sandbox_id', $id)->whereNull('ended_at')->update(['ended_at' => now()]);
        }
        $this->deletePeople(DB::table('users')->where('demo_sandbox_id', $id)->pluck('id')->all());
        DB::table('demo_sandboxes')->where('id', $id)->delete();
    }

    /**
     * Nightly refresh: every private copy and the seeded demo itself are deleted, then the demo is seeded again with
     * today's dates. Everything else (super admins, other accounts, visit and sign-in logs) stays.
     */
    public function purgeAllDemoData(): void
    {
        foreach (DB::table('demo_sandboxes')->pluck('id') as $id) {
            $this->destroy((int) $id);
        }
        $this->deletePeople(DB::table('users')->whereIn('email', config('demo.emails', []))->pluck('id')->all());
    }

    /** Deletes these people, the businesses they own (with every row of them) and their sessions. */
    private function deletePeople(array $userIds): void
    {
        if (! config('demo.instance')) {
            throw new RuntimeException('Demo data is only deleted on a demo installation (DEMO_INSTANCE=true).');
        }
        $businessIds = $userIds ? DB::table('business_user')->whereIn('user_id', $userIds)->where('role', 'owner')->pluck('business_id')->unique()->values()->all() : [];
        // Only businesses no other (non-copy) person belongs to.
        if ($businessIds) {
            $foreign = DB::table('business_user')->whereIn('business_id', $businessIds)->whereNotIn('user_id', $userIds)->pluck('business_id')->all();
            $businessIds = array_values(array_diff($businessIds, $foreign));
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            if ($businessIds) {
                foreach (self::tablesWithBusinessId() as $table) {
                    DB::table($table)->whereIn('business_id', $businessIds)->delete();
                }
                DB::table('businesses')->whereIn('id', $businessIds)->delete();
            }
            if ($userIds) {
                DB::table('business_user')->whereIn('user_id', $userIds)->delete();
                DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $userIds)->delete();
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->whereIn('user_id', $userIds)->delete();
                }
                DB::table('users')->whereIn('id', $userIds)->delete();
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /** @return string[] */
    private static function tablesWithBusinessId(): array
    {
        $db = DB::getDatabaseName();

        return collect(DB::select('select table_name as t from information_schema.columns where table_schema = ? and column_name = ?', [$db, 'business_id']))
            ->pluck('t')->reject(fn ($t) => in_array($t, ['businesses', 'business_user', 'site_visits', 'login_events'], true))->values()->all();
    }

    /** For the super admin's Visits page. */
    public static function overview(): array
    {
        $idle = (int) config('demo.idle_minutes', 120);
        $history = Schema::hasTable('demo_sessions') ? DB::table('demo_sessions')->orderByDesc('id')->limit(50)->get() : collect();
        $acts = $history->isEmpty() ? collect() : DB::table('demo_activities')->whereIn('demo_session_id', $history->pluck('id'))->orderByDesc('id')->get()->groupBy('demo_session_id');

        return [
            'ready' => DB::table('demo_sandboxes')->where('status', 'ready')->count(),
            'today' => DB::table('demo_sandboxes')->whereDate('claimed_at', today())->count(),
            'total' => Schema::hasTable('demo_sessions') ? DB::table('demo_sessions')->count() : 0,
            'history' => $history->map(fn ($s) => [
                'id' => $s->id, 'visitor' => $s->visitor_id ? substr($s->visitor_id, 0, 8) : null, 'role' => $s->role,
                'browser' => $s->browser, 'os' => $s->os, 'device' => $s->device, 'ip' => $s->ip,
                'started' => $s->started_at ? Carbon::parse($s->started_at)->format('d M, H:i') : null,
                'last_seen' => $s->last_seen_at ? Carbon::parse($s->last_seen_at)->format('d M, H:i') : null,
                'ended' => (bool) $s->ended_at, 'page_views' => (int) $s->page_views, 'actions' => (int) $s->actions,
                'activity' => ($acts[$s->id] ?? collect())->take(25)->map(fn ($a) => ['at' => Carbon::parse($a->created_at)->format('H:i'), 'method' => $a->method,
                    'what' => $a->route ?: $a->path, 'refused' => (bool) $a->refused, 'status' => $a->status])->values()->all(),
            ])->all(),
            'active' => DB::table('demo_sandboxes')->where('status', 'in_use')->orderByDesc('last_seen_at')->limit(50)->get()->map(fn ($s) => [
                'id' => $s->id, 'status' => $s->status, 'visitor' => $s->visitor_id ? substr($s->visitor_id, 0, 8) : null,
                'browser' => $s->browser, 'os' => $s->os, 'device' => $s->device, 'ip' => $s->ip,
                'started' => $s->claimed_at ? Carbon::parse($s->claimed_at)->format('d M, H:i') : null,
                'last_seen' => $s->last_seen_at ? Carbon::parse($s->last_seen_at)->diffForHumans() : null,
                'minutes_left' => $s->last_seen_at ? max(0, $idle - (int) Carbon::parse($s->last_seen_at)->diffInMinutes(now())) : null,
            ])->all(),
        ];
    }
}
