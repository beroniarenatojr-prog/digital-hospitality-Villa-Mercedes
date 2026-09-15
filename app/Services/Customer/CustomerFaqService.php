<?php

namespace App\Services\Customer;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class CustomerFaqService
{
    /**
     * Curated guest FAQs shown on the home page, in display order.
     *
     * @var list<string>
     */
    public const HOME_FAQ_QUESTIONS = [
        'Where is Villa Mercedes located?',
        'What is the entrance fee?',
        'What are the pool hours?',
        'What are the check-in and check-out times?',
        'Is the resort pet-friendly?',
    ];

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
     * @return Collection<int, Faq>
     */
    public function getActiveFaqs(?FaqCategory $category = null): Collection
    {
        return $this->activeFaqQuery($category)->get();
    }

    /**
     * Curated public teaser for the home page (Location, fees, hours, check-in, pets).
     *
     * @return Collection<int, Faq>
     */
    public function getHomeFaqs(int $limit = 5): Collection
    {
        $questions = array_slice(self::HOME_FAQ_QUESTIONS, 0, max(1, $limit));

        $faqs = $this->activeFaqQuery()
            ->whereIn('faqs.question', $questions)
            ->get();

        $ordered = SupportCollection::make($questions)
            ->map(fn (string $question) => $faqs->firstWhere('question', $question))
            ->filter()
            ->values();

        /** @var Collection<int, Faq> $result */
        $result = new Collection($ordered->all());

        return $result;
    }

    /**
     * @return Builder<Faq>
     */
    private function activeFaqQuery(?FaqCategory $category = null)
    {
        return Faq::query()
            ->with('category')
            ->join('faq_categories', 'faqs.faq_category_id', '=', 'faq_categories.id')
            ->select('faqs.*')
            ->where('faqs.is_active', true)
            ->where('faq_categories.is_active', true)
            ->when($category, fn ($query) => $query->where('faq_category_id', $category->id))
            ->orderBy('faq_categories.sort_order')
            ->orderBy('faqs.sort_order')
            ->orderBy('faqs.id');
    }
}
