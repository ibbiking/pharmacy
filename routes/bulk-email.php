<?php

use Illuminate\Support\Facades\Route;
use App\Modules\BulkEmail\Controllers\ContactListController;
use App\Modules\BulkEmail\Controllers\ContactController;
use App\Modules\BulkEmail\Controllers\TemplateController;
use App\Modules\BulkEmail\Controllers\SignatureController;
use App\Modules\BulkEmail\Controllers\CampaignController;
use App\Modules\BulkEmail\Controllers\SmtpController;
use App\Modules\BulkEmail\Controllers\DashboardController;

use App\Modules\BulkEmail\Controllers\TrackingController;

Route::get('bec/track/open/{tracking_id}', [TrackingController::class, 'open'])->name('bec.track.open');
Route::get('bec/track/click/{tracking_id}', [TrackingController::class, 'click'])->name('bec.track.click');

Route::middleware(['web', 'auth'])->prefix('admin/bulk-email')->group(function () {
    
    Route::get('/', [DashboardController::class, 'index'])->name('bec.dashboard');

    // Contact Lists
    Route::resource('contact-lists', ContactListController::class)->names('bec.contact-lists');
    Route::get('contact-lists/{contact_list}/progress', [ContactListController::class, 'progress'])->name('bec.contact-lists.progress');
    Route::get('contact-lists/{contact_list}/columns', [ContactListController::class, 'fetchColumns'])->name('bec.contact-lists.columns');
    Route::get('contact-lists/{contact_list}/duplicates', [ContactListController::class, 'duplicates'])->name('bec.contact-lists.duplicates');
    
    // Contacts
    Route::get('contact-lists/{contact_list}/contacts', [ContactController::class, 'index'])->name('bec.contacts.index');
    Route::post('contacts/toggle-status', [ContactController::class, 'toggleStatus'])->name('bec.contacts.toggle-status');
    Route::post('contacts/bulk-action', [ContactController::class, 'bulkAction'])->name('bec.contacts.bulk-action');

    // Templates
    Route::resource('templates', TemplateController::class)->names('bec.templates');

    // Signatures
    Route::resource('signatures', SignatureController::class)->names('bec.signatures');

    // Campaigns
    Route::resource('campaigns', CampaignController::class)->names('bec.campaigns');
    Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send'])->name('bec.campaigns.send');

    // SMTP Settings
    Route::get('smtp', [SmtpController::class, 'index'])->name('bec.smtp.index');
    Route::post('smtp', [SmtpController::class, 'update'])->name('bec.smtp.update');
    Route::post('smtp/test', [SmtpController::class, 'test'])->name('bec.smtp.test');

    // Activity Logs
    Route::get('activity-logs', [DashboardController::class, 'activityLogs'])->name('bec.activity-logs');
});
