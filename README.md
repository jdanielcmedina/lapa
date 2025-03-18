# Lapa Framework

Um framework PHP minimalista para construir APIs REST e aplicações web.

## Características

- 🚀 Leve e rápido
- 🎯 Focado em APIs REST
- 🔌 Helpers dinâmicos
- 🛣️ Sistema de rotas simples
- 🔒 Segurança integrada
- 📝 Logs automáticos
- 💾 Cache simples
- 🗄️ Gerenciamento de arquivos
- 🔑 Sessões e cookies
- 🌐 Suporte a hosts virtuais
- 🔄 Integração com APIs externas
- 🛡️ Helpers de autenticação
- ✨ Validação de entrada
- 📦 Suporte a banco de dados (via Medoo)
- 📧 Suporte a email (via PHPMailer)
- ⚠️ Tratamento de erros personalizado
- 🔔 Mensagens flash
- 🖼️ Sistema de layouts
- 🔌 Sistema de plugins

### Instalação

```bash
composer create-project jdanielcmedina/lapa meu-projeto
```

Isto vai:
- Criar um novo diretório para o projeto
- Instalar o framework e dependências
- Criar a estrutura base:
  - public/
  - routes/
  - views/
  - storage/
  - helpers/
  - plugins/

### Início Rápido

```php
require 'vendor/autoload.php';
$app = new Lapa\Lapa();

// Rota básica
$app->on('GET /', function() {
    return ['mensagem' => 'Olá Mundo!'];
});

// Rota protegida
$app->on('GET /protegido', function() {
    if (!$this->requireAuth()) {
        return;
    }
    return ['dados' => 'conteúdo protegido'];
});

// Exemplo de API REST
$app->on('POST /usuarios', function() {
    $dados = $this->validate([
        'nome' => 'required|min:3',
        'email' => 'required|email'
    ]);
    
    if (!$dados) return; // Validação falhou
    
    return $this->success($dados, 'Usuário criado');
});
```

### Documentação

#### Manipulação de Requisições

```php
// Parâmetros GET
$query = $this->get('search');           // Parâmetro único
$allGet = $this->get();                  // Todos os parâmetros GET

// Dados POST
$nome = $this->post('name');             // Campo único
$allPost = $this->post();                // Todos os dados POST

// Dados combinados da requisição
$all = $this->request();                 // Todos os dados da requisição
$specific = $this->request('field');     // Campo específico

// Corpo JSON
$json = $this->body();                   // Corpo completo
$field = $this->body('field');           // Campo específico
```

#### Métodos de Resposta

```php
// Respostas de sucesso
return $this->success($data);                    // 200 OK
return $this->success($data, 'Criado', 201);     // 201 Created

// Respostas de erro
return $this->error('Entrada inválida', 400);    // 400 Bad Request
return $this->error('Não autorizado', 401);      // 401 Unauthorized

// Respostas personalizadas
return $this->response($data, 'json');           // JSON
return $this->response($html, 'html');           // HTML
return $this->response($text, 'text');           // Texto simples
return $this->response($xml, 'xml');             // XML
```

#### Autenticação e Proteção

```php
// Proteger rotas
$app->on('GET /api/dados', function() {
    if (!$this->requireAuth()) {
        return;
    }
    return ['dados' => 'protegido'];
});

// Rotas de administrador
$app->on('GET /admin', function() {
    if (!$this->requireAdmin()) {
        return;
    }
    return ['admin' => 'dashboard'];
});
```

#### Validação de Dados

```php
$validated = $this->validate([
    'nome' => 'required|min:3',
    'email' => 'required|email',
    'idade' => 'numeric|min:18',
    'role' => 'in:user,admin'
]);
```

#### Operações de Banco de Dados

```php
// Selecionar
$usuarios = $this->db->select('users', '*');

// Inserir
$id = $this->db->insert('users', [
    'nome' => 'John',
    'email' => 'john@example.com'
]);

// Atualizar
$this->db->update('users',
    ['status' => 'active'],
    ['id' => 1]
);

// Deletar
$this->db->delete('users', ['id' => 1]);
```

#### Gerenciamento de Arquivos

```php
// Upload
$filename = $this->upload('photo');

// Download
$this->download('file.pdf', 'custom-name.pdf');

// Armazenamento
$path = $this->storage('public');    // Obter caminho de armazenamento
$this->clear('cache');               // Limpar armazenamento
```

#### Integração com APIs Externas

```php
// Requisição GET
$data = $this->import('https://api.example.com/data', [
    'headers' => ['Authorization' => 'Bearer ' . $token]
]);

// Requisição POST
$result = $this->import('https://api.example.com/users', [
    'method' => 'POST',
    'data' => ['name' => 'John'],
    'headers' => ['X-API-Key' => 'key']
]);
```

#### Sessões e Cookies

```php
// Sessões
$this->session('user_id', 123);          // Definir
$id = $this->session('user_id');         // Obter
$this->session('user_id', false);        // Remover

// Cookies
$this->cookie('theme', 'dark', [         // Definir com opções
    'expire' => time() + 86400,
    'secure' => true
]);
$theme = $this->cookie('theme');         // Obter
$this->cookie('theme', false);           // Remover
```

### Configuração

```php
return [
    'debug' => true,
    'timezone' => 'UTC',
    
    // Configuração de CORS
    'cors' => [
        'enabled' => false,
        'origins' => '*',
        'methods' => 'GET, POST, PUT, DELETE, OPTIONS, PATCH',
        'headers' => 'Content-Type, Authorization, X-Requested-With',
        'credentials' => false
    ],
    
    // Configuração de Email
    'mail' => [
        'enabled' => false,
        'host' => 'smtp.example.com',
        'port' => 587,
        'secure' => 'tls',      // tls ou ssl
        'auth' => true,
        'username' => '',
        'password' => '',
        'fromName' => 'Your App',
        'fromEmail' => 'noreply@example.com',
        'debug' => 0           // 0 = off, 1 = client, 2 = client/server
    ],
    
    // Configuração de Banco de Dados
    'db' => [
        'type' => 'mysql',
        'host' => 'localhost',
        'database' => 'db_name',
        'username' => 'user',
        'password' => 'pass'
    ]
];
```

### Uso de Email

```php
// Enviar email
$app->mail()
    ->addAddress('recipient@example.com')
    ->setSubject('Email de Teste')
    ->setBody('Olá Mundo!')
    ->send();

// Com HTML
$app->mail()
    ->addAddress('recipient@example.com', 'John Doe')
    ->setSubject('Bem-vindo')
    ->isHTML(true)
    ->setBody('<h1>Bem-vindo!</h1><p>Seu cadastro está pronto.</p>')
    ->send();

// Com anexos
$app->mail()
    ->addAddress('recipient@example.com')
    ->setSubject('Documentos')
    ->addAttachment('/path/to/file.pdf', 'Documento.pdf')
    ->send();
```

### Suporte a CORS

CORS está desativado por padrão, mas pode ser facilmente ativado:

```php
// Ativar CORS com configurações padrão
$app = new Lapa\Lapa([
    'cors' => [
        'enabled' => true
    ]
]);

// Configuração personalizada de CORS
$app = new Lapa\Lapa([
    'cors' => [
        'enabled' => true,
        'origins' => 'https://myapp.com',
        'methods' => 'GET, POST',
        'headers' => 'X-Custom-Header',
        'credentials' => true
    ]
]);

// Sobrescrever por rota
$app->on('GET /api', function() {
    $this->cors('https://api.myapp.com', 'GET, POST');
    return ['dados' => 'Resposta da API'];
});
```

### Estrutura de Diretórios

```bash
lapa/
├── routes/
│   ├── web.php
│   └── api.php
├── storage/
│   ├── app/           # Armazenamento privado por padrão
│   ├── cache/
│   ├── logs/
│   └── uploads/       # Arquivos públicos
└── src/
    ├── Lapa.php
    └── helpers.php
```

### Views

#### Estrutura de Views
O Lapa Framework possui um sistema modular de views, incluindo suporte a partials (componentes reutilizáveis).

Estrutura recomendada:
```bash
views/
├── partials/          # Componentes reutilizáveis
│   ├── header.php     # Cabeçalho do site
│   ├── footer.php     # Rodapé do site
│   ├── sidebar.php    # Barra lateral
│   └── nav.php        # Menu de navegação
├── layouts/           # Layouts base
│   └── default.php    # Layout padrão
└── pages/            # Páginas do site
    ├── home.php
    └── about.php
```

#### Usando Partials
Os partials são pequenos componentes reutilizáveis que podem ser incluídos em qualquer view:

```php
<!-- views/layouts/default.php -->
<!DOCTYPE html>
<html>
<head>
    <title><?= $title ?></title>
</head>
<body>
    <?php $this->partial('header', ['title' => $title]) ?>
    
    <div class="container">
        <?php $this->partial('nav') ?>
        
        <main>
            <?= $content ?>
        </main>
        
        <?php $this->partial('sidebar', ['user' => $user]) ?>
    </div>
    
    <?php $this->partial('footer') ?>
</body>
</html>

<!-- views/pages/home.php -->
<?php $this->partial('header', ['title' => 'Home']) ?>

<div class="content">
    <h1>Bem-vindo!</h1>
    <p>Conteúdo da página...</p>
</div>

<?php $this->partial('footer') ?>
```

#### Passando Dados para Partials
Cada partial pode receber seus próprios dados:

```php
// Na rota
$app->view('pages/home', [
    'title' => 'Home Page',
    'user' => $user
]);

// No partial
$this->partial('header', [
    'title' => $title,
    'showMenu' => true
]);

$this->partial('sidebar', [
    'user' => $user,
    'menuItems' => $items
]);
```

#### Estrutura de Partials
Os partials devem estar na pasta `views/partials/` e podem ser organizados em subpastas:

```php
<!-- views/partials/header.php -->
<header>
    <h1><?= $title ?></h1>
    <?php if ($showMenu ?? false): ?>
        <?php $this->partial('nav/main') ?>
    <?php endif ?>
</header>

<!-- views/partials/nav/main.php -->
<nav>
    <ul>
        <li><a href="/">Home</a></li>
        <li><a href="/about">About</a></li>
    </ul>
</nav>
```

#### Características dos Partials
- Organização modular do código
- Reutilização de componentes
- Escopo isolado de variáveis
- Suporte a subdiretórios
- Passagem de dados específicos
- Aninhamento de partials

### Tratamento de Erros

```php
// Customizar tratamento de erros
$app = new Lapa\Lapa([
    'debug' => true  // Mostrar detalhes em desenvolvimento
]);

// Os erros são automaticamente capturados e exibidos com estilo
try {
    // código que pode gerar erro
} catch (\Exception $e) {
    // Erros 500+ mostram detalhes apenas em debug
    // Erros 400-499 mostram mensagem amigável
}
```

### Sistema de Layouts

```php
// Usar layout base
return $this->layout('pages/home', 'default', [
    'title' => 'Home Page'
]);

// Em views/layouts/default.php
<!DOCTYPE html>
<html>
<head>
    <title><?= $title ?></title>
</head>
<body>
    <?= $content // Conteúdo da view ?>
</body>
</html>
```

### Sistema de Plugins

```php
// Em plugins/Cache.php
class Cache {
    public function set($key, $value) {
        // ...
    }
}

// Uso
$app->cache->set('key', 'value');
```

### Mensagens Flash

```php
// Definir mensagem flash
$this->flash('success', 'Item salvo!');

// Obter e limpar mensagem
$message = $this->flash('success');
```

### Inicialização

Existem duas maneiras de inicializar o Lapa Framework:

1. Inicialização direta no index.php:
```php
$app = new Lapa\Lapa([
    'debug' => true,      // Ativar modo debug
    'secure' => false,    // Requisito de HTTPS
    'errors' => true,     // Mostrar erros detalhados
    'timezone' => 'UTC'   // Fuso horário padrão
], [
    // Configuração de banco de dados (opcional)
    'type' => 'mysql',
    'database' => 'my_database',
    'host' => 'localhost',
    'username' => 'root',
    'password' => ''
]);
```

2. Usando o arquivo config.php:
```php
// config.php
return [
    'debug' => true,
    'secure' => false,
    'errors' => true,
    'timezone' => 'UTC',
    
    'db' => [
        'type' => 'mysql',
        'database' => 'my_database',
        'host' => 'localhost',
        'username' => 'root',
        'password' => ''
    ]
];

// index.php
$app = new Lapa\Lapa();
```

O framework possui padrões sensíveis para todas as opções de configuração:
- debug: false
- secure: false
- errors: true
- timezone: 'UTC'
- upload.max_size: 5MB
- cache.ttl: 1 hora

### Requisitos

- PHP 7.4+
- Composer
- Extensões PHP:
  - JSON
  - PDO (opcional)
  - Fileinfo

## Changelog

### Versão 1.5.0
- Removida a complexidade do middleware em favor dos helpers
- Sistema de autenticação melhorado
- Adicionado método import() para integração com APIs externas
- Melhorado o tratamento de requisições/respostas
- Adicionada mais cobertura de testes
- Limpeza e otimização de código
- Documentação atualizada

## Licença

Licença MIT - veja o arquivo [LICENSE](LICENSE) para mais detalhes.

## Contribuindo

1. Faça um fork do projeto
2. Crie sua branch de feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## Autor

Daniel Medina - [@jdanielcmedina](https://twitter.com/jdanielcmedina)
