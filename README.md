# Laravel Nova Defaultable Fields
Populate default values for Nova fields when creating resources.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/inspheric/nova-defaultable.svg?style=flat-square)](https://packagist.org/packages/inspheric/nova-defaultable)
[![Total Downloads](https://img.shields.io/packagist/dt/inspheric/nova-defaultable.svg?style=flat-square)](https://packagist.org/packages/inspheric/nova-defaultable)

## Installation

Install the package into a Laravel app that uses [Nova](https://nova.laravel.com) with Composer:

```bash
composer require inspheric/nova-defaultable
```

(Optional) If you want to use the `defaultLast()` method (see below), you need to add the trait `Inspheric\NovaDefaultable\HasDefaultableFields` to your base Resource class (located at `app\Nova\Resource.php`):

```php
use Inspheric\NovaDefaultable\HasDefaultableFields;

abstract class Resource extends NovaResource
{
    use HasDefaultableFields;

    // ...
}
```

## Basic Usage

When creating resources, there may be values which can be defaulted to save the user time, rather than needing to be entered into a blank form every time. This could include populating the `user_id` on a resource that the current user owns, repeating the same 'parent' record for several new records in a row, starting with a checkbox in a checked state, or populating an incrementing value e.g. an invoice number.

This package plugs into existing fields and provides two simple methods to supply a default value.

*Note:* The defaultable behaviour below is only applicable on the 'create' or 'attach' form. Fields will not be defaulted on 'update' or 'update-attached' requests; however, the last used value will be stored on any successful save request, and will be defaulted on a later 'create'/'attach' request.

### Default any value

Use the `default()` method on any standard Nova field to populate a simple value, e.g.:

```php
Text::make('Name')
    ->default('Default Name'),
```

or:
```php
Boolean::make('Active')
    ->default(true),
```
The `default()` method can also take a callback as the first parameter, which will return the value to be populated:

```php
Text::make('Name')
    ->default(function(NovaRequest $request) {
        return $request->user()->name.'\'s New Resource';
    }),
```

### Special cases
#### Default a BelongsTo field

To use the `default()` method on a Nova `BelongsTo` field, you can supply either:

* An instance of an Eloquent model:

    ```php
    // $owner = $request->user();

    BelongsTo::make('Owner')
        ->default($owner),
    ```

* The primary key of the related record

    ```php
    // $id = 1;

    BelongsTo::make('Owner')
        ->default($id),
    ```

#### Default a MorphTo field

To use the `default()` method on a Nova `MorphTo` field, you can supply either:

* An instance of an Eloquent model (the simplest option), e.g.:

    ```php
    // $post = App\Post::find(1);

    MorphTo::make('Post', 'commentable')
        ->default($post),
    ```

* An array containing the primary key and the morph type, e.g.:

    ```php
    // $postId = 1;

    MorphTo::make('Post', 'commentable')
        ->default([$postId, App\Nova\Post::class]), // The Resource class or class name
    ```
    or:
    ```php
    MorphTo::make('Post', 'commentable')
        ->default([$postId, App\Post::class]), // The Eloquent model class or class name
    ```
    or:
    ```php
    MorphTo::make('Post', 'commentable')
        ->default([$postId, 'posts']), // The uriKey string
    ```

* An instance of a Nova Resource, e.g.:

    ```php
    // $postResource = new App\Nova\Post(App\Post::find(1));

    MorphTo::make('Post', 'commentable')
        ->default($postResource),
    ```

### Default the last saved value

Use the `defaultLast()` method to cache the last value that was saved for this field on this resource and repopulate it on the next new resource:

```php
Text::make('Name')
    ->defaultLast(),
```

The value is cached uniquely to the user, resource, field, and attribute. The default cache duration is an hour, but this is customisable (see Configuration).

This can be used, for example, to speed up creating multiple resources one after another with the same parent resource, e.g.

```php
BelongsTo::make('Owner')
    ->defaultLast(),
```

*Note:* The `defaultLast()` method handles the morph type for `MorphTo` fields automatically.

### Display using a callback

Both the `default()` and `defaultLast()` methods can take a callback as the final parameter which will transform the defaulted value (whether retrieved from cache or from the `default()` method) before it is populated:

```php
$lastInvoiceNumber = auth()->user()->last_invoice_number;

Number::make('Invoice Number')
    ->default($lastInvoiceNumber, function($value, NovaRequest $request) {
        return $value + 1; // Note: Here the $value came from $lastInvoiceNumber
    }),
```

```php
Number::make('Invoice Number')
    ->defaultLast(function($value, NovaRequest $request) {
        return $value + 1; // Note: Here the $value came from the cache
    }),
```

This can be used, for example, to increment a value each time a new resource is created. *Note:* This is a convenience only and should not be relied upon for uniqueness or strictly sequential incrementing.

### Default last value or static value

If the user does not yet have a 'last' value stored, or the cache has expired, the value for `defaultLast()` will be blank. If you want to fall back to another value if nothing is found in the cache, you can simply do this in the callback, e.g.:

```php
BelongsTo::make('Owner')
    ->defaultLast(function($value, NovaRequest $request) {
        return $value ?: $request->user()->id;
    }),
```

In this example, the owner of the first record created will default to the current user, but every subsequent record will default to the last value saved.

## Advanced Usage
### Extend

Out of the box, the package supports all standard Nova fields which have a single value and can be edited on the 'create' form. There is specific behaviour for the `BelongsTo` and `MorphTo` fields, as described above.

This package does not support any of the fields that implement `Laravel\Nova\Contracts\ListableField`, such as `HasMany`, `BelongsToMany` etc., or fields that extend `Laravel\Nova\Fields\File`, such as `File`, `Image` or `Avatar`.

Any custom field with a single value which extends `Laravel\Nova\Fields\Field` *should* work without customisation. However, if required, you can extend the behaviour of defaultable fields to support custom field types which need additional metadata to be populated.

The `DefaultableField::extend()` method takes the class name of your custom field and a callback which receives `$field` and `$value`. You must return the `$field` and it is suggested that you use `$field->withMeta()` to send the appropriate metadata.

In your `App\Providers\NovaServiceProvider`:
```php
use Inspheric\NovaDefault\DefaultableField;

DefaultableField::extend(YourField::class, function($field, $value) {
    return $field->withMeta([
        'value' => $value, // This line is usually required to populate the value
        'yourMeta' => 'yourValue', // Any additional meta depends on your custom field type
    ]);
});
```

You can pass an array of field types as the first argument to use the same callback on all of them:

```php
DefaultableField::extend([YourField::class, YourOtherField::class], function($field, $value) {
    // ...
});
```

Alternatively, `Inspheric\NovaDefault\DefaultableField` is macroable, so you can add a macro and then use the macro's name as a string as the second argument for the `extend()` method:

```php
DefaultableField::macro('handleYourField', function($field, $value) {
    // ...
});

DefaultableField::extend(YourField::class, 'handleYourField');
DefaultableField::extend(YourOtherField::class, 'handleYourField');
```

## Configuration

The configuration can be published using `php artisan vendor:publish --provider="Inspheric\NovaDefaultable\DefaultableServiceProvider" --tag="config"`.

The configuration file contains two keys:
* `cache.key` - The key to use to store the "last" values in the cache. Defaults to 'default_last' and will be prepended to the authenticated user ID, resource class and field attribute for uniqueness.
* `cache.ttl` - The time to store the last values in the cache, in seconds. Defaults to one hour (60 * 60).
