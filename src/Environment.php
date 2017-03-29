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

use Cartalyst\DataGrid\DataGrid;
use Cartalyst\DataGrid\Contracts\Handler;
use Cartalyst\DataGrid\Contracts\Provider;
use Symfony\Component\HttpFoundation\Request;
use Cartalyst\DataGrid\Providers\RequestProvider;

class Environment
{
    /**
     * The request provider instance.
     *
     * @var \Cartalyst\DataGrid\Contracts\Provider
     */
    protected $requestProvider;

    /**
     * Constructor.
     *
     * @param  \Cartalyst\DataGrid\Contracts\Provider  $requestProvider
     * @return void
     */
    public function __construct(Provider $requestProvider = null)
    {
        if (is_null($requestProvider)) {
            $requestProvider = new RequestProvider(Request::createFromGlobals());
        }

        $this->requestProvider = $requestProvider;
    }

    /**
     * Create a new data grid instance.
     *
     * @param  \Cartalyst\DataGrid\Contracts\Handler  $dataHandler
     * @param  mixed  $requestProvider
     * @return \Cartalyst\DataGrid\DataGrid|mixed
     */
    public function make(Handler $dataHandler, $requestProvider = null)
    {
        return DataGrid::make($dataHandler, $requestProvider ?: $this->requestProvider);
    }
}
