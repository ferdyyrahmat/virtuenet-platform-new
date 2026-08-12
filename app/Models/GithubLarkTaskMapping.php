<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GithubLarkTaskMapping extends Model
{
    protected $table = 'github_lark_task_mappings';

    protected $primaryKey = 'github_issue_url';

    public $incrementing = false;

    public const CREATED_AT = null;

    protected $keyType = 'string';

    public function taskUrl(): ?string
    {
        $template = config('services.lark.task_url_template');

        return $template ? str_replace('{guid}', urlencode($this->lark_task_guid), $template) : null;
    }
}
