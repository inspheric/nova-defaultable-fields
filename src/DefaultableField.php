<?php

namespace Inspheric\NovaDefaultable;

use BadMethodCallException;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Traits\Macroable;

use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\BelongsTo;

use Laravel\Nova\Http\Requests\NovaRequest;

class DefaultableField
{
    use Macroable;

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
     * Set a default field value on create
     *
     * @param  \Laravel\Nova\Fields\Field $field
     * @param  mixed|array|callable $value
     * @param callable|null $callback
     * @return \Laravel\Nova\Fields\Field
     */
    public static function default(Field $field, $value, callable $callback = null)
    {
        $request = app(NovaRequest::class);

        if ($request->editMode == 'create') {

            $key = static::resourceAttribute($request, $field);

            if (is_callable($value)) {
                $value = call_user_func($value, $request->resource());
            }

            if (is_callable($callback)) {
                $value = call_user_func($callback, $value, $request->resource());
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
                        throw new BadMethodCallException("Invalid default field behaviour handler for [$class]");
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
     * Return the cache key for the field on the resource
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest $request
     * @param  \Laravel\Nova\Fields\Field       $field
     * @return string
     */
    public static function cacheKey(NovaRequest $request, Field $field)
    {
        return config('defaultable_field.cache.key').'.'.auth()->id().'.'.md5(static::resourceAttribute($request, $field));
    }

    /**
     * Return the cache key for the field on the resource
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest $request
     * @param  \Laravel\Nova\Fields\Field       $field
     * @return string
     */
    public static function resourceAttribute(NovaRequest $request, Field $field)
    {
        return $request->resource().'@'.$field->attribute;
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
            list($type, $value) = $value;
            $type = $type::uriKey();
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
