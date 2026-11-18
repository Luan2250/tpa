<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

#adicionei a rota e implementei os dois metodos 
class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function attempt(Request $request)
    {
    $credenciais = $request->only('email', 'password');

    if (Auth::attempt($credenciais)) {
        $request->session()->regenerate();

        return redirect()->route('produtos.index');
    }

    return back()
        ->withErrors(['email' => 'E-mail ou senha inválidos.'])
        ->onlyInput('email');
    }   
    public function logout(Request $request)
    {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('produtos.index');
    }
}
