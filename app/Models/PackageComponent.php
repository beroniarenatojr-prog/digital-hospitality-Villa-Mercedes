<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageComponent extends Model
{
    protected $fillable = [
        'package_facility_id',
        'component_facility_id',
    ];

    /**
     * The package facility this component belongs to.
     *
     * @return BelongsTo<Facility, $this>
     */
    public function packageFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'package_facility_id');
    }

    /**
     * The component facility included in the package.
     *
     * @return BelongsTo<Facility, $this>
     */
    public function componentFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'component_facility_id');
    }
}
