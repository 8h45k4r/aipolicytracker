<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Daily, anonymous page-view counts for a small set of funnel paths. */
class PageView extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['day' => 'date'];
    }

    public static function hit(string $path): void
    {
        $path = mb_substr($path, 0, 191);
        $day = now()->toDateString();
        $updated = DB::table('page_views')->where('day', $day)->where('path', $path)->increment('views');
        if ($updated === 0) {
            DB::table('page_views')->insertOrIgnore(['day' => $day, 'path' => $path, 'views' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
