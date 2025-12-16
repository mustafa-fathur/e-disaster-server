<?php

namespace App\Models;

use App\Enums\DisasterAidCategoryEnum;
use App\Enums\PictureTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisasterAid extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'disaster_id',
        'reported_by',
        'donator',
        'location',
        'title',
        'description',
        'category',
        'quantity',
        'unit',
    ];

    protected $casts = [
        'category' => DisasterAidCategoryEnum::class,
    ];

    public function disaster()
    {
        return $this->belongsTo(Disaster::class);
    }

    public function reporter()
    {
        return $this->belongsTo(DisasterVolunteer::class, 'reported_by');
    }

    public function pictures()
    {
        return $this->hasMany(Picture::class, 'foreign_id')
            ->where('type', PictureTypeEnum::DISASTER_AID->value);
    }
}
