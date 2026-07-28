<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
            'search' => 'sometimes|nullable|string|max:255',
            'category_id' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|string|in:sale,hidden,out',
        ];
    }

    /**
     * استخراج الفلاتر المنظفة مع إزالة الـ null والنصوص الفارغة فقط
     * مع الحفاظ على القيم الصفرية و البولينية الحقيقية
     */
    public function validatedFilters(): array
    {
        $raw = [
            'page'        => max(1, (int) $this->query('page', 1)),
            'limit'       => (int) $this->query('limit', 15),
            'search'      => $this->query('search'),
            'category_id' => $this->query('category_id'),
            'status'      => $this->query('status'),
        ];

        return array_filter($raw, fn ($value) => $value !== null && $value !== '');
    }
}
