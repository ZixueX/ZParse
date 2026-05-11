# ZParse - 短视频无水印解析工具 (PHP)

ZParse 是一款纯 PHP 实现的多平台短视频无水印解析工具，支持 26+ 主流平台的视频、图文、音频与封面提取。零外部 PHP 依赖，开箱即用，支持命令行与 HTTP 两种运行模式。

## 功能特性

- **多平台支持** — 覆盖国内外 26+ 主流短视频/社交平台
- **多类型解析** — 视频、图集、音频、封面、实况图(Live Photo)
- **无水印提取** — 直接获取平台原始无水印资源地址
- **流式代理** — 内置签名代理，服务端中转下载，无需暴露源站地址
- **B站音视频合并** — 使用 FFmpeg 流式合并 DASH 音视频流，文件不落地
- **零依赖** — 无需 Composer，无外部 PHP 库，单目录部署即可运行

## 支持平台

| 平台 | 类型 | 平台 | 类型 |
|------|------|------|------|
| 抖音 | 视频/图文/音频 | TikTok | 视频 |
| 快手 | 视频/图文 | YouTube | 视频 |
| 小红书 | 视频/图文/实况图 | Instagram | 视频/图文 |
| 哔哩哔哩(B站) | 视频/音频 | Twitter(X) | 视频 |
| 微博 | 视频/图文 | AcFun | 视频 |
| 西瓜视频 | 视频 | 皮皮搞笑 | 视频 |
| 知乎 | 视频 | 梨视频 | 视频 |
| 微视 | 视频 | 好看视频 | 视频 |
| 逗拍 | 视频 | 虎牙 | 视频 |
| 绿洲 | 视频/图文 | 美拍 | 视频 |
| 皮皮虾 | 视频 | 全民小视频 | 视频 |
| 全民K歌 | 音频 | 六间房 | 视频 |
| 新片场 | 视频 | 最右 | 视频 |

## 系统要求

| 依赖 | 要求 | 说明 |
|------|------|------|
| PHP | >= 7.2 | 需启用 `curl`、`openssl`、`json` 扩展 |
| proc_open | 启用 | B站视频合并需要，`php.ini` 中不能禁用 |
| FFmpeg | 可选 | B站视频下载(音视频合并)需要，详见 [assets/FFMPEG.md](assets/FFMPEG.md) |
| Node.js | 可选 | 抖音签名生成需要 |
| yt-dlp | 可选 | YouTube 视频解析备用方案 |

## 目录结构

```
PHP/
├── index.php                  # HTTP 入口 (路由)
├── parse.php                  # CLI 入口
├── logo.png                   # 站点 Logo
├── config/
│   └── business_config.json   # 平台域名映射 & UA 配置
├── src/
│   ├── autoload.php           # PSR-4 自动加载 & PHP 兼容垫片
│   ├── Config.php             # 配置管理
│   ├── HttpClient.php         # HTTP 客户端 (cURL / streams)
│   ├── HttpResponse.php       # HTTP 响应封装
│   ├── Logger.php             # 日志工具
│   ├── MediaProxy.php         # 媒体代理 & FFmpeg 合并
│   ├── ParserFactory.php      # 解析器工厂
│   ├── ParserService.php      # 解析业务逻辑
│   ├── Response.php           # 标准响应格式
│   ├── UrlParser.php          # URL 提取与解析
│   ├── WebFetcher.php         # 网页抓取
│   ├── Support/
│   │   ├── DouyinSigner.php   # 抖音签名
│   │   └── YtDlp.php          # YouTube yt-dlp 桥接
│   └── Parsers/
│       ├── BaseParser.php     # 解析器基类
│       ├── DouyinParser.php   # 抖音
│       ├── BilibiliParser.php # B站
│       ├── ...                # 其他平台解析器
│       └── ZuiyouParser.php   # 最右
├── assets/
│   ├── FFMPEG.md              # FFmpeg 配置说明
│   └── douyin_utils/          # 抖音签名工具
├── templates/
│   └── landing.html           # Web 前端页面
├── static/                    # 静态资源
└── logs/                      # 运行日志
```

## 本地部署

### 方式一：PHP 内置服务器（推荐本地使用）

最简单的方式，无需安装任何 Web 服务器：

```bash
cd PHP
php -S localhost:8080 index.php
```

浏览器打开 `http://localhost:8080` 即可使用。

### 方式二：指定端口和地址

```bash
# 局域网访问（允许其他设备连接）
php -S 0.0.0.0:8080 index.php

# 指定其他端口
php -S localhost:3000 index.php
```

### 命令行模式

无需启动 Web 服务，直接在终端解析：

```bash
# 直接解析链接
php parse.php "https://v.douyin.com/abc123/"

# 解析分享文案（自动提取其中的链接）
php parse.php "7.07 February 复制打开抖音 https://v.douyin.com/abc123/"

# 解析B站视频
php parse.php "https://www.bilibili.com/video/BV1xx411c7mD"
```

输出为 JSON 格式，解析成功退出码为 `0`，失败为 `1`。

### 配置环境变量

根据需要设置以下环境变量：

```bash
# Linux / macOS
export SECRET_KEY="your_secret_key"       # 代理 URL 签名密钥（默认：default_secret_key）
export FFMPEG_BINARY="/path/to/ffmpeg"    # FFmpeg 路径（可选，也可放在 assets/ 目录下）
export NODE_BINARY="/path/to/node"        # Node.js 路径（可选，默认使用系统 PATH）
export DOMAIN="https://your-domain.com"   # 站点域名（可选，影响代理 URL 生成）

# Windows (PowerShell)
$env:SECRET_KEY="your_secret_key"
$env:FFMPEG_BINARY="C:\path\to\ffmpeg.exe"
```

### FFmpeg 配置

B站视频需要 FFmpeg 合并音视频流。将 FFmpeg 二进制文件放入 `assets/` 目录即可自动识别：

- Windows: `assets/ffmpeg.exe`
- Linux/macOS: `assets/ffmpeg`

详细说明参见 [assets/FFMPEG.md](assets/FFMPEG.md)。

## 服务器部署

### 方式一：Nginx + PHP-FPM

#### 1. 安装 PHP 及扩展

```bash
# Ubuntu / Debian
sudo apt update
sudo apt install php-fpm php-curl php-json php-mbstring

# CentOS / RHEL
sudo yum install php-fpm php-curl php-json php-mbstring

# 确认 proc_open 未被禁用
# 编辑 php.ini，确保 disable_functions 中不包含 proc_open 和 exec
sudo vim /etc/php/7.2/fpm/php.ini
```

#### 2. 部署项目文件

```bash
# 克隆项目
git clone https://github.com/ucmao/media-parser.git /var/www/media-parser
cd /var/www/media-parser/PHP

# 创建日志目录并设置权限
mkdir -p logs
chmod 777 logs

# 放置 FFmpeg（如需B站下载）
# 参考 assets/FFMPEG.md 下载对应平台的 FFmpeg
cp /path/to/ffmpeg assets/ffmpeg
chmod +x assets/ffmpeg
```

#### 3. 配置 Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/media-parser/PHP;
    index index.php;

    # 所有请求路由到 index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # 静态资源直接返回
    location /static/ {
        expires 7d;
        add_header Cache-Control "public, immutable";
    }

    # Logo
    location = /logo.png {
        expires 7d;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        # 环境变量
        fastcgi_param SECRET_KEY "your_secret_key";
        # fastcgi_param FFMPEG_BINARY "/var/www/media-parser/PHP/assets/ffmpeg";
        # fastcgi_param NODE_BINARY "/usr/bin/node";
        # fastcgi_param DOMAIN "https://your-domain.com";

        # 超时设置（B站合并下载可能较慢）
        fastcgi_read_timeout 1800;
        fastcgi_send_timeout 1800;
    }

    # 禁止访问隐藏文件和日志
    location ~ /\. { deny all; }
    location /logs/ { deny all; }
    location /config/ { deny all; }
    location /src/ { deny all; }
}
```

#### 4. 启动服务

```bash
sudo systemctl restart php-fpm
sudo systemctl restart nginx
```

### 方式二：Apache + mod_php

#### 1. 启用 mod_rewrite

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### 2. 创建 `.htaccess`

在 `PHP/` 目录下创建 `.htaccess`：

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# 禁止访问敏感目录
<IfModule mod_authz_core.c>
    <LocationMatch "^/(logs|config|src)/">
        Require all denied
    </LocationMatch>
</IfModule>

# 环境变量
SetEnv SECRET_KEY "your_secret_key"
# SetEnv FFMPEG_BINARY "/var/www/media-parser/PHP/assets/ffmpeg"
```

#### 3. Apache VirtualHost 配置

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/media-parser/PHP

    <Directory /var/www/media-parser/PHP>
        AllowOverride All
        Require all granted
    </Directory>

    # 超时设置
    TimeOut 1800
    ProxyTimeout 1800
</VirtualHost>
```

### 方式三：Docker 部署

在 `PHP/` 目录下创建 `Dockerfile`：

```dockerfile
FROM php:7.2-apache

RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    ffmpeg \
    && docker-php-ext-install curl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
RUN mkdir -p /var/www/html/logs && chmod 777 /var/www/html/logs

# Apache 重写规则
RUN echo '<Directory /var/www/html/>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/media-parser.conf \
    && a2enconf media-parser

ENV SECRET_KEY="change_me_in_production"
EXPOSE 80
```

```bash
# 构建并运行
docker build -t zparse-php .
docker run -d -p 8080:80 -e SECRET_KEY="your_secret_key" zparse-php
```

## API 文档

### 解析接口

**POST** `/api/parse`

请求体 (JSON)：
```json
{
    "text": "视频链接或包含链接的分享文案"
}
```

成功响应 (HTTP 200)：
```json
{
    "retcode": 200,
    "retdesc": "成功",
    "succ": true,
    "data": {
        "video_id": "BV1xx411c7mD",
        "platform": "哔哩哔哩",
        "title": "视频标题",
        "video_url": "https://...",
        "video_proxy_url": "api/proxy?u=...&p=...&s=...",
        "audio_url": "https://...",
        "audio_proxy_url": "api/proxy?u=...&p=...&s=...",
        "merge_proxy_url": "api/merge?v=...&a=...&p=...&s=...",
        "cover_url": "https://...",
        "cover_proxy_url": "api/proxy?u=...&p=...&s=...",
        "author": {
            "nickname": "UP主名称",
            "author_id": "uid_12345",
            "avatar": "https://...",
            "avatar_proxy_url": "api/proxy?u=...&p=...&s=..."
        },
        "image_list": []
    }
}
```

失败响应 (HTTP 400)：
```json
{
    "retcode": 400,
    "retdesc": "该链接尚未支持提取",
    "succ": false,
    "data": null
}
```

### 健康检查

**GET** `/api/health`

返回运行环境信息，包括 PHP 版本、扩展状态、FFmpeg 是否可用等。

### 代理下载

**GET** `/api/proxy?u={base64_url}&p={platform}&s={hmac_sig}&download=1`

通过服务端代理下载媒体文件，带有 HMAC-SHA256 签名防篡改。

### 合并下载（B站）

**GET** `/api/merge?v={base64_video}&a={base64_audio}&p={platform}&s={hmac_sig}&download=1&name={filename}`

流式合并 B站 DASH 音视频流并直接输出 MP4，文件不落地。

## 安全建议

- **修改签名密钥**：生产环境务必修改 `SECRET_KEY` 环境变量，不要使用默认值
- **禁止目录访问**：确保 `logs/`、`config/`、`src/` 目录不可从外部访问
- **HTTPS**：建议启用 HTTPS，可使用 Let's Encrypt 免费证书
- **限流**：建议在 Nginx 层配置请求频率限制，防止接口滥用

## 日志

运行日志位于 `logs/media_parser_php.log`，包含解析错误、FFmpeg 错误等信息。日志文件不会自动轮转，建议配置 logrotate 定期清理：

```bash
# /etc/logrotate.d/media-parser
/var/www/media-parser/PHP/logs/*.log {
    weekly
    rotate 4
    compress
    missingok
    notifempty
}
```
