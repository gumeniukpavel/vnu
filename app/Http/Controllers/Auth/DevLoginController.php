<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class DevLoginController extends Controller
{
    public function login()
    {
        abort_unless(app()->environment('local'), 403);
        $email = request('email','student@vnu.test');
        $user = User::firstOrCreate(['email'=>$email], ['name'=>'VNU Student','password'=>bcrypt(str()->random(12))]);
        Auth::login($user, true);
        return redirect('/')->with('status','Logged in as '.$email);
    }

    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        return redirect('/');
    }
}
