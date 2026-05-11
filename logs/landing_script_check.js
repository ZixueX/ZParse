
        function withDownloadParam(url) {
            if (!url) return url;
            if (!url.includes('api/proxy') && !url.includes('api/merge')) return url;
            const separator = url.includes('?') ? '&' : '?';
            return url + separator + 'download=1';
        }

        function withUrlParam(url, key, value) {
            if (!url || value === undefined || value === null || value === '') return url;
            const separator = url.includes('?') ? '&' : '?';
            return url + separator + encodeURIComponent(key) + '=' + encodeURIComponent(value);
        }

        function makeDownloadName(platform, videoId) {
            const safePlatform = (platform || 'video').replace(/[\\/:*?"<>|\s]+/g, '_');
            const safeId = (videoId || Date.now().toString()).replace(/[\\/:*?"<>|\s]+/g, '_');
            return `${safePlatform}_${safeId}.mp4`;
        }

        function setupSyncedPreview(videoUrl, audioUrl) {
            const cover = document.getElementById('resultCover');
            const video = document.getElementById('resultPreviewVideo');
            const audio = document.getElementById('resultPreviewAudio');
            const play = document.getElementById('resultVideoPlay');
            const coverBlock = document.getElementById('coverBlock');
            const playLayer = play.parentElement;

            video.pause();
            audio.pause();
            video.removeAttribute('src');
            audio.removeAttribute('src');
            video.load();
            audio.load();
            video.classList.add('hidden');
            cover.classList.remove('hidden');

            if (!videoUrl) {
                playLayer.classList.add('hidden');
                coverBlock.onclick = null;
                play.onclick = null;
                return;
            }

            video.src = videoUrl;
            if (audioUrl) {
                audio.src = audioUrl;
            }

            const syncAudio = () => {
                if (!audioUrl || Number.isNaN(video.currentTime)) return;
                try {
                    audio.currentTime = video.currentTime;
                } catch (e) {}
            };

            video.onplay = () => {
                if (!audioUrl) return;
                syncAudio();
                audio.playbackRate = video.playbackRate;
                audio.volume = video.volume;
                audio.muted = video.muted;
                audio.play().catch(() => {});
            };
            video.onpause = () => { if (audioUrl) audio.pause(); };
            video.onended = () => { if (audioUrl) audio.pause(); };
            video.onseeking = syncAudio;
            video.onseeked = syncAudio;
            video.onratechange = () => { if (audioUrl) audio.playbackRate = video.playbackRate; };
            video.onvolumechange = () => {
                if (!audioUrl) return;
                audio.volume = video.volume;
                audio.muted = video.muted;
            };
            video.ontimeupdate = () => {
                if (!audioUrl || audio.paused || video.paused) return;
                if (Math.abs(audio.currentTime - video.currentTime) > 0.35) {
                    syncAudio();
                }
            };

            const startPreview = (event) => {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                cover.classList.add('hidden');
                video.classList.remove('hidden');
                playLayer.classList.add('hidden');
                video.play().catch(() => {});
            };

            play.href = videoUrl;
            play.onclick = startPreview;
            coverBlock.onclick = startPreview;
            coverBlock.classList.add('cursor-pointer');
            coverBlock.title = audioUrl ? '点击播放视频（自动同步音频）' : '点击播放视频';
            playLayer.classList.remove('hidden');
        }

        function inferPlatformFromText(text) {
            const rules = [
                ['抖音', /(?:v\.douyin\.com|douyin\.com|iesdouyin\.com)/i],
                ['小红书', /xiaohongshu\.com/i],
                ['快手', /kuaishou\.com/i],
                ['哔哩哔哩', /(?:bilibili\.com|b23\.tv)/i],
                ['微博', /(?:weibo\.com|m\.weibo\.cn)/i],
                ['西瓜视频', /ixigua\.com/i],
                ['TikTok', /tiktok\.com/i],
                ['YouTube', /(?:youtube\.com|youtu\.be)/i],
                ['Instagram', /instagram\.com/i],
                ['Twitter', /(?:twitter\.com|x\.com)/i],
                ['知乎', /zhihu\.com/i],
                ['AcFun', /acfun\.cn/i],
                ['微视', /weishi\.qq\.com/i],
                ['好看视频', /haokan\.(?:baidu|hao123)\.com/i],
                ['皮皮搞笑', /pipigx\.com/i],
                ['梨视频', /pearvideo\.com/i],
            ];
            for (const [name, pattern] of rules) {
                if (pattern.test(text || '')) return name;
            }
            return '未知平台';
        }

        function setPlatformBadge(platform) {
            const platformEl = document.getElementById('resultPlatform');
            if (platform) {
                platformEl.innerText = platform;
                platformEl.classList.remove('hidden');
                platformEl.classList.add('inline-flex');
            } else {
                platformEl.classList.add('hidden');
                platformEl.classList.remove('inline-flex');
            }
        }

        function setStatusBadge(type, text) {
            const badge = document.getElementById('resultStatusBadge');
            const icon = document.getElementById('resultStatusIcon');
            document.getElementById('resultStatusText').innerText = text;
            const styles = {
                success: ['bg-green-50', 'text-green-600', 'border-green-100', 'fa-check-circle'],
                error: ['bg-red-50', 'text-red-600', 'border-red-100', 'fa-exclamation-circle'],
                info: ['bg-indigo-50', 'text-indigo-600', 'border-indigo-100', 'fa-info-circle'],
            };
            const selected = styles[type] || styles.info;
            badge.className = `inline-flex items-center space-x-1 ${selected[0]} ${selected[1]} px-3 py-1 rounded-full text-xs font-bold border ${selected[2]} shadow-sm`;
            icon.className = `fas ${selected[3]}`;
        }

        function addMessageDetail(label, value) {
            if (value === undefined || value === null || value === '') return;
            const box = document.getElementById('resultMessageDetails');
            const row = document.createElement('div');
            row.className = 'flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-3';
            const key = document.createElement('span');
            key.className = 'shrink-0 font-bold text-slate-700';
            key.textContent = label;
            const val = document.createElement('span');
            val.className = 'break-all';
            val.textContent = String(value);
            row.appendChild(key);
            row.appendChild(val);
            box.appendChild(row);
        }

        function renderMessageResult({ type = 'info', platform = 'ZParse', title = '', message = '', code = '', httpStatus = '' }) {
            abortPreviousMediaRequests();
            const resultState = document.getElementById('resultState');
            const errorState = document.getElementById('errorState');
            const coverBlock = document.getElementById('coverBlock');
            const cover = document.getElementById('resultCover');
            const previewVideo = document.getElementById('resultPreviewVideo');
            const previewAudio = document.getElementById('resultPreviewAudio');
            const play = document.getElementById('resultVideoPlay');
            const albumContainer = document.getElementById('resultAlbum');
            const audioContainer = document.getElementById('audioContainer');
            const actions = document.getElementById('resultActions');
            const authorInfo = document.getElementById('authorInfo');
            const details = document.getElementById('resultMessageDetails');

            errorState.classList.add('hidden');
            errorState.classList.remove('flex');
            setPlatformBadge(platform);
            setStatusBadge(type, type === 'error' ? '解析失败' : '提示');

            coverBlock.classList.remove('hidden', 'cursor-pointer');
            coverBlock.onclick = null;
            coverBlock.title = '';
            cover.src = 'logo.png';
            cover.className = 'w-full h-full object-contain p-12 bg-slate-50';
            cover.classList.remove('hidden');
            previewVideo.classList.add('hidden');
            previewAudio.classList.add('hidden');
            play.parentElement.classList.add('hidden');

            albumContainer.classList.add('hidden');
            albumContainer.classList.remove('flex');
            authorInfo.classList.add('hidden');
            authorInfo.classList.remove('flex');
            audioContainer.classList.add('hidden');
            audioContainer.classList.remove('block');
            actions.classList.add('hidden');

            document.getElementById('resultTitle').innerText = title;
            details.innerHTML = '';
            addMessageDetail('提示信息', message);
            addMessageDetail('解析平台', platform);
            addMessageDetail('错误代码', code);
            addMessageDetail('HTTP状态', httpStatus);
            details.classList.remove('hidden');

            resultState.classList.remove('hidden');
        }

        async function handleParse() {
            const urlInput = document.getElementById('parseUrl').value.trim();
            const loadingState = document.getElementById('loadingState');
            const resultState = document.getElementById('resultState');
            const errorState = document.getElementById('errorState');
            const parseBtn = document.getElementById('parseBtn');

            if (!urlInput) {
                loadingState.classList.add('hidden');
                loadingState.classList.remove('flex');
                renderMessageResult({
                    type: 'info',
                    platform: 'ZParse',
                    title: '请先粘贴视频链接',
                    message: '支持粘贴完整分享文案或视频链接，然后点击一键解析。'
                });
                return;
            }

            // UI Reset
            abortPreviousMediaRequests();
            resultState.classList.add('hidden');
            errorState.classList.add('hidden');
            loadingState.classList.remove('hidden');
            loadingState.classList.add('flex');
            parseBtn.disabled = true;
            parseBtn.classList.add('opacity-70');

            const parseController = new AbortController();
            const parseTimeout = window.setTimeout(() => parseController.abort(), 45000);

            try {
                const apiBase = location.pathname.endsWith('/')
                    ? location.pathname
                    : location.pathname.replace(/\/[^\/]*$/, '/');
                const response = await fetch(apiBase + 'api/parse', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ text: urlInput }),
                    signal: parseController.signal
                });

                const rawText = await response.text();
                let res = {};
                try {
                    res = JSON.parse(rawText);
                } catch (jsonErr) {
                    throw new Error(`接口返回非JSON，HTTP ${response.status}: ${rawText.slice(0, 200)}`);
                }

                loadingState.classList.add('hidden');
                loadingState.classList.remove('flex');

                if (response.ok && res.succ) {
                    // Update UI with results
                    setPlatformBadge(res.data.platform);
                    setStatusBadge('success', '解析成功');
                    const messageDetails = document.getElementById('resultMessageDetails');
                    messageDetails.classList.add('hidden');
                    messageDetails.innerHTML = '';
                    document.getElementById('resultActions').classList.remove('hidden');

                    const authorInfo = document.getElementById('authorInfo');
                    if (res.data.author && res.data.author.nickname) {
                        document.getElementById('authorName').innerText = res.data.author.nickname;
                        const authorIdEl = document.getElementById('authorId');
                        if (res.data.author.author_id) {
                            authorIdEl.innerText = 'ID: ' + res.data.author.author_id;
                            authorIdEl.classList.remove('hidden');
                        } else {
                            authorIdEl.classList.add('hidden');
                        }

                        document.getElementById('authorAvatar').src = res.data.author.avatar_proxy_url || res.data.author.avatar || "logo.png";
                        authorInfo.classList.remove('hidden');
                        authorInfo.classList.add('flex');
                    } else {
                        authorInfo.classList.add('hidden');
                        authorInfo.classList.remove('flex');
                    }

                    const coverPreviewUrl = res.data.cover_proxy_url || res.data.cover_url;
                    const coverDownloadUrl = res.data.cover_proxy_url || res.data.cover_url;
                    const resultCover = document.getElementById('resultCover');
                    resultCover.className = 'w-full h-full object-cover';
                    resultCover.src = coverPreviewUrl || 'logo.png';
                    resultCover.classList.remove('hidden');
                    document.getElementById('resultTitle').innerText = res.data.title || '无标题';

                    const videoDlBtn = document.getElementById('resultVideoDl');
                    const coverBlockEl = document.getElementById('coverBlock');
                    const videoPreviewUrl = res.data.video_proxy_url || res.data.video_url;
                    const audioPreviewUrl = res.data.audio_proxy_url || res.data.audio_url;
                    const mergeDownloadUrl = res.data.merge_proxy_url
                        ? withUrlParam(withDownloadParam(res.data.merge_proxy_url), 'name', makeDownloadName(res.data.platform, res.data.video_id))
                        : null;
                    const fallbackDownloadUrl = res.data.video_proxy_url || res.data.video_url;
                    if (videoPreviewUrl) {
                        videoDlBtn.href = mergeDownloadUrl || withDownloadParam(fallbackDownloadUrl);
                        videoDlBtn.setAttribute('download', makeDownloadName(res.data.platform, res.data.video_id));
                        setupSyncedPreview(videoPreviewUrl, audioPreviewUrl);
                        videoDlBtn.style.display = 'flex';
                    } else {
                        // In case of image-only platforms like Xiaohongshu without video
                        setupSyncedPreview('', '');
                        coverBlockEl.onclick = null;
                        coverBlockEl.classList.remove('cursor-pointer');
                        coverBlockEl.title = '';
                        videoDlBtn.style.display = 'none';
                    }

                    // Parse Audio
                    const audioContainer = document.getElementById('audioContainer');
                    const resultAudio = document.getElementById('resultAudio');
                    const audioUrl = res.data.audio_proxy_url || res.data.audio_url;
                    if (audioUrl && !videoPreviewUrl) {
                        resultAudio.src = audioUrl;
                        audioContainer.classList.remove('hidden');
                        audioContainer.classList.add('block');
                    } else {
                        resultAudio.src = '';
                        audioContainer.classList.add('hidden');
                        audioContainer.classList.remove('block');
                    }

                    // Parse Album Images
                    const albumContainer = document.getElementById('resultAlbum');
                    const albumGrid = document.getElementById('albumGrid');
                    const coverBlock = document.getElementById('coverBlock');

                    albumGrid.innerHTML = '';
                    if (res.data.image_list && res.data.image_list.length > 0) {
                        document.getElementById('albumCount').innerText = res.data.image_list.length;
                        res.data.image_list.forEach(item => {
                            let imgUrl = item;
                            let livePhotoUrl = null;
                            if (typeof item === 'object' && item !== null) {
                                imgUrl = item.proxy_url || item.url;
                                livePhotoUrl = item.live_photo_proxy_url || item.live_photo_url;
                            }

                            const imgContainer = document.createElement('div');
                            imgContainer.className = 'w-full h-full shrink-0 flex-none snap-center relative';
                            
                            let innerHtml = `<a href="${imgUrl}" target="_blank" class="block w-full h-full relative group">
                                <img src="${imgUrl}" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center text-white backdrop-blur-sm space-y-2">
                                    <i class="fas fa-expand text-4xl drop-shadow-md"></i>
                                    <span class="text-sm font-bold bg-black/50 px-3 py-1 rounded-full">查看高清大图</span>
                                </div>
                            </a>`;

                            if (livePhotoUrl) {
                                innerHtml += `<a href="${livePhotoUrl}" target="_blank" class="absolute bottom-6 left-1/2 -translate-x-1/2 bg-indigo-600/90 hover:bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-lg backdrop-blur-md flex items-center space-x-2 transition-transform hover:scale-105 z-10 w-max">
                                    <i class="fas fa-video"></i>
                                    <span>获取实况/动图</span>
                                </a>`;
                                innerHtml += `<div class="absolute top-12 left-4 bg-black/60 backdrop-blur-md text-white px-3 py-1 rounded-full text-xs font-bold border border-white/20 shadow-sm flex items-center space-x-1 z-10 pointer-events-none">
                                    <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                                    <span>Live</span>
                                </div>`;
                            }

                            imgContainer.innerHTML = innerHtml;
                            albumGrid.appendChild(imgContainer);
                        });
                        coverBlock.classList.add('hidden');
                        albumContainer.classList.remove('hidden');
                        albumContainer.classList.add('flex');
                    } else {
                        coverBlock.classList.remove('hidden');
                        albumContainer.classList.add('hidden');
                        albumContainer.classList.remove('flex');
                    }

                    document.getElementById('resultCoverDl').href = coverDownloadUrl || coverPreviewUrl || '';

                    resultState.classList.remove('hidden');
                } else {
                    renderMessageResult({
                        type: 'error',
                        platform: (res.data && res.data.platform) || inferPlatformFromText(urlInput),
                        title: '解析失败',
                        message: res.retdesc || '解析失败，请检查链接是否正确或稍后重试',
                        code: res.retcode || response.status,
                        httpStatus: response.status
                    });
                }
            } catch (err) {
                loadingState.classList.add('hidden');
                loadingState.classList.remove('flex');
                const isTimeout = err && err.name === 'AbortError';
                renderMessageResult({
                    type: 'error',
                    platform: inferPlatformFromText(urlInput),
                    title: isTimeout ? '解析超时' : '解析失败',
                    message: isTimeout ? '解析超时，请稍后重试或刷新页面' : (err && err.message ? err.message : '网络错误，请稍后重试'),
                    code: isTimeout ? 'TIMEOUT' : 'NETWORK_ERROR'
                });
            } finally {
                window.clearTimeout(parseTimeout);
                parseBtn.disabled = false;
                parseBtn.classList.remove('opacity-70');
            }
        }

        function abortPreviousMediaRequests() {
            const audio = document.getElementById('resultAudio');
            if (audio) {
                try { audio.pause(); } catch (e) {}
                audio.removeAttribute('src');
                try { audio.load(); } catch (e) {}
            }

            const cover = document.getElementById('resultCover');
            if (cover) {
                cover.removeAttribute('src');
                cover.classList.remove('hidden');
            }

            const previewVideo = document.getElementById('resultPreviewVideo');
            if (previewVideo) {
                try { previewVideo.pause(); } catch (e) {}
                previewVideo.removeAttribute('src');
                previewVideo.classList.add('hidden');
                previewVideo.onplay = null;
                previewVideo.onpause = null;
                previewVideo.onended = null;
                previewVideo.onseeking = null;
                previewVideo.onseeked = null;
                previewVideo.onratechange = null;
                previewVideo.onvolumechange = null;
                previewVideo.ontimeupdate = null;
                try { previewVideo.load(); } catch (e) {}
            }

            const previewAudio = document.getElementById('resultPreviewAudio');
            if (previewAudio) {
                try { previewAudio.pause(); } catch (e) {}
                previewAudio.removeAttribute('src');
                previewAudio.classList.add('hidden');
                try { previewAudio.load(); } catch (e) {}
            }

            const avatar = document.getElementById('authorAvatar');
            if (avatar) {
                avatar.removeAttribute('src');
            }

            const albumGrid = document.getElementById('albumGrid');
            if (albumGrid) {
                albumGrid.querySelectorAll('img').forEach(img => img.removeAttribute('src'));
                albumGrid.innerHTML = '';
            }

            const play = document.getElementById('resultVideoPlay');
            if (play) {
                play.removeAttribute('href');
                play.onclick = null;
            }

            const coverBlock = document.getElementById('coverBlock');
            if (coverBlock) {
                coverBlock.onclick = null;
            }
        }
    
