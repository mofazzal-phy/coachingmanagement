<?php

namespace Modules\Settings\app\Models;

use Modules\Core\app\Models\BaseModel;

class Setting extends BaseModel
{
    protected $fillable = ['key', 'value', 'group', 'type', 'description'];
    protected array $searchable = ['key'];
    protected array $filterable = ['group'];
}
