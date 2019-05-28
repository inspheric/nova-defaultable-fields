<?php

namespace Inspheric\NovaDefaultable;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\ActionFields;

trait HasDefaultableActionFields
{
    /**
     * Handle chunk results.
     *
     * @param  \Laravel\Nova\Fields\ActionFields $fields
     * @param  array $results
     *
     * @return mixed
     */
    public function handleResult(ActionFields $fields, $results)
    {
        collect($this->fields())->filter(function($field) {
            return $field->meta['defaultLast'] ?? false;
        })->each(function($field) use ($fields) {            
            
            $field->withMeta(['value' => $fields->{$field->attribute}]);
            $request = app(NovaRequest::class);
            
            DefaultableField::cacheLastValue($request, $field, $this->uriKey());
            
        });

        return parent::handleResult($fields, $results);
    }
}
