<?php

namespace App\Modules\BulkEmail\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BulkEmail\Models\Contact;
use App\Modules\BulkEmail\Models\ContactList;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(ContactList $contact_list, Request $request)
    {
        $query = Contact::where('contact_list_id', $contact_list->id);

        if ($request->search) {
            $query->where('email', 'like', '%' . $request->search . '%')
                  ->orWhere('data', 'like', '%' . $request->search . '%');
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $contacts = $query->latest()->paginate(25);
        $columns = $contact_list->columns;

        return view('bulk-email::contacts.index', compact('contact_list', 'contacts', 'columns'));
    }

    public function toggleStatus(Request $request)
    {
        $contact = Contact::findOrFail($request->id);
        $contact->status = ($contact->status == 'enabled') ? 'disabled' : 'enabled';
        $contact->save();

        return response()->json(['success' => true, 'status' => $contact->status]);
    }

    public function bulkAction(Request $request)
    {
        $ids = $request->ids;
        $action = $request->action;

        if ($action == 'enable') {
            Contact::whereIn('id', $ids)->update(['status' => 'enabled']);
        } elseif ($action == 'disable') {
            Contact::whereIn('id', $ids)->update(['status' => 'disabled']);
        }

        return response()->json(['success' => true]);
    }
}
