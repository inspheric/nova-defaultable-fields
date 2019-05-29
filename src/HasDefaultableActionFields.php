<?php

namespace Inspheric\NovaDefaultable;

use Illuminate\Support\Str;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\ActionRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

trait HasDefaultableActionFields
{
    /**
     * Handle chunk results.
     *
     * @param \Laravel\Nova\Fields\ActionFields $fields
     * @param array                             $results
     *
     * @return mixed
     */
    public function handleResult(ActionFields $fields, $results)
    {
        collect($this->fields())->filter(function ($field) {
            return $field->meta['defaultLast'] ?? false;
        })->each(function ($field) use ($fields) {
            $field->withMeta(['value' => $fields->{$field->attribute}]);
            $request = app(NovaRequest::class);

            DefaultableField::cacheLastValue($request, $field, $this->uriKey());
        });

        $results = parent::handleResult($fields, $results);

        return $this->refreshIndex($results);
    }

    /**
     * Provide the ability to refresh the index page when an action is run,
     * so that the action fields' default values can be repopulated.
     *
     * @param array|null $action
     *
     * @return array|null
     */
    protected function refreshIndex($action = null)
    {
        if ($this->refreshIndex ?? false) {
            $request = app(ActionRequest::class);

            $referrer = $request->header('Referer');
            $uriKey = Nova::newResourceFromModel($request->targetModel())->uriKey();

            if (Str::endsWith(stristr($referrer.'?', '?', true), '/'.$uriKey)) {
                $action = Action::redirect($referrer);
            }
        }

        return $action;
    }
}
