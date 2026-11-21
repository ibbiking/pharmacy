<?php
// php smoke.php <app dir>: a few real requests through the app + data counts (no output of secrets)
chdir($argv[1]);
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
foreach (['/', '/guide', '/admin/login', '/admin/signup'] as $uri) {
    $req = Illuminate\Http\Request::create('https://'.basename($argv[1]).$uri, 'GET', [], [], [], ['HTTPS' => 'on']);
    $res = $kernel->handle($req);
    $body = $res->getContent();
    printf("%-14s %d %s%s\n", $uri, $res->getStatusCode(), str_contains($body, (string) config('app.brand')) ? 'brand-ok' : 'NO-BRAND', str_contains($body, 'phpdebugbar') ? ' DEBUGBAR!' : '');
    $kernel->terminate($req, $res);
}
$db = Illuminate\Support\Facades\DB::class;
printf("users=%d businesses=%d invoices=%d sandboxes_ready=%d super_admin=%s debug=%s env=%s\n",
    $db::table('users')->count(), $db::table('businesses')->count(), $db::table('invoices')->count(),
    Illuminate\Support\Facades\Schema::hasTable('demo_sandboxes') ? $db::table('demo_sandboxes')->where('status', 'ready')->count() : -1,
    App\Models\User::role('super-admin')->where('email', 'ibrahimazhar86@gmail.com')->exists() ? 'yes' : 'no',
    var_export(config('app.debug'), true), app()->environment());
