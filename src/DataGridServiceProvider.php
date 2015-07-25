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
    public function register()
    {
        $this->prepareResources();

        $this->registerIlluminateRequestProvider();

        $this->registerDataGrid();
    }

    /**
     * Prepare the package resources.
     *
     * @return void
     */
    protected function prepareResources()
    {
        // Publish config
        $config = realpath(__DIR__.'/../resources/config/config.php');

        $this->mergeConfigFrom($config, 'cartalyst.data-grid');

        $this->publishes([
            $config => config_path('cartalyst.data-grid.php'),
        ], 'config');

        // Publish assets
        $assets = realpath(__DIR__.'/../../data-grid/resources/assets');

        $this->publishes([
            $assets => public_path('assets/cartalyst/data-grid'),
        ], 'assets');
    }

    /**
     * Register request provider.
     *
     * @return void
     */
    protected function registerIlluminateRequestProvider()
    {
        $this->app->singleton('datagrid.request', function ($app) {
            $requestProvider = new RequestProvider($app['request']);

            $config = $app['config']->get('cartalyst.data-grid');

            $requestProvider->setDefaultMethod($config['method']);
            $requestProvider->setDefaultThreshold($config['threshold']);
            $requestProvider->setDefaultThrottle($config['throttle']);

            return $requestProvider;
        });
    }

    /**
     * Register data grid.
     *
     * @return void
     */
    protected function registerDataGrid()
    {
        $this->app->singleton('datagrid', function ($app) {
            return new Environment($app['datagrid.request']);
        });
    }
}
