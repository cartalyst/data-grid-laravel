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
 * @version    1.0.0
 * @author     Cartalyst LLC
 * @license    Cartalyst PSL
 * @copyright  (c) 2011-2015, Cartalyst LLC
 * @link       http://cartalyst.com
 */

namespace Cartalyst\DataGrid\Laravel;

use Illuminate\Support\ServiceProvider;
use Cartalyst\DataGrid\Providers\RequestProvider;

class DataGridServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                $this->getResourcePath('config/config.php') => config_path('cartalyst/data-grid/config.php'),
            ], 'config');

            // Publish assets
            $this->publishes([
                realpath(__DIR__.'/../../data-grid/resources/assets') => public_path('assets/vendor/cartalyst/data-grid'),
            ], 'assets');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function register()
    {
        $this->mergeConfigFrom(
            $this->getResourcePath('config/config.php'), 'cartalyst.data-grid.config'
        );

        $this->registerIlluminateRequestProvider();

        $this->registerDataGrid();
    }

    /**
     * Register request provider.
     *
     * @return void
     */
    protected function registerIlluminateRequestProvider()
    {
        $this->app->singleton('datagrid.request', function ($app) {
            $config = $app['config']->get('cartalyst.data-grid');

            $requestProvider = new RequestProvider($app['request']);

            $requestProvider->setDefaultMethod($config['method']);
            $requestProvider->setDefaultThrottle($config['throttle']);
            $requestProvider->setDefaultThreshold($config['threshold']);

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
     * @return string
     */
    protected function getResourcePath($resource)
    {
        return realpath(__DIR__.'/../resources/'.$resource);
    }
}
