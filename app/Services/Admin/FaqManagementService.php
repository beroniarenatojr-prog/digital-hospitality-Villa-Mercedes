<?php

namespace App\Services\Admin;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FaqManagementService
{
    public function createCategory(User $admin, array $data): FaqCategory
    {
        $category = FaqCategory::create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
        ]);

        activity()
            ->performedOn($category)
            ->causedBy($admin)
            ->event('created')
            ->log("FAQ Category '{$category->name}' created.");

        return $category;
    }

    public function updateCategory(User $admin, FaqCategory $category, array $data): FaqCategory
    {
        $category->update($data);

        activity()
            ->performedOn($category)
            ->causedBy($admin)
            ->event('updated')
            ->log("FAQ Category '{$category->name}' updated.");

        return $category;
    }

    public function toggleCategoryStatus(User $admin, FaqCategory $category, bool $isActive): void
    {
        $category->update(['is_active' => $isActive]);

        $statusStr = $isActive ? 'activated' : 'deactivated';
        activity()
            ->performedOn($category)
            ->causedBy($admin)
            ->event('status_changed')
            ->log("FAQ Category '{$category->name}' $statusStr.");
    }

    public function createFaq(User $admin, array $data): Faq
    {
        $faq = Faq::create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
        ]);

        activity()
            ->performedOn($faq)
            ->causedBy($admin)
            ->event('created')
            ->log("FAQ created under category ID {$faq->faq_category_id}.");

        return $faq;
    }

    public function updateFaq(User $admin, Faq $faq, array $data): Faq
    {
        $faq->update($data);

        activity()
            ->performedOn($faq)
            ->causedBy($admin)
            ->event('updated')
            ->log("FAQ ID {$faq->id} updated.");

        return $faq;
    }

    public function toggleFaqStatus(User $admin, Faq $faq, bool $isActive): void
    {
        $faq->update(['is_active' => $isActive]);

        $statusStr = $isActive ? 'activated' : 'deactivated';
        activity()
            ->performedOn($faq)
            ->causedBy($admin)
            ->event('status_changed')
            ->log("FAQ ID {$faq->id} $statusStr.");
    }
}
