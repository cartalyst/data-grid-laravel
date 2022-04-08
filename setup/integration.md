### Integration

After installing the package, open your Laravel config file which is located at `config/app.php` and add the following lines.

In the `$providers` array add the following service provider for this package.

```php
Cartalyst\DataGrid\Laravel\DataGridServiceProvider::class,
```

In the `$aliases` array add the following facade for this package.

```php
'DataGrid' => Cartalyst\DataGrid\Laravel\Facades\DataGrid::class,
```

###### Configuration

After installing, you can publish the package configuration file into your application by running the following command on your terminal:

`php artisan vendor:publish --tag="cartalyst:data-grid.config"`

This will publish the config file to `config/cartalyst/data-grid/config.php` where you can modify the package configuration.
