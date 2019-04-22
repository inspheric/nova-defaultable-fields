<?php

namespace Inspheric\NovaDefaultable;

use Illuminate\Support\Facades\Cache;

use Laravel\Nova\Http\Requests\NovaRequest;

trait HasDefaultableFields
{

    /**
     * Fill the given fields for the model.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Illuminate\Support\Collection  $fields
     * @return array
     */
    protected static function fillFields(NovaRequest $request, $model, $fields)
    {
        $filled = parent::fillFields($request, $model, $fields);

        $fields->filter(function($field) {
            return $field->meta['defaultLast'] ?? false;
        })->each(function($field) use ($request) {
            Cache::put(DefaultableField::cacheKey($request, $field), $request[$field->attribute], config('defaultable_field.cache.ttl'));
        });

        return $filled;
    }
}
