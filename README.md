# Laravel Nova Defaultable Fields
Populate default values for Nova fields when creating resources.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/inspheric/nova-defaultable.svg?style=flat-square)](https://packagist.org/packages/inspheric/nova-defaultable)
[![Total Downloads](https://img.shields.io/packagist/dt/inspheric/nova-defaultable.svg?style=flat-square)](https://packagist.org/packages/inspheric/nova-defaultable)

## Installation

Install the package into a Laravel app that uses [Nova](https://nova.laravel.com) with Composer:

```bash
composer require inspheric/nova-defaultable
```

## Basic Usage

Use the `default()` method on any standard Nova field to populate a simple value:

```php
Text::make('Name')
    ->default($value),
```

The `default()` method can also take a callback as the first parameter, which will return the value to be populated:

```php

use Laravel\Nova\Resource;

Text::make('Name')
    ->default(function(Resource $resource) {
        return $resource->derived_attribute;
    }),
```

Use the `defaultLast()` method to cache the last value that was saved for this field on this resource and repopulate it on the next new resource:

```php
Text::make('Name')
    ->defaultLast(),
```

The value is cached uniquely to the user ID, resource and attribute. The default cache duration is an hour, but this is customisable (see Configuration).

This can be used for example to speed up creating multiple resources with the same parent resource one after another, e.g.

```php
BelongsTo::make('User')
    ->defaultLast(),
```

## Display Using Callback

Both the `default()` and `defaultLast()` methods can take a callback as the final parameter which will transform the defaulted value (whether retrieved from cache or from the `default()` method) before it is populated:

```php
Number::make('Sequence Number')
    ->default($value, function($value, Resource $resource) {
        return $value + 1;
    }),
```

```php
Number::make('Sequence Number')
    ->defaultLast(function($value, Resource $resource) {
        return $value + 1;
    }),
```

This can be used for example to increment a value each time a new resource is created. *Note:* This should not be relied upon for uniqueness or strictly sequential incrementing.

## Configuration
