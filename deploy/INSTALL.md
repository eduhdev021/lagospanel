# LagosPanel — Guia de Instalação em Produção

Pacote: `lagospanel-files.zip` (WordPress + tema + plugin) · `db.sql.gz` (banco) · `replace-url.php` · `clean-demo.php`

## Requisitos

- Ubuntu/Debian com **PHP 8.1+** (extensões: `mysqlnd curl mbstring xml gd zip`), **MariaDB/MySQL 8**, **Nginx + PHP-FPM** (ou Apache) e `certbot`
- Um domínio apontando para o servidor (registro A → IP)

---

## 1. Banco de dados

```bash
mysql -u root -e "
CREATE DATABASE lagospanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lagos'@'localhost' IDENTIFIED BY 'UMA_SENHA_FORTE';
GRANT ALL PRIVILEGES ON lagospanel.* TO 'lagos'@'localhost';
FLUSH PRIVILEGES;"
```

## 2. Arquivos

```bash
mkdir -p /var/www/lagospanel
unzip lagospanel-files.zip -d /var/www/lagospanel
cd /var/www/lagospanel
```

## 3. wp-config.php

Edite `/var/www/lagospanel/wp-config.php`:

```php
define('DB_NAME', 'lagospanel');
define('DB_USER', 'lagos');
define('DB_PASSWORD', 'UMA_SENHA_FORTE');
define('DB_HOST', 'localhost');

define('WP_DEBUG', false);           // era true na demo
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
define('DISALLOW_FILE_EDIT', true);  // já vem — mantenha
define('DISABLE_WP_CRON', true);     // NOVO: cron via sistema (passo 7)
```

Gere salts novos e substitua os blocos existentes:
```bash
curl -s https://api.wordpress.org/secret-key/1.1/salt/
```

## 4. Importar o banco

```bash
zcat db.sql.gz | mysql -u root lagospanel
```

## 5. Trocar a URL (serializada com segurança)

> Bom saber: o `wp-config.php` do LagosPanel detecta o domínio automaticamente
> (`WP_HOME`/`WP_SITEURL` seguem o host acessado, com suporte a HTTPS e proxy) —
> então o site já responde no seu domínio. O passo abaixo corrige os URLs gravados
> NO CONTEÚDO do banco (GUIDs de posts, metadados serializados etc.).

```bash
cp ../replace-url.php /var/www/lagospanel/
cd /var/www/lagospanel
php replace-url.php http://localhost:8080 https://SEUDOMINIO.com.br
rm replace-url.php
```

## 6. Permissões e Nginx

```bash
chown -R www-data:www-data /var/www/lagospanel
find /var/www/lagospanel -type d -exec chmod 755 {} \;
find /var/www/lagospanel -type f -exec chmod 644 {} \;
```

`/etc/nginx/sites-available/lagospanel`:

```nginx
server {
    listen 80;
    server_name SEUDOMINIO.com.br;
    root /var/www/lagospanel;
    index index.php;

    client_max_body_size 64M;

    location / { try_files $uri $uri/ /index.php?$args; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
    location ~* \.(js|css|png|jpg|jpeg|svg|woff2?)$ { expires 30d; }
    location = /xmlrpc.php { deny all; }
}
```

```bash
ln -s /etc/nginx/sites-available/lagospanel /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

## 7. Cron do sistema (renovações e suspensões automáticas)

```bash
crontab -e
# adicione:
0 3 * * * curl -s -o /dev/null https://SEUDOMINIO.com.br/wp-cron.php
```

## 8. HTTPS

```bash
apt install certbot python3-certbot-nginx
certbot --nginx -d SEUDOMINIO.com.br
```

## 9. Primeiro acesso e configuração

1. Entre em `https://SEUDOMINIO.com.br/wp-login.php` — **usuário `eduardo`, senha `LagosPanel#2026`**
2. **Troque a senha imediatamente** (Usuários → Perfil)
3. LagosPanel → Configurações: SMTP real (host, porta, senha) + teste de envio
4. LagosPanel → Pagamentos: cole as credenciais dos gateways escolhidos e **desligue o modo teste**
5. **Recadastre os webhooks** nos provedores — as URLs agora apontam para o seu domínio (copie da config de cada gateway)
6. Opcional — remover dados de demonstração (faturas/tickets/conta demo):
   ```bash
   cp ../clean-demo.php /var/www/lagospanel/ && cd /var/www/lagospanel
   php clean-demo.php          # conferir
   php clean-demo.php --yes    # executar
   ```
7. Revise o conteúdo de **Termos de Uso** e **Política de Privacidade** com seu advogado (são modelos)

## 10. Backups (diário)

```bash
# crontab:
30 2 * * * mysqldump -u root lagospanel | gzip > /root/backups/lagospanel-$(date +\%F).sql.gz
```

## Verificação final

| O quê | Esperado |
|---|---|
| `https://SEUDOMINIO.com.br/` | Landing roxa Lagos |
| Cadastro de conta | Checkbox de aceite dos Termos (LGPD) obrigatório |
| Loja → contratar → fatura | Checkout com gateways (sem "Pix demonstração" — já está em modo produção) |
| Ticket → fechar → avaliar | Estrelas 1–5 |
| `https://SEUDOMINIO.com.br/status/` | Status da rede |
| WP-Cron no crontab | Faturas de renovação 5 dias antes + suspensão 3 dias após vencer |

**Dúvidas:** todo o código está em `wp-content/plugins/lagos-core/` e `wp-content/themes/lagospanel/`.
