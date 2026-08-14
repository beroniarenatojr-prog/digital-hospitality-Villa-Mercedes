<?php

namespace App\Services\Customer;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Database\Eloquent\Collection;

class CustomerFaqService
{
    /**
     * Retrieve all active FAQ categories sorted by sort_order.
     *
     * @return Collection<int, FaqCategory>
     */
    public function getActiveCategories(): Collection
    {
        return FaqCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Retrieve all active FAQs, optionally filtered by an active category.
     * Results are sorted by category sort_order, then by FAQ sort_order.
     *
     * @param FaqCategory|null $category
     * @return Collection<int, Faq>
     */
    public function getActiveFaqs(?FaqCategory $category = null): Collection
    {
        $query = Faq::with('category')
            ->where('is_active', true)
            ->whereHas('category', function ($q) {
                $q->where('is_active', true);
            });

        if ($category) {
            $query->where('faq_category_id', $category->id);
        }

        // We sort by the related category's sort_order, then the FAQ's sort_order
        return $query->get()
            ->sortBy([
                ['category.sort_order', 'asc'],
                ['sort_order', 'asc']
            ])
            ->values();
    }
}
