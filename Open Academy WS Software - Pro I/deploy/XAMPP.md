# Guia de Configuracao no XAMPP (Windows)

Este passo a passo mostra como executar o sistema usando o stack **XAMPP** (Apache + PHP + MySQL) em Windows. A ideia e usar o Apache do XAMPP como servidor web, o MySQL para o banco `sistemaead` e manter toda a estrutura original do projeto.

## 1. Pre-requisitos

1. Instale o XAMPP 8.2 ou superior (https://www.apachefriends.org/). Durante a instalacao deixe ativos **Apache** e **MySQL**.
2. Confirme que `mod_rewrite` esta habilitado. No arquivo `C:\xampp\apache\conf\httpd.conf` garanta que a linha abaixo NAO esteja comentada:
   ```
   LoadModule rewrite_module modules/mod_rewrite.so
   ```
   e que o bloco do `htdocs` permita `.htaccess`:
   ```
   <Directory "C:/xampp/htdocs">
       AllowOverride All
   </Directory>
   ```
3. Garanta que os servicos **Apache** e **MySQL** sobem sem erros no *XAMPP Control Panel*.

## 2. Copiar o projeto para `htdocs`

1. Escolha um nome de pasta (nos exemplos usamos `open-academy`).
2. Com o XAMPP parado, copie o conteudo do repositorio para `C:\xampp\htdocs\open-academy`. Exemplo (PowerShell como administrador):
   ```powershell
   Copy-Item "w:\SISTEMAS WS SOFTWARE\WS Open Academy\Open Academy WS Software - Pro I\*" `
     -Destination "C:\xampp\htdocs\open-academy" -Recurse -Force
   ```
3. Dentro de `C:\xampp\htdocs\open-academy`, confirme que existem as pastas `public/`, `includes/`, `database/` e o arquivo `config.php`. O `.htaccess` da raiz faz o redirecionamento automatico para `public/`.

## 3. Criar e configurar o `.env`

1. Copie o arquivo de exemplo:
   ```powershell
   cd C:\xampp\htdocs\open-academy
   copy .env.example .env
   ```
2. Edite o `.env` com os dados do MySQL do XAMPP (por padrao usuario `root`, senha em branco e porta `3306`):
   ```env
   APP_NAME=Training
   DB_DRIVER=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=sistemaead
   DB_USER=root
   DB_PASS=
   APP_BASE_URL=/open-academy      # se acessar por http://localhost/open-academy
   ```
3. Ajuste `DEFAULT_ADMIN_*` caso queira cadastrar outro administrador por padrao quando a aplicacao iniciar.

## 4. Provisionar o banco via phpMyAdmin

1. Inicie **Apache** e **MySQL** no painel do XAMPP.
2. Abra [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
3. Crie o banco `sistemaead` com charset `utf8mb4`.
4. Ainda no phpMyAdmin, selecione o banco criado -> aba **Importar** -> envie `database/schema.sql` -> clique em **Executar**.
5. Verifique se as tabelas (users, courses, etc.) foram geradas.

## 5. Ajustar permissoes de escrita

Em Windows normalmente o usuario atual ja possui escrita. Caso o upload de arquivos falhe, execute no PowerShell como administrador:

```powershell
icacls "C:\xampp\htdocs\open-academy\public\uploads" /grant "Users":(OI)(CI)M
icacls "C:\xampp\htdocs\open-academy\storage" /grant "Users":(OI)(CI)M
```

## 6. (Opcional) Configurar um VirtualHost amigavel

Para acessar via `http://ead.local`, inclua o trecho abaixo em `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    ServerName ead.local
    DocumentRoot "C:/xampp/htdocs/open-academy/public"

    <Directory "C:/xampp/htdocs/open-academy/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Depois edite `C:\Windows\System32\drivers\etc\hosts` (como administrador) e acrescente:

```
127.0.0.1    ead.local
```

Reinicie o Apache no painel do XAMPP e defina `APP_BASE_URL=http://ead.local` no `.env`.

## 7. Testar

1. Com Apache/MySQL rodando, abra `http://localhost/open-academy` (ou o dominio configurado). O `.htaccess` da raiz encaminha para `public/index.php`.
2. Acesse com `admin@ead.test / Senha@123`. No primeiro carregamento, se o usuario nao existir, o `config.php` cria o admin padrao usando os valores do `.env`.
3. Acesse `http://localhost/open-academy/test_connection.php` se precisar checar a comunicacao com o banco.

## 8. Checklist pos-instalacao

- `.env` apontando para `127.0.0.1`, usuario `root` e o banco `sistemaead`.
- `database/schema.sql` importado sem erros.
- Apache com `mod_rewrite` habilitado e `AllowOverride All`.
- Pastas `public/uploads` e `storage` com escrita liberada.
- Login funcionando e senha padrao alterada apos o primeiro acesso.

Seguindo estes passos o sistema roda integralmente dentro do stack XAMPP, sem ajustes adicionais de servidor.
