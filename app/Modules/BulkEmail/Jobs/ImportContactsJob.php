<?php

namespace App\Modules\BulkEmail\Jobs;

use App\Modules\BulkEmail\Models\Contact;
use App\Modules\BulkEmail\Models\ContactList;
use App\Modules\BulkEmail\Models\DuplicateContact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class ImportContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $contactList;

    public function __construct(ContactList $contactList)
    {
        $this->contactList = $contactList;
    }

    public function handle()
    {
        $this->contactList->update(['status' => 'processing']);

        try {
            $data = Excel::toArray(new \stdClass(), storage_path('app/' . $this->contactList->file_path));
            $rows = $data[0];
            $headers = array_map('strtolower', array_shift($rows));
            
            $this->contactList->update(['total_rows' => count($rows)]);
            
            $processed = 0;
            $failed = 0;
            $duplicates = 0;

            foreach ($rows as $row) {
                try {
                    $rowData = array_combine($headers, $row);
                    $email = strtolower(trim($rowData['email'] ?? ''));

                    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $failed++;
                        continue;
                    }

                    // Check for unique email within same list
                    $exists = Contact::where('contact_list_id', $this->contactList->id)
                        ->where('email', $email)
                        ->exists();

                    if ($exists) {
                        DuplicateContact::create([
                            'contact_list_id' => $this->contactList->id,
                            'email' => $email,
                            'row_data' => $rowData
                        ]);
                        $duplicates++;
                        continue;
                    }

                    Contact::create([
                        'contact_list_id' => $this->contactList->id,
                        'email' => $email,
                        'data' => $rowData,
                        'status' => 'enabled'
                    ]);

                    $processed++;
                    
                    // Update progress every 10 rows
                    if (($processed + $duplicates + $failed) % 10 === 0) {
                        $this->contactList->update(['processed_rows' => $processed]);
                    }

                } catch (\Exception $e) {
                    $failed++;
                }
            }

            $this->contactList->update([
                'status' => 'completed',
                'processed_rows' => $processed,
                'failed_rows' => $failed,
                'duplicate_rows' => $duplicates
            ]);

        } catch (\Exception $e) {
            $this->contactList->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }
    }
}
