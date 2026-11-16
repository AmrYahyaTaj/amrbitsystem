<?php

namespace App\Models;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            $context = app(CompanyContext::class);
            // Missing context must never expose records, including in console jobs.
            if ($context->company === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where($query->getModel()->qualifyColumn('company_id'), $context->id());
            }
        });
        static::creating(function (TenantModel $model) {
            $companyId = app(CompanyContext::class)->id();
            if ($model->company_id !== null && (int) $model->company_id !== $companyId) {
                throw new \LogicException('Cross-company writes are forbidden.');
            }
            $model->company_id = $companyId;
        });
        static::updating(function (TenantModel $model) {
            if ($model->isDirty('company_id') || (int) $model->company_id !== app(CompanyContext::class)->id()) {
                throw new \LogicException('The owning company cannot be changed.');
            }
        });
    }
}
