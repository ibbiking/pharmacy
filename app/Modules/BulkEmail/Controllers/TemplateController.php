<?php

namespace App\Modules\BulkEmail\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BulkEmail\Models\EmailTemplate;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index()
    {
        $templates = EmailTemplate::latest()->paginate(10);
        return view('bulk-email::templates.index', compact('templates'));
    }

    public function create()
    {
        $lists = \App\Modules\BulkEmail\Models\ContactList::all();
        return view('bulk-email::templates.create', compact('lists'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'nullable|string|max:255',
            'body' => 'required',
            'contact_list_id' => 'nullable|exists:bec_contact_lists,id',
            'files.*' => 'nullable|file|max:10240', // 10MB per file
        ]);

        $data = $request->all();
        
        if ($request->hasFile('files')) {
            $attachments = [];
            foreach ($request->file('files') as $file) {
                $path = $file->store('bulk-email/attachments');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime' => $file->getClientMimeType(),
                ];
            }
            $data['attachments'] = $attachments;
        }

        EmailTemplate::create($data);

        return redirect()->route('bec.templates.index')->with('success', 'Template created successfully.');
    }

    public function edit(EmailTemplate $template)
    {
        $lists = \App\Modules\BulkEmail\Models\ContactList::all();
        return view('bulk-email::templates.edit', compact('template', 'lists'));
    }

    public function update(Request $request, EmailTemplate $template)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'nullable|string|max:255',
            'body' => 'required',
            'contact_list_id' => 'nullable|exists:bec_contact_lists,id',
            'files.*' => 'nullable|file|max:10240',
        ]);

        $data = $request->all();

        if ($request->hasFile('files')) {
            // Optional: delete old files if you want
            $attachments = $template->attachments ?: [];
            foreach ($request->file('files') as $file) {
                $path = $file->store('bulk-email/attachments');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime' => $file->getClientMimeType(),
                ];
            }
            $data['attachments'] = $attachments;
        }

        $template->update($data);

        return redirect()->route('bec.templates.index')->with('success', 'Template updated successfully.');
    }

    public function destroy(EmailTemplate $template)
    {
        $template->delete();
        return redirect()->route('bec.templates.index')->with('success', 'Template deleted.');
    }
}
