<?php

/**
 * Part of the Data Grid package.
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Cartalyst PSL License.
 *
 * This source file is subject to the Cartalyst PSL License that is
 * bundled with this package in the LICENSE file.
 *
 * @package    Data Grid
 * @version    4.0.0
 * @author     Cartalyst LLC
 * @license    Cartalyst PSL
 * @copyright  (c) 2011-2015, Cartalyst LLC
 * @link       http://cartalyst.com
 */

namespace Cartalyst\DataGrid\Laravel;

use Illuminate\Support\ServiceProvider;
use Cartalyst\DataGrid\RequestProvider;

class DataGridServiceProvider extends ServiceProvider
{
    /**
     * {@inheritDoc}
     */
    public function boot()
    {
//        $this->configureDomPdf();
    }

    /**
     * {@inheritDoc}
     */
    public function register()
    {
        $this->prepareResources();

//        $this->registerIlluminateRequestProvider();
    }

    /**
     * Prepare the package resources.
     *
     * @return void
     */
    protected function prepareResources()
    {
        // Publish config
        $config = realpath(__DIR__.'/../config/config.php');

        $this->mergeConfigFrom($config, 'cartalyst.data-grid');

        $this->publishes([
            $config => config_path('cartalyst.data-grid.php'),
        ], 'config');

        // Publish assets
        $assets = realpath(__DIR__.'/../../public');

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
        $this->app['datagrid.request'] = $this->app->share(function ($app) {
            $config = $app['config']->get('cartalyst.data-grid');

            $requestProvider = new RequestProvider($app['request'], null, null, $app['view']);

            $requestProvider->setDefaultMethod($config['method']);

            $requestProvider->setDefaultThreshold($config['threshold']);

            $requestProvider->setDefaultThrottle($config['throttle']);

            return $requestProvider;
        });
    }

    /**
     * Configure Dom Pdf.
     *
     * @return void
     */
    protected function configureDomPdf()
    {
        $configFile = base_path().'/vendor/dompdf/dompdf/dompdf_config.inc.php';

        if ($this->app['files']->exists($configFile)) {
            if (! defined('DOMPDF_ENABLE_AUTOLOAD')) {
                define('DOMPDF_ENABLE_AUTOLOAD', false);
            }

            require_once $configFile;
        }
    }
}
