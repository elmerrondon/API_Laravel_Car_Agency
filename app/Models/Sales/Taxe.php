<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Taxe extends Model
{
    use SoftDeletes;

    protected $fillable = ["name","code","percentage","is_active"];

    protected $hidden = ["created_at","updated_at","deleted_at"];

    public function casts() : array{
        return ["is_active" => "boolean"];
    }

}
