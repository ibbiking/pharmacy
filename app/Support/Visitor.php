<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** The anonymous browser id (cookie "vid") and a plain reading of the user agent, for the visit logs. */
class Visitor
{
    public const COOKIE = 'vid';

    /** The browser's id: from its cookie, or a new one (queued as a 2-year cookie). */
    public static function id(Request $request): string
    {
        $id = (string) $request->cookie(self::COOKIE, '');
        if (! Str::isUuid($id)) {
            $id = $request->attributes->get(self::COOKIE) ?: (string) Str::uuid();
            $request->attributes->set(self::COOKIE, $id);
            \Illuminate\Support\Facades\Cookie::queue(self::COOKIE, $id, 60 * 24 * 730, null, null, null, true, false, 'lax');
        }

        return $id;
    }

    /** @return array{browser: string, os: string, device: string} */
    public static function agent(?string $ua): array
    {
        $ua = (string) $ua;
        $bot = (bool) preg_match('/bot|crawl|spider|slurp|preview|headless|curl|wget|python|monitor/i', $ua);
        $browser = match (true) {
            $bot => 'Bot',
            (bool) preg_match('/Edg\//', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/', $ua) => 'Opera',
            (bool) preg_match('/SamsungBrowser/', $ua) => 'Samsung Internet',
            (bool) preg_match('/Firefox\//', $ua) => 'Firefox',
            (bool) preg_match('/Chrome\//', $ua) => 'Chrome',
            (bool) preg_match('/Safari\//', $ua) => 'Safari',
            default => 'Other',
        };
        $os = match (true) {
            (bool) preg_match('/Windows/', $ua) => 'Windows',
            (bool) preg_match('/Android/', $ua) => 'Android',
            (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
            (bool) preg_match('/Mac OS X|Macintosh/', $ua) => 'macOS',
            (bool) preg_match('/CrOS/', $ua) => 'ChromeOS',
            (bool) preg_match('/Linux/', $ua) => 'Linux',
            default => 'Other',
        };
        $device = $bot ? 'bot' : (preg_match('/iPad|Tablet/', $ua) ? 'tablet' : (preg_match('/Mobi|Android|iPhone/', $ua) ? 'mobile' : 'desktop'));

        return ['browser' => $browser, 'os' => $os, 'device' => $device];
    }
}
