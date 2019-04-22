# Laravel Nova Defaultable Fields
Populate default values for Nova fields when creating resources.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/inspheric/nova-defaultable.svg?style=flat-square)](https://packagist.org/packages/inspheric/nova-defaultable)
[![Total Downloads](https://img.shields.io/packagist/dt/inspheric/nova-defaultable.svg?style=flat-square)](https://packagist.org/packages/inspheric/nova-defaultable)

## Installation

Install the package into a Laravel app that uses [Nova](https://nova.laravel.com) with Composer:

```bash
composer require inspheric/nova-defaultable
```

Add the trait `Inspheric\NovaDefaultable\HasDefaultableFields` to your base Resource class (located at `app\Nova\Resource.php`):

```php
use Inspheric\NovaDefaultable\HasDefaultableFields;

abstract class Resource extends NovaResource
{
    use HasDefaultableFields;

    // ...
}
```

## Basic Usage
### Default any value

Use the `default()` method on any standard Nova field to populate a simple value:

```php
Text::make('Name')
    ->default($value),
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

You can use the `default()` method on a Nova `BelongsTo` field by simply returning the primary key of the related record:

```php
BelongsTo::make('Owner', 'owner')
    ->default($owner->id),
```

#### Default a MorphTo field

To use the `default()` method on a Nova `MorphTo` field, you must return an array containing the primary key and the morph type:

```php
MorphTo::make('Post', 'commentable')
    ->default([$post->id, Post::class]),
```
or:
```php
MorphTo::make('Post', 'commentable')
    ->default([$post->id, 'posts']),
```

### Default the last saved value

Use the `defaultLast()` method to cache the last value that was saved for this field on this resource and repopulate it on the next new resource:

```php
Text::make('Name')
    ->defaultLast(),
```

The value is cached uniquely to the user ID, resource and attribute. The default cache duration is an hour, but this is customisable (see Configuration).

This can be used, for example, to speed up creating multiple resources one after another with the same parent resource, e.g.

```php
BelongsTo::make('User')
    ->defaultLast(),
```

*Note:* The `defaultLast()` method handles the morph type for `MorphTo` fields automatically.

### Display Using Callback

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

This can be used, for example, to increment a value each time a new resource is created. *Note:* This should not be relied upon for uniqueness or strictly sequential incrementing.

## Advanced Usage
### Extend

Out of the box, the package supports all standard Nova fields which have a single value. It does not support any fields that implement `Laravel\Nova\Contracts\ListableField`, such as `HasMany`, `BelongsToMany` etc.

```php
```

// DefaultableField::extend(Text::class, function($field, $value) {
//     return $field->withMeta([
//         'value' => 'BLAHBLAH',
//     ]);
// });

// DefaultableField::macro('handleText', function($field, $value) {
//     return $field->withMeta([
//         'value' => 'BLAH2BLAH2',
//     ]);
// });
// DefaultableField::extend(Text::class, 'handleText');


## Configuration

The configuration can be published using `php artisan vendor:publish --provider="Inspheric\NovaDefaultable\DefaultableServiceProvider" --tag="config"`.

The configuration file contains two keys:
* `cache.key` - The key to use to store the "last" values in the cache. Defaults to 'default_last' and will be prepended to authenticated user ID, resource class and field attribute for uniqueness.
* `cache.ttl` - The time to store the last values in the cache, in seconds. Defaults to one hour.
