# Histórico de Alterações

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
e este projeto segue [Versionamento Semântico](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-01-10

### Adicionado
- Documentação automática de API via método `docs()`
- Templates HTML estilizados para erros e documentação
- Suporte a anotações `@api` para documentação automática
- Novo método `debug()` para tratamento unificado de erros

### Modificado
- Simplificado o tratamento de erros
- Otimizado o processo de inicialização
- Melhorada a estrutura de estilos CSS para erros e docs
- Removidas funções redundantes de renderização

### Removido
- Classe `Errors.php` (funcionalidade incorporada ao core)
- Método `isConfigured()` (responsabilidade movida para o Installer)
- Métodos redundantes de renderização de erro