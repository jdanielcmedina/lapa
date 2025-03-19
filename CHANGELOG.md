# Histórico de Alterações

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
e este projeto segue [Versionamento Semântico](https://semver.org/spec/v2.0.0.html).

## [Não Lançado]

### Adicionado
- Configuração inicial do projeto

### Modificado

### Obsoleto

### Removido

### Corrigido

### Segurança

## [1.0.0] - 2024-01-XX

### Adicionado
- Documentação automática de API via método `docs()`
- Templates HTML estilizados para erros e documentação

### Modificado
- Simplificado o tratamento de erros com método `debug()`
- Removidas funções redundantes de renderização de erros
- Otimizado o processo de inicialização no construtor
- Removido método `isConfigured()` obsoleto

### Removido
- Classe `Errors.php` (funcionalidade incorporada ao core)
- Métodos redundantes de renderização de erro