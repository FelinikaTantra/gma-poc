<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany()
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where($builder->getModel()->getTable() . '.company_id', auth()->user()->company_id);
            } else {
                // For local demo without full login UI, we mock the first user's company
                $companyId = \DB::table('users')->first()?->company_id;
                if ($companyId) {
                    $builder->where($builder->getModel()->getTable() . '.company_id', $companyId);
                }
            }
        });

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->company_id = auth()->user()->company_id;
            } else {
                $companyId = \DB::table('users')->first()?->company_id;
                if ($companyId) {
                    $model->company_id = $companyId;
                }
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }
}
