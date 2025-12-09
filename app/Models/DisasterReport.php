<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\PictureTypeEnum;

class DisasterReport extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'disaster_id',
        'reported_by',
        'title',
        'description',
        'lat',
        'long',
        'is_final_stage',
    ];

    protected $casts = [
        'is_final_stage' => 'boolean',
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
        return $this->hasMany(Picture::class, 'foreign_id')->where('type', PictureTypeEnum::DISASTER_REPORT);
    }
}
