<?php

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'image',
        'icon',
        'description',
        'status',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'category_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopePublicLeaves(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('parent_id')
            ->whereDoesntHave('children')
            ->whereHas('parent', fn (Builder $parentQuery) => $parentQuery
                ->whereNull('parent_id')
                ->where('status', 'active'));
    }
}
