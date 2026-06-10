<?php

namespace App\Modules\BulkEmail\Services;

use App\Modules\BulkEmail\Models\ContactList;
use App\Modules\BulkEmail\Models\ContactListColumn;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportService
{
    public function validateAndCreate($data, $file)
    {
        // 1. Read headers
        $headers = $this->getHeaders($file);
        
        // 2. Validate email column exists
        if (!in_array('email', $headers)) {
            throw new \Exception('The file must contain an "email" column.');
        }

        // 3. Validate no spaces in columns
        foreach ($headers as $header) {
            if (strpos($header, ' ') !== false) {
                throw new \Exception("Column names must not contain spaces. Invalid column: '$header'");
            }
        }

        // 4. Save file
        $path = $file->store('bulk-email/imports');

        // 5. Create Contact List record
        $contactList = ContactList::create([
            'name' => $data['name'],
            'file_path' => $path,
            'status' => 'pending',
        ]);

        // 6. Store column definitions
        foreach ($headers as $header) {
            ContactListColumn::create([
                'contact_list_id' => $contactList->id,
                'column_name' => $header,
                'ui_label' => Str::title(str_replace('_', ' ', $header)),
            ]);
        }

        return $contactList;
    }

    private function getHeaders($file)
    {
        $data = Excel::toArray(new \stdClass(), $file);
        if (empty($data) || empty($data[0])) {
            return [];
        }
        
        // Return first row as headers, trimmed and lowercase
        return array_map(function($h) {
            return strtolower(trim($h));
        }, $data[0][0]);
    }
}
