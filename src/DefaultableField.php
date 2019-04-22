<?php

namespace Inspheric\NovaDefaultable;

use BadMethodCallException;
use InvalidArgumentException;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Traits\Macroable;

use Laravel\Nova\Contracts\ListableField;

use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\BelongsTo;

use Laravel\Nova\Http\Requests\NovaRequest;

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
     * Enable defaulting behaviour on a custom field type
     *
     * @param  string|\Laravel\Nova\Fields\Field $field
     * @param  string|callable $macro
     * @return void
     */
    public static function extend($field, $macro)
    {
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
        if ($field instanceof ListableField) {
            $class = get_class($field);
            throw new InvalidArgumentException("Listable field type `$class` does not support defaultable values");
        }

        $request = app(NovaRequest::class);

        if ($request->editMode == 'create') {

            if (is_callable($value)) {
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
                        throw new BadMethodCallException("Invalid defaultable field behaviour handler for `$class`");
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

        if ($request->editMode == 'create') {
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
            Cache::put(DefaultableField::cacheKey($request, $field), [
                $request[$field->attribute],
                $request->{$field->attribute.'_type'},
            ], config('defaultable_field.cache.ttl'));
        }
        else {
            Cache::put(DefaultableField::cacheKey($request, $field), $request[$field->attribute], config('defaultable_field.cache.ttl'));
        }
        // dd($field, $request->all(), $field->attribute, $request[$field->attribute]);
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
     * @param  mixed|array $value
     * @return \Laravel\Nova\Fields\MorphTo
     */
    protected static function handleMorphTo(MorphTo $field, $value)
    {
        if (is_array($value)) {
            list($value, $type) = $value;

            if (class_exists($type) && is_a($type, Resource::class, true)) {
                $type = $type::uriKey();
            }
        }
        else {
            $type = null;
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
        return $field->withMeta([
            'belongsToId' => $value,
        ]);
    }
}
