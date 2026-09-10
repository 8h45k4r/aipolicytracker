<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsFutureImage extends Model
{
    use SoftDeletes, HasUuids;

    protected $fillable = [
        'news_id',
        'type',
        'name',
        'path',
    ];

    public function news(){
        return $this->belongsTo(News::class);
    }
}
