<?php

/**
 * Part of the Data Grid Laravel package.
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Cartalyst PSL License.
 *
 * This source file is subject to the Cartalyst PSL License that is
 * bundled with this package in the LICENSE file.
 *
 * @package    Data Grid Laravel
 * @version    1.0.2
 * @author     Cartalyst LLC
 * @license    Cartalyst PSL
 * @copyright  (c) 2011-2017, Cartalyst LLC
 * @link       http://cartalyst.com
 */

namespace Cartalyst\DataGrid\Laravel;

use Illuminate\Support\ServiceProvider;
use Cartalyst\DataGrid\Providers\RequestProvider;

class DataGridServiceProvider extends ServiceProvider
{
    /**
     * Holds the configuration values.
     *
     * @var array
     */
    protected $config = [];

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                $this->getResourcePath('config/config.php') => config_path('cartalyst/data-grid/config.php'),
            ], 'cartalyst:data-grid.config');

            // Publish assets
            $this->publishes([
                $this->getResourcePath('assets', true) => public_path('assets/vendor/cartalyst/data-grid'),
            ], 'cartalyst:data-grid.assets');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function register()
    {
        $configurationKey = 'cartalyst.data-grid.config';

        $this->mergeConfigFrom(
            $this->getResourcePath('config/config.php'), $configurationKey
        );

        $this->config = $this->app['config']->get($configurationKey);

        $this->registerRequestProvider();

        $this->registerDataGrid();
    }

    /**
     * Register request provider.
     *
     * @return void
     */
    protected function registerRequestProvider()
    {
        $this->app->singleton('datagrid.request', function ($app) {
            $requestProvider = new RequestProvider($app['request']);

            $requestProvider->setDefaultMethod($this->config['method']);
            $requestProvider->setDefaultThrottle($this->config['throttle']);
            $requestProvider->setDefaultThreshold($this->config['threshold']);

            return $requestProvider;
        });
    }

    /**
     * Registers Data Grid.
     *
     * @return void
     */
    protected function registerDataGrid()
    {
        $this->app->singleton('datagrid', function ($app) {
            return new Environment($app['datagrid.request']);
        });

        $this->app->alias('datagrid', 'Cartalyst\DataGrid\DataGrid');
    }

    /**
     * Returns the full path to the given resource.
     *
     * @param  string  $resource
     * @param  bool  $mainPackage
     * @return string
     */
    protected function getResourcePath($resource, $mainPackage = false)
    {
        if ($mainPackage === true) {
            return realpath(__DIR__.'/../../data-grid/resources/'.$resource);
        }

        return realpath(__DIR__.'/../resources/'.$resource);
    }
}
