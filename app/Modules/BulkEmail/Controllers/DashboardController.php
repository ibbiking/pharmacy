<?php

namespace App\Modules\BulkEmail\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BulkEmail\Models\Campaign;
use App\Modules\BulkEmail\Models\ContactList;
use App\Modules\BulkEmail\Models\ActivityLog;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_campaigns' => Campaign::count(),
            'total_lists' => ContactList::count(),
            'total_contacts' => \App\Modules\BulkEmail\Models\Contact::count(),
            'total_sent' => \App\Modules\BulkEmail\Models\CampaignLog::where('status', 'sent')->count(),
            'total_opened' => \App\Modules\BulkEmail\Models\CampaignLog::where('status', 'opened')->count(),
            'total_clicked' => \App\Modules\BulkEmail\Models\CampaignLog::where('status', 'clicked')->count(),
            'active_campaigns' => Campaign::where('status', 'processing')->count(),
            'completed_campaigns' => Campaign::where('status', 'completed')->count(),
        ];

        $recent_activity = ActivityLog::latest()->take(10)->get();

        return view('bulk-email::dashboard', compact('stats', 'recent_activity'));
    }

    public function activityLogs()
    {
        $logs = ActivityLog::latest()->paginate(20);
        return view('bulk-email::activity-logs', compact('logs'));
    }
}
