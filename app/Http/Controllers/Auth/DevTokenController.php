<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

final class DevTokenController extends Controller
{
    public function issue(Request $r)
    {
        if (!App::isLocal()) {
            abort(403, 'Tokens can be issued only in local env.');
        }

        $data = $r->validate([
            'email'     => ['required','email'],
            'abilities' => ['array'],
            'abilities.*' => ['string'],
        ]);

        $user = User::firstOrCreate(
            ['email'=>$data['email']],
            ['name'=>'VNU Student','password'=>bcrypt(str()->random(16))]
        );

        $token = $user->createToken('postman', $data['abilities'] ?? ['*'])->plainTextToken;

        return response()->json(['token'=>$token, 'user'=>[
            'id'=>$user->id, 'email'=>$user->email, 'name'=>$user->name
        ]]);
    }
}
