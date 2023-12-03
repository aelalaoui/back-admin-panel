<?php

namespace App\Models;

use App\Traits\Searchable;
use \Illuminate\Database\Eloquent\Model as EloquentModel;

class Model extends EloquentModel
{
    use Searchable;
}
