<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GithubDeveloperMapping extends Model
{
    protected $table = 'github_developer_mappings';

    protected $primaryKey = 'github_username';

    public $incrementing = false;

    public const CREATED_AT = null;

    protected $keyType = 'string';
}
