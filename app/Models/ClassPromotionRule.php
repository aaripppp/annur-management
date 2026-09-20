<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassPromotionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_class_id',
        'target_class_id',
        'action',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sourceClass()
    {
        return $this->belongsTo(SchoolClass::class, 'source_class_id');
    }

    public function targetClass()
    {
        return $this->belongsTo(SchoolClass::class, 'target_class_id');
    }
}
