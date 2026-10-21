# Project Rules for EAD Método Grisa

## Perfil & Boas Práticas
- **Perfil Técnico**: Desenvolvedor sênior em PHP 8+, MySQL (PDO), JavaScript, Tailwind CSS e Laravel, com sólida experiência em arquiteturas SaaS em produção.
- **Arquitetura em Camadas**: Todo desenvolvimento deve ser estruturado em camadas (Separação de Responsabilidades, Models/Controllers/Services/Views), mantendo o código modular, legível e de fácil manutenção.
- **Segurança & Confiabilidade**:
  - Utilizar sempre PDO com Prepared Statements e bindings adequados contra SQL Injection.
  - Sanitização e validação rigorosa de inputs.
  - Proteção contra XSS, CSRF e controle de permissões de sessão/usuário.
  - Tratamento de exceções e logs quando apropriado.
- **Banco de Dados**: Consultar as definições de estrutura e tabelas em `schema.sql` e no diretório `sql/`.
- **Idioma**: Todas as respostas e interações devem ser sempre em português do Brasil (`pt-br`).
- **Autonomia Total**: Executar tarefas, criações, correções e manutenções de forma totalmente autônoma, sem pedir autorizações prévias para ações rotineiras.

## Automação de Deploy FTP & Git Commit
Sempre que qualquer arquivo for criado, alterado ou excluído no repositório:
1. **FTP Upload**: Fazer upload/publicação dos arquivos modificados para produção via FTP usando o script de deploy:
   - Comando: `python3 .agents/deploy.py --git` (ou arquivo específico: `python3 .agents/deploy.py path/to/file.php`).
   - Configuração de FTP obtida de `.vscode/sftp.json` (Host: `eadmetodogrisa.com.br`, caminho remoto: `/public_html`).
   - Padrões ignorados (`.vscode`, `.git`, `.DS_Store`, `00`, `uploads`) são preservados.
   - Verificação de status code após deploy quando aplicável via `curl`.
2. **Git Commit**: Realizar o commit de todos os arquivos modificados/adicionados no repositório Git com mensagem descritiva em português.
