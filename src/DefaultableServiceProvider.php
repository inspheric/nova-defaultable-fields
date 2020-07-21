<?php

namespace Inspheric\NovaDefaultable;

use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Fields\Field;

class DefaultableServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/defaultable_field.php' => config_path('defaultable_field.php'),
        ]);

        $this->mergeConfigFrom(
            __DIR__.'/../config/defaultable_field.php', 'defaultable_field'
        );

        Field::macro('defaultUsing', function($value, callable $callback = null) {
            /** @var Field $this */
            return DefaultableField::default($this, $value, $callback);
        });

        Field::macro('defaultLast', function(callable $callback = null) {
            /** @var Field $this */
            return DefaultableField::defaultLast($this, $callback);
        });
    }
}
