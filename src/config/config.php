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

return [

    /*
    |--------------------------------------------------------------------------
    | Default Method
    |--------------------------------------------------------------------------
    |
    | Define the default method, this will define the data grid behavior.
    |
    | Supported: "single", "group", "infinite"
    |
    */

    'method' => 'single',
    /*
    |--------------------------------------------------------------------------
    | Threshold
    |--------------------------------------------------------------------------
    |
    | Define the default threshold (number of results before pagination begins).
    |
    */

    'threshold' => 100,
    /*
    |--------------------------------------------------------------------------
    | Throttle
    |--------------------------------------------------------------------------
    |
    | Define the default throttle, which is the maximum results set.
    |
    */

    'throttle' => 100,
];
