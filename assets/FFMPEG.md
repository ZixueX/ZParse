# FFmpeg 配置说明

ZParse 使用 FFmpeg 合并 B站（哔哩哔哩）视频的音视频流，采用流式合并方式，文件不落地，直接输出给用户下载。

## 下载 FFmpeg

请根据你的操作系统下载对应的 FFmpeg **静态编译** 二进制文件，并将可执行文件放入本目录（`assets/`）。

### Windows

1. 访问 https://www.gyan.dev/ffmpeg/builds/ ，下载 `ffmpeg-release-essentials.zip`
2. 解压后在 `bin/` 目录中找到 `ffmpeg.exe`
3. 将 `ffmpeg.exe` 复制到本目录（`assets/ffmpeg.exe`）

### Linux

```bash
# Ubuntu / Debian
sudo apt install ffmpeg
# 或下载静态编译版本
wget https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz
tar xf ffmpeg-release-amd64-static.tar.xz
cp ffmpeg-*-static/ffmpeg assets/ffmpeg
chmod +x assets/ffmpeg
```

### macOS

```bash
# 使用 Homebrew
brew install ffmpeg
# 或下载静态编译版本
# Intel: https://evermeet.cx/ffmpeg/
# Apple Silicon: 同上，选择 arm64 版本
# 下载后将 ffmpeg 复制到 assets/ 目录
cp ffmpeg assets/ffmpeg
chmod +x assets/ffmpeg
```

## 查找顺序

程序按以下优先级查找 FFmpeg：

1. 环境变量 `FFMPEG_BINARY`（指定完整路径）
2. `assets/ffmpeg`（Linux/macOS）或 `assets/ffmpeg.exe`（Windows）
3. 系统 PATH 中的 `ffmpeg`

## 验证

放置完成后，可在命令行中运行以下命令验证：

```bash
# 如果放在 assets/ 目录
./assets/ffmpeg -version

# 如果已加入系统 PATH
ffmpeg -version
```

输出版本信息即表示配置成功。
