<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadAsset extends Model
{
    protected $guarded = [];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'size' => 'integer'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeAvailableTo($q, User $u)
    {
        return $q->where('active', true)->where(function ($q) use ($u) {
            $q->whereNull('product_id')->orWhereIn('product_id', Service::where('user_id', $u->id)->where('status', 'active')->select('product_id'));
        });
    }
}
