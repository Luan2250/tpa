# Complemento do README – Guia de resolução (TPA – Loja de Produtos)

Guia de consulta para a prova. A prova costuma seguir este mesmo modelo: um projeto Laravel com telas e rotas prontas, e você implementa o back-end (migrations, models, controllers, middlewares, validação).

---

## 0. Antes de começar

### Ambiente (Windows + PHP instalado à mão)

Se aparecer `could not find driver (Connection: sqlite ...)`, a extensão do SQLite está desativada no `php.ini`.

1. Descubra qual `php.ini` está em uso: `php --ini`
2. Abra esse arquivo e remova o `;` do começo destas linhas:
   ```ini
   extension=pdo_sqlite
   extension=sqlite3
   ```
3. Se necessário, ajuste `extension_dir = "ext"` (ou o caminho completo, ex.: `"C:\php\ext"`).
4. Pare o `php artisan serve` (Ctrl+C), confira com `php -m` (devem aparecer `pdo_sqlite` e `sqlite3`) e rode de novo.
5. Se o `php.ini` não tiver essas linhas, copie `php.ini-development` para `php.ini`.

### Subir o projeto

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan serve
```

No Windows/PowerShell, se o `touch` não existir: `New-Item database/database.sqlite -ItemType File`.

### Antes de codar: leia o "contrato"

O seeder e os arquivos de views definem os nomes que você **precisa** usar. Leia antes de escrever migration ou model:

- `database/seeders/ProdutoSeeder.php`: nome da model, tabela e colunas
- `routes/web.php`: nomes das rotas
- `resources/views/...`: nomes dos campos dos formulários e variáveis esperadas (`$produtos`, `$vendas`)

---

## 1. Ordem de implementação e arquivos alterados

| # | Requisito | Pontos | Arquivos mexidos |
|---|---|---|---|
| R1 | Listagem pública | 2,0 | migration `produtos`, `Models/Produto.php`, `ProdutoController@index` |
| R2 | Login/logout | 1,5 | `LoginController@attempt` e `@logout` |
| R3 | Middleware `auth` | 1,0 | `routes/web.php` |
| R4 | Middleware `vendedor` | 2,0 | `Http/Middleware/VendedorMiddleware.php`, `bootstrap/app.php`, `routes/web.php` |
| R5 | Cadastro validado | 2,0 | `Http/Requests/StoreProdutoRequest.php`, `ProdutoController@store` |
| R6 | Dono do produto | 1,0 | `ProdutoController@store` |
| R7 | Compra | 2,5 | migrations `vendas` e `produto_venda`, `Models/Venda.php`, `CompraController@store` |
| R8 | Minhas compras | 2,0 | `CompraController@index` |
| Bônus | Estoque | 2,0 | `CompraController@store` |

---

## 2. Passo a passo por requisito

### R1 – Listagem pública de Produtos

```bash
php artisan make:model Produto -m
```

**Migration** (`database/migrations/..._create_produtos_table.php`):

```php
Schema::create('produtos', function (Blueprint $table) {
    $table->id();
    $table->string('nome');
    $table->string('categoria');
    $table->integer('garantia_meses');
    $table->decimal('preco', 8, 2);
    $table->integer('estoque');
    $table->foreignId('vendedor_id')->constrained('users');
    $table->timestamps();
});
```

**Model** (`app/Models/Produto.php`):

```php
protected $fillable = ['nome', 'categoria', 'garantia_meses', 'preco', 'estoque', 'vendedor_id'];

public function vendedor()
{
    return $this->belongsTo(User::class, 'vendedor_id');
}
```

**Controller** (`ProdutoController`):

```php
use App\Models\Produto;

public function index()
{
    $produtos = Produto::with('vendedor')->get();

    return view('produtos.index', compact('produtos'));
}
```

Depois: `php artisan migrate:fresh --seed`.

**Armadilhas:**

- `belongsTo(User::class, 'vendedor_id')`: sem o 2º argumento o Laravel procura `user_id`.
- `constrained('users')`: sem o nome da tabela o Laravel tentaria `vendedors`.
- `with('vendedor')` é pedido no enunciado (evita o problema N+1).
- O VS Code pode criar um `use App\Models\Produto as ModelsProduto;` duplicado ao aceitar um auto-import. Apague essa linha.
- Sublinhado vermelho do Intelephense nem sempre é erro real: recarregue a janela (`Ctrl+Shift+P` → *Developer: Reload Window*) e teste no navegador.

---

### R2 – Login e logout

```php
use Illuminate\Support\Facades\Auth;

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
```

**Armadilhas:**

- Os campos do formulário precisam se chamar `email` e `password`.
- Se a mensagem de erro não aparecer na tela, confira a chave que a view lê (`@error('email')` ou `$errors->any()`).
- As três linhas do logout (`logout`, `invalidate`, `regenerateToken`) são o padrão: decore.

---

### R3 – Middleware `auth` e R4 – Middleware `vendedor`

**Criar o middleware:**

```bash
php artisan make:middleware VendedorMiddleware
```

**`app/Http/Middleware/VendedorMiddleware.php`:**

```php
public function handle(Request $request, Closure $next): Response
{
    if (! $request->user() || $request->user()->tipo !== 'vendedor') {
        abort(403);
    }

    return $next($request);
}
```

**Registrar o apelido em `bootstrap/app.php`** (Laravel 11/12 **não tem** `Kernel.php`):

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'vendedor' => \App\Http\Middleware\VendedorMiddleware::class,
    ]);
})
```

**Aplicar em `routes/web.php`:**

```php
Route::get('/produtos', [ProdutoController::class, 'index'])->name('produtos.index');

Route::middleware(['auth', 'vendedor'])->group(function () {
    Route::get('/produtos/create', [ProdutoController::class, 'create'])->name('produtos.create');
    Route::post('/produtos', [ProdutoController::class, 'store'])->name('produtos.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
    Route::get('/compras/create', [CompraController::class, 'create'])->name('compras.create');
    Route::post('/compras', [CompraController::class, 'store'])->name('compras.store');
});
```

**Armadilhas:**

- A ordem `['auth', 'vendedor']` importa: `auth` primeiro (deslogado vai para o login), `vendedor` depois (logado sem perfil recebe 403).
- A listagem de produtos fica **fora** dos grupos (é pública).
- Sem o alias em `bootstrap/app.php`, aparece o erro `Target class [vendedor] does not exist`.
- O redirecionamento do `auth` usa a rota de nome `login`, que já existe.

---

### R5 – Cadastro validado e R6 – Dono do produto

```bash
php artisan make:request StoreProdutoRequest
```

**`app/Http/Requests/StoreProdutoRequest.php`:**

```php
public function authorize(): bool
{
    return true;   // o padrão gerado é false e causa 403 em todo envio
}

public function rules(): array
{
    return [
        'nome'           => ['required', 'string', 'max:255'],
        'categoria'      => ['required', 'in:Eletrônicos,Vestuário,Casa,Alimentos'],
        'garantia_meses' => ['required', 'integer', 'between:0,36'],
        'preco'          => ['required', 'numeric', 'gt:0'],
        'estoque'        => ['required', 'integer', 'min:0'],
    ];
}
```

**`ProdutoController@store`:**

```php
use App\Http\Requests\StoreProdutoRequest;
use Illuminate\Support\Facades\Auth;

public function store(StoreProdutoRequest $request)
{
    $dados = $request->validated();
    $dados['vendedor_id'] = Auth::id();

    Produto::create($dados);

    return redirect()->route('produtos.index');
}
```

**Armadilhas:**

- `authorize()` retornando `false` → 403 em todo envio.
- Trocar `Request` por `StoreProdutoRequest` na assinatura do `store`: é isso que dispara a validação.
- Usar `validated()` e não `all()`.
- `vendedor_id` vem de `Auth::id()`, **nunca** do formulário.
- Sem `$fillable` na model → `MassAssignmentException`.
- `between:0,36` aceita 0 e 36; `gt:0` rejeita 0 (`min:0` aceitaria).

---

### R7 – Compra

```bash
php artisan make:model Venda -m
php artisan make:migration create_produto_venda_table
```

**Migration `vendas`:**

```php
Schema::create('vendas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cliente_id')->constrained('users');
    $table->timestamps();
});
```

**Migration `produto_venda` (pivô):**

```php
Schema::create('produto_venda', function (Blueprint $table) {
    $table->id();
    $table->foreignId('venda_id')->constrained('vendas');
    $table->foreignId('produto_id')->constrained('produtos');
    $table->integer('quantidade');
    $table->decimal('preco_unitario', 8, 2);
    $table->timestamps();
});
```

**Model `Venda`:**

```php
protected $fillable = ['cliente_id'];

public function produtos()
{
    return $this->belongsToMany(Produto::class, 'produto_venda')
        ->withPivot('quantidade', 'preco_unitario')
        ->withTimestamps();
}
```

**`CompraController@store` (versão básica, sem estoque):**

```php
use App\Models\Venda;
use Illuminate\Support\Facades\Auth;

public function store(Request $request)
{
    $itens = collect($request->input('itens', []))
        ->filter(fn ($quantidade) => (int) $quantidade > 0);

    $venda = Venda::create(['cliente_id' => Auth::id()]);

    foreach ($itens as $produtoId => $quantidade) {
        $produto = Produto::findOrFail($produtoId);

        $venda->produtos()->attach($produto->id, [
            'quantidade'     => (int) $quantidade,
            'preco_unitario' => $produto->preco,
        ]);
    }

    return redirect()->route('compras.index')->with('success', 'Compra realizada!');
}
```

**Armadilhas:**

- A migration da pivô precisa rodar **depois** de `vendas` e `produtos` (a ordem é pela data no nome do arquivo).
- O formulário envia `itens[id_do_produto] = quantidade`: a **chave** do array é o id do produto.
- `preco_unitario` guarda o preço do momento da compra.
- Sem `withPivot`, a view não consegue ler `$produto->pivot->quantidade`.
- Sem `withTimestamps`, os timestamps da pivô ficam nulos.
- Se "Compra realizada!" não aparecer, confira a chave do flash lida em `layouts/app.blade.php`.

---

### R8 – Minhas compras

```php
public function index()
{
    $vendas = Venda::where('cliente_id', Auth::id())
        ->with('produtos')
        ->latest()
        ->get();

    return view('compras.index', compact('vendas'));
}
```

**Armadilhas:**

- O `where('cliente_id', Auth::id())` é o ponto de correção: sem ele, um cliente vê as compras dos outros.
- Nome da variável da view: `$vendas`.

---

### Bônus – Estoque

```php
use Illuminate\Support\Facades\DB;

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
```

**Armadilhas:**

- Valide **antes** de criar a venda: se recusar, nenhuma compra pode existir.
- `decrement('estoque', $n)` faz o `UPDATE` direto no banco.
- `withInput()` mantém as quantidades digitadas após o erro.

---

## 3. Roteiro de testes (navegador)

Usuários (senha `senha123`): `ana@loja.test` e `bruno@loja.test` (vendedores), `carla@loja.test` e `diego@loja.test` (clientes).

1. **R1:** sem login, `/produtos` lista os produtos com o nome do vendedor.
2. **R2:** senha errada volta com erro; senha certa mostra o nome e o tipo no topo; **Sair** volta ao botão **Entrar**.
3. **R3:** deslogado, **Cadastrar Produto**, **Comprar** e **Minhas compras** levam ao login; **Produtos** abre normal.
4. **R4:** Carla em **Cadastrar Produto** → 403; Ana → formulário.
5. **R5:** formulário vazio mostra erros; garantia `48` erra; preço `0` erra; `Liquidificador / Casa / 12 / 149.90 / 7` salva.
6. **R6:** item da Ana mostra "Ana Vendedora"; item do Bruno mostra "Bruno Vendedor".
7. **R7:** Carla compra 2 de um item e 1 de outro → vai a **Minhas compras** com "Compra realizada!".
8. **R8:** a compra mostra os 2 itens com subtotais e total; Diego **não** vê a compra da Carla.
9. **Bônus:** quantidade acima do estoque → erro e nenhuma compra; comprar 1 reduz o estoque em 1.

---

## 4. Cheatsheet de comandos

```bash
# Criar arquivos
php artisan make:model Nome -m              # model + migration
php artisan make:migration create_x_table   # só migration
php artisan make:middleware NomeMiddleware
php artisan make:request StoreNomeRequest
php artisan make:controller NomeController

# Banco
php artisan migrate                # roda migrations pendentes
php artisan migrate:fresh --seed   # recria TUDO e roda os seeders
php artisan migrate:status

# Diagnóstico
php artisan route:list             # confere rotas e middlewares aplicados
php artisan optimize:clear         # limpa caches (config, rotas, views)
composer dump-autoload             # recarrega o autoload de classes
php -m                             # lista extensões do PHP carregadas
php artisan tinker                 # console para testar models

php artisan serve
```

---

## 5. Erros comuns e como resolver

| Sintoma | Causa provável | Solução |
|---|---|---|
| `could not find driver` | extensão SQLite desativada | ativar `pdo_sqlite` e `sqlite3` no `php.ini` |
| "Produtos não semeados" | migration/model do R1 incompletas | conferir colunas e rodar `migrate:fresh --seed` |
| `Target class [vendedor] does not exist` | alias não registrado | `bootstrap/app.php` → `$middleware->alias([...])` |
| 403 em todo envio do formulário | `authorize()` retorna `false` | trocar para `true` |
| `MassAssignmentException` | campo fora do `$fillable` | adicionar o campo (inclusive `vendedor_id`) |
| `Class "App\Models\Produto" not found` | namespace/arquivo errado ou cache | conferir `namespace App\Models;` e `composer dump-autoload` |
| Erro de chave estrangeira no migrate | ordem das migrations | criar `vendas` e `produtos` antes da pivô |
| Coluna `user_id` não encontrada | `belongsTo` sem a chave | `belongsTo(User::class, 'vendedor_id')` |
| `Undefined property: pivot` | falta `withPivot` | `->withPivot('quantidade', 'preco_unitario')` |
| Visitante deslogado recebe 403 | `vendedor` sem `auth` | usar `['auth', 'vendedor']` nessa ordem |
| Mensagem de erro/sucesso não aparece | chave diferente da que o layout lê | conferir `session('...')` e `$errors` no layout |

---

## 6. Checklist final antes de entregar

- [ ] `php artisan migrate:fresh --seed` roda sem erro e sem avisos
- [ ] `php artisan serve` sobe e as telas abrem
- [ ] Todos os roteiros da seção 3 passam
- [ ] Nenhum arquivo "contrato" foi alterado (seeder, rotas, views, nomes de tabela/colunas)
- [ ] `php artisan route:list` mostra `auth` e `vendedor` nas rotas certas
- [ ] Projeto inteiro compactado em `.zip` (inclua a pasta toda) e enviado no Classroom