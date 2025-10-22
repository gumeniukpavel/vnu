<?php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

final class MeController
{
    public function __invoke(Request $r)
    {
        return response()->json([
            'id'    => $r->user()->id,
            'name'  => $r->user()->name,
            'email' => $r->user()->email,
        ]);
    }
}
