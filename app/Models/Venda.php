<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venda extends Model
{
    protected $fillable = ['cliente_id'];

    public function produtos()
    {
        return $this->belongsToMany(Produto::class, 'produto_venda')
            ->withPivot('quantidade', 'preco_unitario')
            ->withTimestamps();
    }
}