<?php

namespace App\Modules\BulkEmail\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BulkEmail\Models\CampaignLog;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function open($tracking_id)
    {
        $log = CampaignLog::where('tracking_id', $tracking_id)->first();
        if ($log) {
            $log->update([
                'status' => 'opened',
                'opened_at' => now(),
            ]);
        }

        // Return a 1x1 transparent GIF
        $img = base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICRAEAOw==');
        return response($img)->header('Content-Type', 'image/gif');
    }

    public function click(Request $request, $tracking_id)
    {
        $log = CampaignLog::where('tracking_id', $tracking_id)->first();
        if ($log) {
            $log->update([
                'status' => 'clicked',
                'clicked_at' => now(),
            ]);
        }

        return redirect($request->url);
    }
}
