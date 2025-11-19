# Instalação Rápida - Sistema EAD
> Para rodar no XAMPP/Windows siga tambem o guia `deploy/XAMPP.md`, que detalha como copiar o projeto para `C:\xampp\htdocs`, configurar o `.env`, importar `database/schema.sql` via phpMyAdmin e liberar escrita em `public\uploads` e `storage`.

## 🚀 Deploy Rápido (5 minutos)

### 1. Preparar o ambiente

```bash
# Copiar arquivo de exemplo
cp .env.example .env

# Editar o arquivo .env com suas credenciais
# DB_HOST, DB_NAME, DB_USER, DB_PASS
```

### 2. Configurar banco de dados

```sql
-- Criar banco de dados
CREATE DATABASE sistemaead CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Importar estrutura
-- Execute o arquivo: database/schema.sql
```

### 3. Configurar permissões

```bash
# Linux/Mac
chmod -R 755 public/uploads
chmod -R 755 storage

# Windows (via PowerShell como Administrador)
icacls "public\uploads" /grant "IIS_IUSRS:(OI)(CI)F"
icacls "storage" /grant "IIS_IUSRS:(OI)(CI)F"
```

### 4. Testar localmente

```bash
# Iniciar servidor PHP
php -S localhost:8000 -t public

# Acessar no navegador
# http://localhost:8000
```

### 5. Credenciais padrão

Após a primeira execução, o sistema cria automaticamente um administrador:

- **Email**: `admin@ead.test`
- **Senha**: `Senha@123`

⚠️ **IMPORTANTE**: Altere essas credenciais após o primeiro acesso!

## 📦 Deploy em Produção

### Servidor Compartilhado (cPanel/Hostgator)

1. **Upload dos arquivos**
   - Compacte todos os arquivos (exceto `.env` e `public/uploads/*`)
   - Faça upload via cPanel File Manager
   - Extraia na pasta `public_html` ou subdiretório

2. **Configurar banco de dados**
   - Crie o banco via MySQL Databases
   - Importe `database/schema.sql` via phpMyAdmin
   - Configure as credenciais no `.env`

3. **Configurar `.env`**
   ```env
   DB_HOST=localhost
   DB_NAME=usuario_sistemaead
   DB_USER=usuario_db
   DB_PASS=sua_senha
   APP_BASE_URL=/ead  # Se estiver em subdiretório
   ```

4. **Permissões**
   - `public/uploads/` → 755
   - `storage/` → 755

### VPS/Servidor Dedicado

Consulte o arquivo `DEPLOY.md` para instruções detalhadas.

## ✅ Checklist Pós-Deploy

- [ ] Arquivo `.env` configurado
- [ ] Banco de dados importado
- [ ] Permissões de pastas corretas
- [ ] Login funcionando
- [ ] Upload de arquivos funcionando
- [ ] SSL/HTTPS configurado (recomendado)
- [ ] Credenciais padrão alteradas

## 🆘 Problemas Comuns

### Erro de conexão com banco
- Verifique credenciais no `.env`
- Verifique se o host está correto (`localhost` ou IP)

### Erro de permissão em uploads
- Verifique permissões: `chmod -R 775 public/uploads`
- Verifique proprietário: `chown -R www-data:www-data public/uploads`

### Página em branco
- Verifique logs de erro do PHP
- Verifique se todas as extensões estão instaladas
- Verifique se o `.htaccess` está funcionando

## 📚 Documentação Completa

Para mais detalhes, consulte:
- `DEPLOY.md` - Guia completo de deploy
- `README.md` - Documentação geral do projeto

