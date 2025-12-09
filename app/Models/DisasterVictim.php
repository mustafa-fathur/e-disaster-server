<?php

namespace App\Models;

use App\Enums\DisasterVictimStatusEnum;
use App\Enums\PictureTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisasterVictim extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'disaster_id',
        'reported_by',
        'nik',
        'name',
        'date_of_birth',
        'gender',
        'contact_info',
        'description',
        'is_evacuated',
        'status',
    ];

    protected $casts = [
        'status' => DisasterVictimStatusEnum::class,
        'date_of_birth' => 'date',
        'gender' => 'boolean',
        'is_evacuated' => 'boolean',
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
            ->where('type', PictureTypeEnum::DISASTER_VICTIM->value);
    }
}
