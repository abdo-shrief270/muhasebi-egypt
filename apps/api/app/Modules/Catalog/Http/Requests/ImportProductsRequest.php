<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class ImportProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('products.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // 5 MB is thousands of rows; xlsx is a zip, csv is plain text.
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt', 'extensions:xlsx,csv'],
            'skip_invalid' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'الملف'];
    }

    public function messages(): array
    {
        return ['file.mimes' => 'الملف لازم يبقى Excel (xlsx) أو CSV.', 'file.extensions' => 'الملف لازم يبقى Excel (xlsx) أو CSV.'];
    }

    public function sheet(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('file');
    }
}
