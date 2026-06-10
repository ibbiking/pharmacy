<?php

namespace App\Modules\BulkEmail\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BulkEmail\Models\ContactList;
use App\Modules\BulkEmail\Models\ActivityLog;
use App\Modules\BulkEmail\Requests\ContactListUploadRequest;
use App\Modules\BulkEmail\Services\ImportService;
use App\Modules\BulkEmail\Jobs\ImportContactsJob;
use Illuminate\Http\Request;

class ContactListController extends Controller
{
    protected $importService;

    public function __construct(ImportService $importService)
    {
        $this->importService = $importService;
    }

    public function index()
    {
        $lists = ContactList::latest()->paginate(10);
        return view('bulk-email::contact-lists.index', compact('lists'));
    }

    public function create()
    {
        return view('bulk-email::contact-lists.create');
    }

    public function store(ContactListUploadRequest $request)
    {
        try {
            $contactList = $this->importService->validateAndCreate($request->validated(), $request->file('file'));
            
            ActivityLog::log("Created contact list: {$contactList->name}", $contactList);

            // Dispatch Import Job
            ImportContactsJob::dispatch($contactList);

            return redirect()->route('bec.contact-lists.show', $contactList->id)
                ->with('success', 'File uploaded and import started.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(ContactList $contact_list)
    {
        return view('bulk-email::contact-lists.show', compact('contact_list'));
    }

    public function fetchColumns(ContactList $contact_list)
    {
        return response()->json($contact_list->columns);
    }

    public function progress(ContactList $contact_list)
    {
        return response()->json([
            'status' => $contact_list->status,
            'total' => $contact_list->total_rows,
            'processed' => $contact_list->processed_rows,
            'failed' => $contact_list->failed_rows,
            'percentage' => $contact_list->total_rows > 0 ? round(($contact_list->processed_rows / $contact_list->total_rows) * 100) : 0,
            'error' => $contact_list->error_message
        ]);
    }

    public function duplicates(ContactList $contact_list)
    {
        $duplicates = $contact_list->duplicates()->latest()->paginate(50);
        return view('bulk-email::contact-lists.duplicates', compact('contact_list', 'duplicates'));
    }

    public function destroy(ContactList $contact_list)
    {
        ActivityLog::log("Deleted contact list: {$contact_list->name}", $contact_list);
        $contact_list->delete();
        return redirect()->route('bec.contact-lists.index')->with('success', 'Contact list deleted.');
    }
}
