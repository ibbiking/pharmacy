<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Top-bar bell (Roadmap 13c): the signed-in user's notifications for the CURRENT business only (business_id is stored
 * in each notification's data), 10 per page, mark one / all read.
 */
class NotificationController extends Controller
{
    public static function businessId(): int
    {
        return (int) (session('impersonate_business_id') ?? session('business_id'));
    }

    public static function queryFor(Request $request)
    {
        return $request->user()->notifications()->where('data->business_id', self::businessId());
    }

    public function index(Request $request)
    {
        $page = self::queryFor($request)->latest()->paginate(10);

        return response()->json([
            'data' => collect($page->items())->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'lines' => $n->data['lines'] ?? [],
                'url' => $n->data['url'] ?? null,
                'read' => $n->read_at !== null,
                'when' => $n->created_at?->diffForHumans(),
            ]),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'unread' => self::queryFor($request)->whereNull('read_at')->count(),
        ]);
    }

    public function read(Request $request, string $id)
    {
        self::queryFor($request)->whereKey($id)->update(['read_at' => now()]);

        return response()->json(['unread' => self::queryFor($request)->whereNull('read_at')->count()]);
    }

    public function readAll(Request $request)
    {
        self::queryFor($request)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['unread' => 0]);
    }
}
