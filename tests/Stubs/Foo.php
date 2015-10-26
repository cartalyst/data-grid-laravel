<?php

namespace Cartalyst\DataGrid\Laravel\Tests\Stubs;

use Cartalyst\Attributes\EntityTrait;
use Cartalyst\Attributes\EntityInterface;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Foo extends Eloquent implements EntityInterface
{
    use EntityTrait;
}
