<?php

namespace App\Modules\BulkEmail\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactListUploadRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'file' => 'required|file|mimes:csv,xlsx,xls,txt|max:51200', // 50MB
        ];
    }
}
