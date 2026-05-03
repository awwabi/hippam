<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (app()->has('current.tenant')) {
                $builder->where(
                    $builder->getModel()->getTable() . '.tenant_id',
                    app('current.tenant')->id
                );
            }
        });

        static::creating(function (Model $model) {
            if (app()->has('current.tenant') && empty($model->tenant_id)) {
                $model->tenant_id = app('current.tenant')->id;
            }
        });
    }
}
