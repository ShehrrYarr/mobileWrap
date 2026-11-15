<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Variant option type beyond colour, e.g. Capacity, Length, Wattage. */
class Attribute extends Model
{
    use HasSlug;

    protected $fillable = ['name', 'slug', 'sort_order'];

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order')->orderBy('id');
    }
}
