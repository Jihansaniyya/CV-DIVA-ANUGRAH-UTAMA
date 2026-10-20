<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** Sama dengan StoreUnitRequest, tetapi kode satuan yang sedang diubah dikecualikan dari cek unik. */
class UpdateUnitRequest extends StoreUnitRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('units', 'code')->ignore($this->route('unit'))],
            'name' => ['required', 'string', 'max:80'],
        ];
    }
}
