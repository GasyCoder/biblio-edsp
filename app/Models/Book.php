<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['category_id', 'title', 'subtitle', 'cover_path', 'publication_year', 'publisher', 'summary', 'isbn', 'keywords', 'language', 'edition'])]
class Book extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = ['cover_url'];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'publication_year' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class)->withPivot('position')->withTimestamps()->orderByPivot('position');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(Copy::class);
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
