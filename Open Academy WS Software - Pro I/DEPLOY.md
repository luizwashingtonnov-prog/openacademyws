# Guia de Deploy - Sistema EAD

Este guia contém instruções detalhadas para fazer o deploy da aplicação em diferentes ambientes.

## 📋 Pré-requisitos

- PHP 8.0 ou superior
- MySQL 5.7+ ou PostgreSQL 10+
- Servidor web (Apache ou Nginx)
- Extensões PHP necessárias:
  - `pdo_mysql` ou `pdo_pgsql`
  - `gd` ou `imagick` (para processamento de imagens)
  - `mbstring`
  - `fileinfo`
  - `json`
  - `session`

## 🚀 Deploy em Servidor Compartilhado (cPanel/Hostgator)

### Passo 1: Preparar os arquivos

1. Compacte todos os arquivos do projeto (exceto `.env` e `uploads/`)
2. Faça upload do arquivo ZIP via cPanel File Manager
3. Extraia o arquivo na pasta `public_html` ou subdiretório desejado

### Passo 2: Configurar o banco de dados

1. Acesse o MySQL Databases no cPanel
2. Crie um novo banco de dados (ex: `usuario_sistemaead`)
3. Crie um usuário e associe ao banco
4. Anote as credenciais (host, usuário, senha, nome do banco)

### Passo 3: Configurar o ambiente

1. Renomeie `.env.example` para `.env`
2. Edite o arquivo `.env` com as credenciais do banco:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=usuario_sistemaead
DB_USER=usuario_db
DB_PASS=senha_segura
APP_BASE_URL=/ead  # Se estiver em subdiretório
```

### Passo 4: Importar o banco de dados

1. Acesse phpMyAdmin no cPanel
2. Selecione o banco de dados criado
3. Vá em "Importar"
4. Selecione o arquivo `database/schema.sql`
5. Clique em "Executar"

### Passo 5: Configurar permissões

Via cPanel File Manager ou FTP, defina as permissões:

```
public/uploads/          → 755
public/uploads/pdfs/     → 755
public/uploads/videos/   → 755
public/uploads/signatures/ → 755
storage/                → 755
storage/signature_settings.json → 644
```

### Passo 6: Verificar configurações

1. Acesse o site e verifique se o login aparece
2. Tente fazer login com as credenciais padrão do `.env`
3. Verifique se os uploads funcionam

## 🌐 Deploy em VPS/Servidor Dedicado

### Opção 1: Apache

1. Clone ou faça upload dos arquivos para `/var/www/html/ead` (ou diretório desejado)
2. Configure o VirtualHost:

```apache
<VirtualHost *:80>
    ServerName ead.seudominio.com
    DocumentRoot /var/www/html/ead/public
    
    <Directory /var/www/html/ead/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/ead_error.log
    CustomLog ${APACHE_LOG_DIR}/ead_access.log combined
</VirtualHost>
```

3. Configure o `.env` conforme necessário
4. Importe o banco de dados
5. Configure permissões:

```bash
sudo chown -R www-data:www-data /var/www/html/ead
sudo chmod -R 755 /var/www/html/ead
sudo chmod -R 775 /var/www/html/ead/public/uploads
sudo chmod -R 775 /var/www/html/ead/storage
```

6. Habilite o site: `sudo a2ensite ead.conf`
7. Reinicie o Apache: `sudo systemctl restart apache2`

### Opção 2: Nginx

1. Faça upload dos arquivos para `/var/www/ead`
2. Configure o servidor:

```nginx
server {
    listen 80;
    server_name ead.seudominio.com;
    root /var/www/ead/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

3. Configure o `.env` e importe o banco
4. Configure permissões e reinicie o Nginx

## 🔒 Configurações de Segurança

### Após o deploy, verifique:

1. ✅ Arquivo `.env` não está acessível publicamente
2. ✅ Permissões de arquivos estão corretas
3. ✅ Senhas do banco são fortes
4. ✅ SSL/HTTPS está configurado (recomendado)
5. ✅ Backups regulares estão configurados

### Configurar HTTPS (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d ead.seudominio.com
```

## 📦 Checklist de Deploy

- [ ] Arquivos enviados para o servidor
- [ ] Arquivo `.env` configurado
- [ ] Banco de dados criado e importado
- [ ] Permissões de pastas configuradas
- [ ] `.htaccess` funcionando corretamente
- [ ] Login funcionando
- [ ] Upload de arquivos funcionando
- [ ] SSL/HTTPS configurado (recomendado)
- [ ] Backups configurados
- [ ] Testes de funcionalidades realizados

## 🐛 Troubleshooting

### Erro: "Falha ao conectar ao banco de dados"
- Verifique as credenciais no `.env`
- Verifique se o host está correto (pode ser `localhost` ou IP)
- Verifique se o usuário tem permissões no banco

### Erro: "Permission denied" em uploads
- Verifique permissões: `chmod -R 775 public/uploads`
- Verifique o proprietário: `chown -R www-data:www-data public/uploads`

### Erro: "Token de segurança inválido"
- Verifique se as sessões estão funcionando
- Limpe o cache do navegador
- Verifique permissões na pasta de sessões do PHP

### Página em branco
- Ative exibição de erros no `.env` (apenas em desenvolvimento)
- Verifique logs de erro do PHP
- Verifique se todas as extensões PHP estão instaladas

## 📞 Suporte

Para problemas específicos, verifique:
- Logs do servidor (`/var/log/apache2/` ou `/var/log/nginx/`)
- Logs do PHP
- Logs de erro do banco de dados

