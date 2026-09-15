<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ResortPool extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'audience_note', 'image_path', 'is_package_only', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_package_only' => 'boolean', 'is_active' => 'boolean'];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
