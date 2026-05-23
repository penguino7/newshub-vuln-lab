# NewsHub Vulnerable Lab

NewsHub là một lab PHP/MySQL cố ý chứa lỗ hổng để thực hành kiểm thử bảo mật web. Lab tập trung vào:

- SQL Injection
- Reflected XSS
- Stored XSS
- DOM-based XSS
- SPA/API-driven rendering

Chỉ chạy lab trong môi trường local hoặc mạng lab riêng. Không public ra Internet.

## Cấu trúc dự án

```text
.
├── Dockerfile
├── docker-compose.yml
├── apache-newshub.conf
├── db/
│   └── init.sql
├── www/
│   ├── index.php
│   ├── search.php
│   ├── news.php
│   ├── category.php
│   ├── user/
│   ├── admin/
│   ├── comment/
│   ├── api/
│   │   ├── suggest.php
│   │   ├── track.php
│   │   └── spa/
│   ├── spa/
│   │   ├── index.html
│   │   ├── app.js
│   │   ├── style.css
│   │   └── .htaccess
│   └── static/
└── XSS_SQLI_TESTING_GUIDE.md
```

Các thành phần chính:

- `www/`: mã nguồn web, được serve làm document root.
- `db/init.sql`: tạo database, bảng, dữ liệu mẫu và fake secret cho bài SQLi.
- `www/config/db.php`: cấu hình kết nối database.
- `www/spa/`: SPA lab dùng JavaScript `fetch()` để gọi API JSON.
- `www/api/spa/`: API JSON cho SPA, cố ý chứa SQLi/XSS.

Thông tin database mặc định:

```text
DB_HOST=db
DB_NAME=newshub
DB_USER=webuser
DB_PASS=webpass123
```

## Tài khoản mẫu

```text
admin / admin123
editor1 / editor123
thinhnv / password123
minhlt / password
huongnt / 12345678
```

## Chạy lab bằng Docker trên Kali

Đây là cách khuyến nghị.

### 1. Cài Docker

Trên Kali:

```bash
sudo apt update
sudo apt install -y docker.io docker-compose unzip
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"
newgrp docker
```

Kiểm tra:

```bash
docker --version
docker compose version || docker-compose version
```

### 2. Chạy lab

Vào thư mục dự án:

```bash
cd ~/labs/Vuln-lab
```

Nếu máy dùng `docker compose`:

```bash
docker compose up -d --build
```

Nếu Kali chỉ có `docker-compose`:

```bash
docker-compose up -d --build
```

Kiểm tra container:

```bash
docker compose ps || docker-compose ps
```

Xem log:

```bash
docker compose logs -f web || docker-compose logs -f web
docker compose logs -f db || docker-compose logs -f db
```

Mở lab:

```text
http://127.0.0.1:12001/
```

Nếu truy cập từ máy host vào Kali VM:

```text
http://IP_CUA_KALI:12001/
```

## Các URL chính

Classic PHP app:

```text
http://127.0.0.1:12001/
http://127.0.0.1:12001/search.php
http://127.0.0.1:12001/news.php?id=1
http://127.0.0.1:12001/category.php?id=1
http://127.0.0.1:12001/user/login.php
http://127.0.0.1:12001/admin/dashboard.php
```

SPA lab:

```text
http://127.0.0.1:12001/spa/search
http://127.0.0.1:12001/spa/article/1
http://127.0.0.1:12001/spa/comments/1
http://127.0.0.1:12001/spa/logs
```

Static pages:

```text
http://127.0.0.1:12001/static/about.html
http://127.0.0.1:12001/static/contact.html
http://127.0.0.1:12001/static/faq.html
```

## SPA Lab

SPA nằm tại:

```text
/spa/search
```

SPA này dùng History API, không dùng route dạng `#/`. Các route như `/spa/article/1` hoặc `/spa/comments/1` được rewrite về `www/spa/index.html` bằng file:

```text
www/spa/.htaccess
```

Các route SPA:

```text
/spa/search
/spa/article/1
/spa/comments/1
/spa/logs
```

Các API JSON SPA:

```text
/api/spa/search.php?q=...
/api/spa/news.php?id=...
/api/spa/comments.php?news_id=...
/api/spa/comment_add.php
/api/spa/logs.php?keyword=...
```

Ý tưởng của SPA lab:

- HTML ban đầu không chứa dữ liệu chính.
- JavaScript gọi API bằng `fetch()`.
- API trả JSON.
- Frontend render JSON vào DOM bằng `innerHTML`.
- Từ đó có thể kiểm thử DOM XSS, Stored XSS qua JSON và SQLi trên API.

## Các nhóm lỗ hổng chính

### SQL Injection

Endpoint tiêu biểu:

```text
/search.php?q=...
/news.php?id=...
/category.php?id=...
/user/login.php
/user/profile.php?user=...
/api/track.php?id=...&ref=...
/admin/dashboard.php?filter_cat=...
/admin/users.php?search_user=...
/admin/news_manage.php?edit_id=...
/api/spa/search.php?q=...
/api/spa/news.php?id=...
/api/spa/comments.php?news_id=...
/api/spa/logs.php?keyword=...
```

Các dạng có trong lab:

- Error-based SQLi
- UNION-based SQLi
- Boolean-based blind SQLi
- Time-based blind SQLi
- SQLi authentication bypass
- SQLi trong JSON API

### XSS

Endpoint/route tiêu biểu:

```text
/search.php?q=...
/comment/add.php
/user/register.php
/user/profile.php
/admin/dashboard.php
/admin/users.php
/api/suggest.php?q=...
/spa/search
/spa/comments/1
/spa/logs
```

Các dạng có trong lab:

- Reflected XSS
- Stored XSS
- DOM-based XSS
- XSS qua JSON API render bằng `innerHTML`

Chi tiết payload, tham số và vị trí sink nằm trong:

```text
XSS_SQLI_TESTING_GUIDE.md
```

## Reset lab về trạng thái ban đầu

Trong quá trình test XSS/SQLi, lab có thể bị lưu nhiều dữ liệu rác như comment chứa payload, search log, page view log hoặc dữ liệu do scanner gửi vào. Nếu muốn đưa lab về trạng thái sạch như lúc mới chạy lần đầu, hãy reset database Docker volume.

### Cách khuyến nghị: reset toàn bộ database

Chạy trong thư mục có file `docker-compose.yml`:

```bash
cd ~/labs/Vuln-lab
docker compose down -v
docker compose up -d --build
```

Nếu máy dùng lệnh cũ `docker-compose`:

```bash
cd ~/labs/Vuln-lab
docker-compose down -v
docker-compose up -d --build
```

Sau đó kiểm tra lại:

```bash
docker compose ps || docker-compose ps
```

Mở lại lab:

```text
http://127.0.0.1:12001/
```

Lưu ý quan trọng:

- `docker compose restart` chỉ khởi động lại container, không xóa dữ liệu cũ.
- `docker compose down` chỉ dừng và xóa container/network, không xóa database volume.
- `docker compose down -v` mới xóa volume `db_data`, vì vậy database sẽ được tạo lại từ `db/init.sql`.
- Không dùng cách này nếu bạn đang muốn giữ lại dữ liệu test để phân tích.

### Chỉ xóa dữ liệu test XSS trong comments

Nếu chỉ muốn xóa các comment payload mà không reset toàn bộ database:

```bash
docker exec -it newshub-db mariadb -uroot -prootpass123 newshub \
  -e "DELETE FROM comments WHERE content LIKE '%FUZZ%' OR author_name LIKE '%FUZZ%' OR content LIKE '%<script%';"
```

### Xóa thêm log sinh ra khi test

Nếu muốn dọn cả search log và page view log:

```bash
docker exec -it newshub-db mariadb -uroot -prootpass123 newshub \
  -e "TRUNCATE TABLE search_logs; TRUNCATE TABLE page_views;"
```

### Kiểm tra số lượng dữ liệu sau khi dọn

```bash
docker exec -it newshub-db mariadb -uroot -prootpass123 newshub \
  -e "SELECT COUNT(*) AS comments FROM comments; SELECT COUNT(*) AS search_logs FROM search_logs; SELECT COUNT(*) AS page_views FROM page_views;"
```

## Dừng lab

```bash
docker compose down
```

Hoặc:

```bash
docker-compose down
```

## Chạy trực tiếp bằng Apache/PHP/MariaDB trên Kali

Nếu không dùng Docker:

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server php libapache2-mod-php php-mysql php-mbstring rsync
sudo systemctl enable --now apache2 mariadb
```

Tạo database:

```bash
cd ~/labs/Vuln-lab

sudo mariadb <<'SQL'
CREATE DATABASE IF NOT EXISTS newshub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'webuser'@'localhost' IDENTIFIED BY 'webpass123';
GRANT ALL PRIVILEGES ON newshub.* TO 'webuser'@'localhost';
FLUSH PRIVILEGES;
SQL

sudo mariadb < db/init.sql
```

Copy source:

```bash
sudo mkdir -p /var/www/newshub
sudo rsync -av --delete www/ /var/www/newshub/
sudo chown -R www-data:www-data /var/www/newshub
```

Tạo VirtualHost port `12001`:

```bash
grep -q '^Listen 12001$' /etc/apache2/ports.conf || echo 'Listen 12001' | sudo tee -a /etc/apache2/ports.conf

sudo tee /etc/apache2/sites-available/newshub.conf >/dev/null <<'EOF'
<VirtualHost *:12001>
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

Enable site:

```bash
sudo a2enmod rewrite
sudo a2ensite newshub.conf
sudo systemctl reload apache2
```

Mở:

```text
http://127.0.0.1:12001/
```

## Troubleshooting

### Lỗi `Connection failed`

Kiểm tra container:

```bash
docker compose ps || docker-compose ps
docker compose logs db || docker-compose logs db
```

Kiểm tra thông tin DB trong:

```text
www/config/db.php
docker-compose.yml
```

### Reload `/spa/article/1` bị 404

Cần đảm bảo Docker image đã được rebuild sau khi thêm `apache-newshub.conf` và `.htaccess`:

```bash
docker compose up -d --build
```

Hoặc:

```bash
docker-compose up -d --build
```

Nếu chạy Apache trực tiếp, cần bật rewrite:

```bash
sudo a2enmod rewrite
sudo systemctl reload apache2
```

### Thiếu `mb_substr()`

Cài extension:

```bash
sudo apt install -y php-mbstring
sudo systemctl reload apache2
```

Với Docker:

```bash
docker compose build --no-cache web
docker compose up -d
```

## Ghi chú

Một số link như `/rss.php` và `/sitemap.php` được giao diện tham chiếu nhưng hiện chưa có file tương ứng. Đây không phải lỗi triển khai chính của lab.

Nội dung tiếng Việt trong seed data có một số đoạn bị lỗi encoding từ dữ liệu ban đầu. Điều này không ảnh hưởng đến mục tiêu kiểm thử XSS/SQLi.
