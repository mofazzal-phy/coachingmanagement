<?php
namespace Modules\Core\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\app\Traits\HasUuid;
use Modules\Core\app\Traits\Searchable;
use Modules\Core\app\Traits\Filterable;

class BaseModel extends Model
{
    use HasUuid, Searchable, Filterable;

    public $incrementing = false;
    protected $keyType = 'string';
    protected array $searchable = ['name'];
    protected array $filterable = ['status', 'is_active'];
}
