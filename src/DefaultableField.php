<?php

namespace Inspheric\NovaDefaultable;

use BadMethodCallException;
use InvalidArgumentException;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Traits\Macroable;

use Laravel\Nova\Contracts\ListableField;

use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\BelongsTo;

use Laravel\Nova\Http\Requests\NovaRequest;

use Laravel\Nova\Nova;
use Laravel\Nova\Resource;

class DefaultableField
{
    use Macroable;

    /**
     * Methods to handle various field types
     * @var array
     */
    protected static $fieldMacros = [
        MorphTo::class => 'handleMorphTo',
        BelongsTo::class => 'handleBelongsTo',
    ];

    /**
     * Classes or interfaces which are not supported
     * @var array
     */
    protected static $unsupported = [
        ListableField::class,
        File::class,
    ];

    /**
     * Enable defaulting behaviour on a custom field type
     *
     * @param  string|\Laravel\Nova\Fields\Field|string[]|\Laravel\Nova\Fields\Field[] $field
     * @param  string|callable $macro
     * @return void
     */
    public static function extend($field, $macro)
    {
        if (is_array($field)) {
            foreach ($field as $fld) {
                $fld = is_object($fld) ? get_class($fld) : $fld;

                static::$fieldMacros[$fld] = $macro;
            }

            return;
        }

        $field = is_object($field) ? get_class($field) : $field;

        static::$fieldMacros[$field] = $macro;
    }

    /**
     * Set a default field value on a create request
     *
     * @param  \Laravel\Nova\Fields\Field $field
     * @param  mixed|array|callable $value
     * @param callable|null $callback
     * @return \Laravel\Nova\Fields\Field
     */
    public static function default(Field $field, $value, callable $callback = null)
    {
        foreach (static::$unsupported as $unsupported) {
            if ($field instanceof $unsupported) {
                $class = get_class($field);
                throw new InvalidArgumentException("Field type `$class` does not support defaultable values");
            }
        }

        $request = app(NovaRequest::class);

        if ($request->isCreateOrAttachRequest()) {

            if (!is_array($value) && !is_string($value) && is_callable($value)) {
                $value = call_user_func($value, $request);
            }

            if (is_callable($callback)) {
                $value = call_user_func($callback, $value, $request);
            }

            foreach (static::$fieldMacros as $class => $macro) {
                if ($field instanceof $class) {
                    if (is_callable($macro)) {
                        return call_user_func($macro, $field, $value);
                    }
                    elseif (is_string($macro) && method_exists(static::class, $macro)) {
                        return call_user_func([static::class, $macro], $field, $value);
                    }
                    elseif (is_string($macro) && static::hasMacro($macro)) {
                        return static::__callStatic($macro, [$field, $value]);
                    }
                    else {
                        throw new BadMethodCallException("Invalid defaultable field handler for `$class`");
                    }
                }
            }

            return $field->withMeta([
                'value' => $value,
            ]);
        }

        return $field;
    }

    /**
     * Set the default to the last used value
     *
     * @param  \Laravel\Nova\Fields\Field  $field
     * @param callable|null $callback
     * @return \Laravel\Nova\Fields\Field
     */
    public static function defaultLast(Field $field, callable $callback = null)
    {
        $request = app(NovaRequest::class);

        if ($request->isCreateOrAttachRequest()) {
            $last = Cache::get(static::cacheKey($request, $field));

            $field->default($last, $callback);
        }

        return $field->withMeta(['defaultLast' => 'true']);
    }

    /**
     * Store the last value for a field on a resource
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest $request
     * @param  \Laravel\Nova\Fields\Field       $field
     * @return string
     */
    public static function cacheLastValue(NovaRequest $request, Field $field)
    {
        if ($field instanceof MorphTo) {
            $cacheable = [
                $request->{$field->attribute},
                $request->{$field->attribute.'_type'},
            ];
        }
        else {
            $cacheable = $request->{$field->attribute};
        }

        Cache::put(DefaultableField::cacheKey($request, $field), $cacheable, config('defaultable_field.cache.ttl'));
    }

    /**
     * Return the cache key for the field on the resource
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest $request
     * @param  \Laravel\Nova\Fields\Field       $field
     * @return string
     */
    public static function cacheKey(NovaRequest $request, Field $field)
    {
        return config('defaultable_field.cache.key').'.'.auth()->id().'.'.md5($request->resource().'::'.get_class($field).'::'.$field->attribute);
    }

    /**
     * Default behaviour for MorphTo fields
     *
     * @param  \Laravel\Nova\Fields\MorphTo  $field
     * @param  mixed|array|\Illuminate\Database\Eloquent\Model|\Laravel\Nova\Resource $value
     * @return \Laravel\Nova\Fields\MorphTo
     */
    protected static function handleMorphTo(MorphTo $field, $value)
    {
        if (is_array($value)) {
            list($value, $type) = $value;

            if ($value instanceof Model) {
                $value = $value->getKey();
            }

            if (is_a($type, Resource::class, true)) {
                $type = $type::uriKey();
            }
            elseif (is_a($type, Model::class, true)) {
                $resource = Nova::resourceForModel($type);
                $type = $resource::uriKey();
            }
        }
        elseif (is_a($value, Resource::class)) {
            $type = $value::uriKey();
            $value = $value->model()->getKey();
        }
        elseif (is_a($value, Model::class)) {
            $model = Nova::newResourceFromModel($value);
            $type = $model::uriKey();
            $value = $value->getKey();
        }
        else {
            $type = null;
            $value = null;
        }

        return $field->withMeta([
            'morphToType' => $type,
            'morphToId' => $value,
        ]);
    }

    /**
     * Default behaviour for BelongsTo fields
     *
     * @param  \Laravel\Nova\Fields\BelongsTo  $field
     * @param  mixed|array $value
     * @return \Laravel\Nova\Fields\BelongsTo
     */
    protected static function handleBelongsTo(BelongsTo $field, $value)
    {
        if (is_a($value, Resource::class)) {
            $value = $value->model()->getKey();
        }
        elseif (is_a($value, Model::class)) {
            $value = $value->getKey();
        }

        return $field->withMeta([
            'belongsToId' => $value,
        ]);
    }
}
