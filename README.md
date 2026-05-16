# NewsHub Vulnerable Lab

NewsHub la mot lab PHP/MySQL co y de lai cac loi SQL injection, XSS va auth bypass de thuc hanh trong moi truong local. Khong expose lab nay ra Internet hoac mang noi bo khong tin cay.

## Tong quan source

- `www/`: source PHP duoc serve lam document root.
- `www/config/db.php`: cau hinh ket noi DB qua bien moi truong, mac dinh `DB_HOST=db`, `DB_NAME=newshub`, `DB_USER=webuser`, `DB_PASS=webpass123`.
- `db/init.sql`: tao database, bang, du lieu mau, tai khoan mau va bang `secret_configs` cho bai SQLi.
- `Dockerfile`, `docker-compose.yml`: phuong an chay nhanh tren Kali bang Docker.

Tai khoan mau:

- `admin` / `admin123`
- `editor1` / `editor123`
- `thinhnv` / `password123`
- `minhlt` / `password`
- `huongnt` / `12345678`

Mot so link trong UI nhu `/rss.php` va `/sitemap.php` dang duoc tham chieu nhung source hien tai chua co file tuong ung. `www/comment/list.php` cung dang rong. Cac diem nay khong can cho viec chay lab chinh.

## Cach 1: Chay bang Docker Compose tren Kali

Day la cach nen dung vi source mac dinh da tro `DB_HOST=db`, dung dung ten service trong Compose.

### 1. Cai Docker tren Kali

```bash
sudo apt update
sudo apt install -y docker.io docker-compose unzip
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"
newgrp docker
```

Kiem tra:

```bash
docker --version
docker compose version || docker-compose version
```

### 2. Dua source vao Kali

Vi du dat lab o `~/labs/newshub`:

```bash
mkdir -p ~/labs
cd ~/labs
# copy hoac git clone project vao thu muc newshub
cd newshub
```

Thu muc phai co dang:

```text
newshub/
  Dockerfile
  docker-compose.yml
  db/init.sql
  www/index.php
```

### 3. Build va chay lab

```bash
docker compose up -d --build
# Neu lenh tren khong co tren Kali, dung:
docker-compose up -d --build
```

Xem trang thai:

```bash
docker compose ps
docker compose logs -f web
docker compose logs -f db
# Hoac voi docker-compose:
docker-compose ps
docker-compose logs -f web
docker-compose logs -f db
```

Mo trinh duyet tren Kali:

```text
http://127.0.0.1:8080/
```

Neu port `8080` bi trung, sua dong sau trong `docker-compose.yml`:

```yaml
ports:
  - "8081:80"
```

Sau do chay lai:

```bash
docker compose up -d
# Hoac:
docker-compose up -d
```

Va truy cap:

```text
http://127.0.0.1:8081/
```

### 4. Truy cap database trong container

```bash
docker compose exec db mariadb -uwebuser -pwebpass123 newshub
# Hoac:
docker-compose exec db mariadb -uwebuser -pwebpass123 newshub
```

Mot so lenh kiem tra nhanh:

```sql
SHOW TABLES;
SELECT username, role FROM users;
SELECT config_key, config_value FROM secret_configs;
```

### 5. Reset lab ve trang thai ban dau

Lenh nay xoa container va volume database, sau do import lai `db/init.sql` tu dau:

```bash
docker compose down -v
docker compose up -d --build
# Hoac:
docker-compose down -v
docker-compose up -d --build
```

### 6. Dung lab

```bash
docker compose down
# Hoac:
docker-compose down
```

## Cach 2: Chay truc tiep bang Apache, PHP va MariaDB tren Kali

Dung cach nay neu ban khong muon Docker. Can set `DB_HOST=localhost` trong Apache vi source mac dinh tim host `db`.

### 1. Cai package can thiet

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server php libapache2-mod-php php-mysql php-mbstring
sudo systemctl enable --now apache2 mariadb
```

Kiem tra module PHP:

```bash
php -m | grep -E 'mysqli|pdo_mysql|mbstring'
```

### 2. Tao database va user

Chay tu thu muc root cua project:

```bash
sudo mariadb <<'SQL'
CREATE DATABASE IF NOT EXISTS newshub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'webuser'@'localhost' IDENTIFIED BY 'webpass123';
GRANT ALL PRIVILEGES ON newshub.* TO 'webuser'@'localhost';
FLUSH PRIVILEGES;
SQL

sudo mariadb < db/init.sql
```

Kiem tra:

```bash
mariadb -uwebuser -pwebpass123 newshub -e "SELECT username, role FROM users;"
```

### 3. Copy source vao Apache document root rieng

```bash
sudo mkdir -p /var/www/newshub
sudo rsync -av --delete www/ /var/www/newshub/
sudo chown -R www-data:www-data /var/www/newshub
```

Neu Kali chua co `rsync`:

```bash
sudo apt install -y rsync
```

### 4. Tao VirtualHost o port 8080

Them `Listen 8080` neu chua co:

```bash
grep -q '^Listen 8080$' /etc/apache2/ports.conf || echo 'Listen 8080' | sudo tee -a /etc/apache2/ports.conf
```

Tao file site:

```bash
sudo tee /etc/apache2/sites-available/newshub.conf >/dev/null <<'EOF'
<VirtualHost *:8080>
    ServerName newshub.local
    DocumentRoot /var/www/newshub

    SetEnv DB_HOST localhost
    SetEnv DB_NAME newshub
    SetEnv DB_USER webuser
    SetEnv DB_PASS webpass123

    <Directory /var/www/newshub>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/newshub_error.log
    CustomLog ${APACHE_LOG_DIR}/newshub_access.log combined
</VirtualHost>
EOF
```

Enable site va reload Apache:

```bash
sudo a2enmod rewrite
sudo a2ensite newshub.conf
sudo systemctl reload apache2
```

Truy cap:

```text
http://127.0.0.1:8080/
```

### 5. Cap nhat source sau khi sua code

Moi lan sua source trong project, copy lai:

```bash
sudo rsync -av --delete www/ /var/www/newshub/
sudo chown -R www-data:www-data /var/www/newshub
sudo systemctl reload apache2
```

### 6. Reset database

```bash
sudo mariadb -e "DROP DATABASE IF EXISTS newshub;"
sudo mariadb < db/init.sql
```

Neu bi mat quyen cho `webuser`, cap lai:

```bash
sudo mariadb <<'SQL'
GRANT ALL PRIVILEGES ON newshub.* TO 'webuser'@'localhost';
FLUSH PRIVILEGES;
SQL
```

## Cach 3: PHP built-in server cho test nhanh

Phuong an nay khong can Apache, nhung van can MariaDB da tao o Cach 2.

```bash
cd /duong/dan/toi/project
DB_HOST=localhost DB_NAME=newshub DB_USER=webuser DB_PASS=webpass123 php -S 127.0.0.1:8080 -t www
```

Truy cap:

```text
http://127.0.0.1:8080/
```

Dung bang `Ctrl+C`.

## Cac endpoint lab chinh

- `/search.php?q=...`: SQLi error-based, reflected XSS, log keyword cho stored XSS trong admin.
- `/news.php?id=...`: SQLi UNION-based, comment stored XSS.
- `/category.php?id=...`: SQLi boolean-based blind.
- `/api/track.php?...`: SQLi time-based blind qua tracking pixel.
- `/api/suggest.php?q=...`: API goi y, du lieu duoc render bang `innerHTML` trong `search.php`.
- `/user/login.php`: SQLi authentication bypass.
- `/user/register.php` va `/user/profile.php`: stored XSS trong `bio`.
- `/admin/dashboard.php`: SQLi UNION-based qua `filter_cat`, stored XSS tu search logs.
- `/admin/users.php`: SQLi boolean-based blind qua `search_user`.
- `/admin/news_manage.php`: SQLi qua `edit_id`, stored XSS qua `tags`.

## SPA Lab

SPA lab moi nam tai:

```text
http://127.0.0.1:8080/spa/
```

Day la mot frontend vanilla JavaScript dung History API router va goi API JSON bang `fetch()`. HTML ban dau hau nhu khong co du lieu bai viet; du lieu duoc lay tu API roi render tren browser.

File moi:

```text
www/spa/index.html
www/spa/app.js
www/spa/style.css
www/api/spa/_bootstrap.php
www/api/spa/search.php
www/api/spa/news.php
www/api/spa/comments.php
www/api/spa/comment_add.php
www/api/spa/logs.php
```

Route SPA:

- `/spa/search`: tim bai viet qua API JSON.
- `/spa/article/1`: xem bai viet qua API JSON.
- `/spa/comments/1`: xem va them comment qua API.
- `/spa/logs`: xem search logs qua API.

SPA dung History API, khong dung hash route. File `www/spa/.htaccess` rewrite cac route `/spa/*` ve `/spa/index.html`, nen refresh truc tiep `/spa/article/1` van hoat dong khi Apache da bat `mod_rewrite` va `AllowOverride All`.

API SPA:

- `/api/spa/search.php?q=...`: SQLi trong `q`, tra JSON, keyword duoc luu vao `search_logs`.
- `/api/spa/news.php?id=...`: SQLi UNION/error-based trong `id`.
- `/api/spa/comments.php?news_id=...`: SQLi trong `news_id`, tra comment raw.
- `/api/spa/comment_add.php`: POST comment raw, tao stored XSS.
- `/api/spa/logs.php?keyword=...`: SQLi trong `keyword`, tra search logs raw.

Payload test nhanh cho SPA:

```text
/spa/search?q=<img src=x onerror=alert(1)>
/spa/comments/1
```

Them comment trong SPA voi noi dung:

```html
<img src=x onerror=alert(1)>
```

Sau do reload `/spa/comments/1` de thay stored XSS.

Test SQLi API JSON:

```text
/api/spa/search.php?q=' OR 1=1--%20
/api/spa/news.php?id=1 UNION SELECT 1,2,3,4,5,6,7,8,9,10,11--%20
/api/spa/comments.php?news_id=1 OR 1=1
/api/spa/logs.php?keyword=' OR 1=1--%20
```

Khi kiem thu bang Burp/ZAP, hay bat traffic trong tab Network vi SPA se goi cac API tren sau khi trang `/spa/` da load.

## Troubleshooting

### Trang bao `Connection failed`

Voi Docker:

```bash
docker compose ps
docker compose logs db
```

Voi Apache bare-metal, kiem tra bien moi truong trong VirtualHost va MariaDB:

```bash
sudo systemctl status mariadb --no-pager
mariadb -uwebuser -pwebpass123 newshub -e "SHOW TABLES;"
```

### Loi `Call to undefined function mb_substr()`

Thieu extension `mbstring`:

```bash
sudo apt install -y php-mbstring
sudo systemctl reload apache2
```

Voi Docker, build lai:

```bash
docker compose build --no-cache web
docker compose up -d
```

### Docker DB khong import lai du lieu

MariaDB chi chay file trong `/docker-entrypoint-initdb.d/` khi volume database rong. Reset volume:

```bash
docker compose down -v
docker compose up -d --build
```

### Chu tieng Viet hien thi loi font

Source va seed data hien tai co noi dung tieng Viet bi ma hoa khong dong nhat o mot so file. Viec nay khong chan lab chay, nhung neu can hien thi dep thi can chuan hoa lai encoding UTF-8 cho source va `db/init.sql`.
