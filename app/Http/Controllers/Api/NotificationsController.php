<?php
namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

final class NotificationsController
{
    public function index(Request $r)
    {
        // поки — найпростіше читання зі своєї таблиці (можна віддати заглушку)
        $items = DB::table('notification_items')->where('user_id',$r->user()->id)
            ->orderByDesc('id')->limit(20)->get();
        return response()->json($items);
    }
}
