<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Illuminate\Http\Request;
use App\Models\Venda;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function index()
    {
         $vendas = Venda::where('cliente_id', Auth::id())
        ->with('produtos')
        ->latest()
        ->get();

        return view('compras.index', compact('vendas'));
    }

    public function create()
    {
        $produtos = Produto::orderBy('nome')->get();

        return view('compras.create', compact('produtos'));
    }

   public function store(Request $request)
{
    $itens = collect($request->input('itens', []))
        ->filter(fn ($quantidade) => (int) $quantidade > 0);

    if ($itens->isEmpty()) {
        return back()->withErrors(['itens' => 'Selecione pelo menos um item.']);
    }

    $produtos = Produto::whereIn('id', $itens->keys())->get()->keyBy('id');

    // 1) valida o estoque ANTES de criar qualquer coisa
    foreach ($itens as $produtoId => $quantidade) {
        $produto = $produtos->get($produtoId);

        if (! $produto || (int) $quantidade > $produto->estoque) {
            return back()
                ->withErrors(['itens' => 'Estoque insuficiente para "' . ($produto->nome ?? 'produto') . '".'])
                ->withInput();
        }
    }

    // 2) cria a venda, anexa os itens e baixa o estoque (tudo ou nada)
    DB::transaction(function () use ($itens, $produtos) {
        $venda = Venda::create(['cliente_id' => Auth::id()]);

        foreach ($itens as $produtoId => $quantidade) {
            $produto = $produtos[$produtoId];

            $venda->produtos()->attach($produto->id, [
                'quantidade'     => (int) $quantidade,
                'preco_unitario' => $produto->preco,
            ]);

            $produto->decrement('estoque', (int) $quantidade);
        }
    });

    return redirect()->route('compras.index')->with('success', 'Compra realizada!');
}
}
