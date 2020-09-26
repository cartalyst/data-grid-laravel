<?php

/*
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
 * @version    4.0.0
 * @author     Cartalyst LLC
 * @license    Cartalyst PSL
 * @copyright  (c) 2011-2020, Cartalyst LLC
 * @link       https://cartalyst.com
 */

namespace Cartalyst\DataGrid\Laravel\Tests\Stubs;

use Cartalyst\Attributes\EntityTrait;
use Cartalyst\Attributes\EntityInterface;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Foo extends Eloquent implements EntityInterface
{
    use EntityTrait;
}
