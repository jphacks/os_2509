/* eslint-disable no-console */
/**
 * child/assets/js/app.js
 * 音声テキストと位置情報を保存し、日記生成トリガーを走らせるフロント側ロジック
 */

document.addEventListener('DOMContentLoaded', () => {
    const VoiceApp = {
        // ---------------------------
        // 設定値
        // ---------------------------
        config: {
            lang: 'ja-JP',
            platform: {
                isMobile: /iPhone|iPad|iPod|Android/i.test(navigator.userAgent),
                isIOS: /iPhone|iPad|iPod/i.test(navigator.userAgent),
            },
            locationIntervalMs: 1_000,
        },

        api: {
            root: '/os_2509/',
            get saveData() {
                return `${this.root}frontend/top/child/save_data.php`;
            },
            get saveTranscript() {
                return `${this.root}frontend/top/child/save_transcript.php`;
            },
            get runDiary() {
                return `${this.root}frontend/top/child/run_diary_generation.php`;
            },
        },

        async parseJSONResponse(resp) {
            const raw = await resp.text();
            console.debug('[API] raw response snippet', {
                url: resp.url || '(unknown)',
                status: resp.status,
                length: raw.length,
                preview: raw.slice(0, 200),
            });

            try {
                const data = raw ? JSON.parse(raw) : null;
                return { ok: resp.ok, status: resp.status, data, raw };
            } catch (error) {
                console.error('[API] Non-JSON response', {
                    status: resp.status,
                    preview: raw.slice(0, 400),
                });
                throw new Error(`Non-JSON response (status ${resp.status})`);
            }
        },

        // ---------------------------
        // DOM 参照
        // ---------------------------
        dom: {
            toggleBtn: document.getElementById('toggle-btn'),
            clearBtn: document.getElementById('clear-btn'),
            statusLight: document.getElementById('status-light'),
            statusText: document.getElementById('status-text'),
            finalTranscript: document.getElementById('final-transcript'),
            interimTranscript: document.getElementById('interim-transcript'),
            errorDisplay: document.getElementById('error-display'),
            errorMessage: document.getElementById('error-message'),
        },

        // ---------------------------
        // 状態管理
        // ---------------------------
        state: {
            isRecognizing: false,
            finalTranscript: '',
            lastSentTranscript: '',
            ignoreOnend: false,
            currentLatitude: null,
            currentLongitude: null,
            locationIntervalId: null,
            pendingFinalSend: false,
        },

        recognition: null,

        // ---------------------------
        // 初期化
        // ---------------------------
        init() {
            this.updateUI('stopped');

            const SpeechRecognition =
                window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                this.handleUnsupportedBrowser();
                return;
            }

            if (!navigator.geolocation) {
                console.warn('[Geo] このブラウザは位置情報に対応していません');
            }

            this.recognition = new SpeechRecognition();
            this.configureRecognition();
            this.bindEvents();
            console.log('[VoiceApp] 初期化が完了しました');
        },

        configureRecognition() {
            this.recognition.lang = this.config.lang;
            this.recognition.interimResults = true;
            this.recognition.continuous = !this.config.platform.isMobile;
        },

        bindEvents() {
            this.dom.toggleBtn.addEventListener('click', () =>
                this.toggleRecognition(),
            );
            this.dom.clearBtn.addEventListener('click', () =>
                this.clearTranscript(),
            );

            this.recognition.onstart = () => {
                console.debug('[recognition] onstart');
                this.updateUI('waiting');
            };

            this.recognition.onaudiostart = () => {
                console.debug('[recognition] onaudiostart');
                this.updateUI('recognizing');
            };

            this.recognition.onend = () => {
                console.debug('[recognition] onend', {
                    ignoreOnend: this.state.ignoreOnend,
                    wasRecognizing: this.state.isRecognizing,
                    pendingFinalSend: this.state.pendingFinalSend,
                });

                if (this.state.pendingFinalSend) {
                    this.flushFinalTranscript();
                    return;
                }

                if (this.state.ignoreOnend) {
                    this.state.ignoreOnend = false;
                    return;
                }

                if (this.state.isRecognizing) {
                    this.updateUI('waiting');
                    try {
                        this.recognition.start();
                    } catch (error) {
                        console.warn('[recognition] restart failed', error);
                        this.updateUI('stopped');
                        this.state.isRecognizing = false;
                    }
                } else {
                    this.updateUI('stopped');
                }
            };

            this.recognition.onerror = (event) => {
                this.state.isRecognizing = false;

                console.error('[recognition] onerror', {
                    name: event?.error,
                    message: event?.message,
                });

                let errorMsg = '音声認識でエラーが発生しました。';
                switch (event?.error) {
                    case 'not-allowed':
                    case 'service-not-allowed':
                        errorMsg =
                            'マイクへのアクセスが許可されていません。ブラウザ設定を確認してください。';
                        break;
                    case 'audio-capture':
                        errorMsg =
                            'マイクが見つかりません。別アプリで使用されていないか確認してください。';
                        break;
                    case 'no-speech':
                        errorMsg =
                            '音声が検出されませんでした。マイクに近づいて話してください。';
                        break;
                    case 'network':
                        errorMsg =
                            '音声認識サーバーとの通信に問題があります。ネットワークを確認してください。';
                        break;
                    case 'aborted':
                        errorMsg = '音声認識が中断されました。';
                        break;
                    case 'bad-grammar':
                    case 'language-not-supported':
                        errorMsg =
                            '音声認識が対応していない設定です。言語設定を確認してください。';
                        break;
                    default:
                        errorMsg = `音声認識エラー: ${event?.error || 'unknown'}`;
                }

                this.updateUI('error', errorMsg);
                this.state.ignoreOnend = true;

                const transient = ['no-speech', 'network', 'aborted'].includes(
                    event?.error,
                );
                if (transient) {
                    setTimeout(() => {
                        if (!this.state.ignoreOnend) {
                            try {
                                this.updateUI('waiting');
                                this.recognition.start();
                                this.state.isRecognizing = true;
                            } catch (error) {
                                console.error('[recognition] 再開失敗', error);
                            }
                        }
                    }, 800);
                }

                if (this.state.locationIntervalId) {
                    clearInterval(this.state.locationIntervalId);
                    this.state.locationIntervalId = null;
                }
            };

            this.recognition.onresult = (event) => {
                this.handleRecognitionResult(event);
            };
        },

        // ---------------------------
        // UI と制御
        // ---------------------------
        toggleRecognition() {
            if (this.state.isRecognizing) {
                this.stopRecognition();
            } else {
                this.startRecognition();
            }
        },

        startRecognition() {
            if (this.state.isRecognizing) return;

            if (this.state.locationIntervalId) {
                clearInterval(this.state.locationIntervalId);
            }

            this.state.locationIntervalId = setInterval(
                () => this.sendPeriodicLocation(),
                this.config.locationIntervalMs,
            );

            this.state.pendingFinalSend = false;
            this.state.ignoreOnend = false;
            this.state.isRecognizing = true;

            const existingFinal =
                this.dom.finalTranscript.textContent?.trim() || '';
            this.state.finalTranscript = existingFinal;
            this.state.lastSentTranscript = existingFinal;
            this.dom.interimTranscript.textContent = '';
            this.dom.errorDisplay.classList.add('hidden');

            console.debug('[recognition] startRecognition', {
                existingFinalTextLength: this.state.finalTranscript.length,
            });

            try {
                this.recognition.start();
            } catch (error) {
                console.error('[recognition] start failed', error);
                this.updateUI('error', 'マイクへのアクセスに失敗しました。');
                this.state.isRecognizing = false;
                if (this.state.locationIntervalId) {
                    clearInterval(this.state.locationIntervalId);
                    this.state.locationIntervalId = null;
                }
            }
        },

        stopRecognition() {
            if (!this.state.isRecognizing) return;

            if (this.state.locationIntervalId) {
                clearInterval(this.state.locationIntervalId);
                this.state.locationIntervalId = null;
            }

            this.state.isRecognizing = false;
            this.state.pendingFinalSend = true;
            this.state.ignoreOnend = false;

            try {
                this.recognition.stop();
            } catch (error) {
                console.debug('[recognition] stop threw (ignored)', error);
                this.flushFinalTranscript();
            }
        },

        flushFinalTranscript() {
            const finalText = this.state.finalTranscript.trim();
            const interimText =
                this.dom.interimTranscript.textContent?.trim() || '';

            let combined = finalText;
            if (interimText) {
                const needsSpace =
                    finalText.length > 0 &&
                    !/\s$/.test(finalText) &&
                    !/[、。,.!?！？]$/.test(finalText);
                combined = `${finalText}${needsSpace ? ' ' : ''}${interimText}`;
            }

            combined = combined.trim();

            if (combined.length > 0) {
                if (combined === this.state.lastSentTranscript) {
                    console.debug(
                        '[recognition] flushFinalTranscript: no new transcript to send',
                    );
                } else {
                    console.debug('[recognition] flushFinalTranscript', {
                        combinedLength: combined.length,
                        finalLength: finalText.length,
                        interimLength: interimText.length,
                        preview: combined.slice(0, 120),
                    });
                    this.sendDataToPHP(combined);
                    this.state.lastSentTranscript = combined;
                }
                this.state.finalTranscript = combined;
                this.dom.finalTranscript.textContent = combined;
            } else {
                console.warn(
                    '[recognition] flushFinalTranscript: no transcript to send.',
                );
                this.state.currentLatitude = null;
                this.state.currentLongitude = null;
            }

            this.dom.interimTranscript.textContent = '';
            this.dom.errorDisplay.classList.add('hidden');
            this.state.pendingFinalSend = false;
            this.state.ignoreOnend = false;
            this.updateUI('stopped');
        },

        handleRecognitionResult(event) {
            let interimTranscript = '';

            for (let i = event.resultIndex; i < event.results.length; i += 1) {
                const result = event.results[i][0];
                const transcript = result.transcript.trim();

                if (event.results[i].isFinal) {
                    if (transcript.length > 0) {
                        const needsPunct = !/[\u3002\uFF01\uFF1F!?]$/.test(
                            transcript,
                        );
                        this.state.finalTranscript += needsPunct
                            ? `${transcript}\u3002`
                            : transcript;
                    } else {
                        console.debug(
                            '[recognition] ignored empty final segment',
                            { result },
                        );
                    }
                } else if (transcript.length > 0) {
                    interimTranscript += transcript;
                }
            }

            console.debug('[recognition] handleRecognitionResult', {
                resultIndex: event.resultIndex,
                interimLength: interimTranscript.length,
                finalLength: this.state.finalTranscript.length,
                interimPreview: interimTranscript.slice(0, 80),
                finalPreview: this.state.finalTranscript.slice(-80),
            });

            this.dom.finalTranscript.textContent = this.state.finalTranscript;
            this.dom.interimTranscript.textContent = interimTranscript;
        },

        clearTranscript() {
            if (this.state.isRecognizing) {
                this.state.isRecognizing = false;
                this.state.ignoreOnend = true;
                this.state.pendingFinalSend = false;
                try {
                    this.recognition.stop();
                } catch (error) {
                    console.debug('[recognition] stop threw (ignored)', error);
                }
            }

            if (this.state.locationIntervalId) {
                clearInterval(this.state.locationIntervalId);
                this.state.locationIntervalId = null;
            }

            this.state.finalTranscript = '';
            this.state.lastSentTranscript = '';
            this.dom.finalTranscript.textContent = '';
            this.dom.interimTranscript.textContent = '';
            this.dom.errorDisplay.classList.add('hidden');
            this.updateUI('stopped');
            this.state.currentLatitude = null;
            this.state.currentLongitude = null;
        },

        updateUI(state, message = '') {
            const {
                statusLight,
                statusText,
                toggleBtn,
                errorDisplay,
                errorMessage,
            } = this.dom;

            statusLight.className = 'status-light';
            toggleBtn.className = 'diary-button';
            errorDisplay.classList.add('hidden');

            switch (state) {
                case 'recognizing':
                    statusLight.classList.add('recognizing');
                    statusText.textContent = 'はなしてください';
                    toggleBtn.textContent = 'かいわをとめる';
                    toggleBtn.classList.add('stop-btn');
                    break;
                case 'waiting':
                    statusLight.classList.add('waiting');
                    statusText.textContent = 'じゅんび中';
                    toggleBtn.textContent = 'かいわをとめる';
                    toggleBtn.classList.add('stop-btn');
                    break;
                case 'stopped':
                    statusLight.classList.add('stopped');
                    statusText.textContent = 'とまっているよ';
                    toggleBtn.textContent = 'はなす';
                    toggleBtn.classList.add('primary-btn');
                    break;
                case 'error':
                    statusLight.classList.add('stopped');
                    statusText.textContent = 'エラー';
                    errorMessage.textContent = message;
                    errorDisplay.classList.remove('hidden');
                    toggleBtn.textContent = '再試行';
                    toggleBtn.classList.add('primary-btn');
                    break;
                default:
                    statusLight.classList.add('stopped');
                    statusText.textContent = 'とまっているよ';
                    toggleBtn.textContent = 'はなす';
                    toggleBtn.classList.add('primary-btn');
            }
        },

        handleUnsupportedBrowser() {
            this.dom.toggleBtn.disabled = true;
            this.dom.clearBtn.disabled = true;
            this.updateUI('error', 'ご利用のブラウザは音声認識に対応していません。');
            this.dom.toggleBtn.textContent = '非対応';
        },

        // ---------------------------
        // 位置情報送信
        // ---------------------------
        sendPeriodicLocation() {
            if (!navigator.geolocation) {
                console.warn('[Interval] Geolocation not supported');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;

                    this.state.currentLatitude = lat;
                    this.state.currentLongitude = lon;

                    console.log(`[Interval] Sending location: ${lat}, ${lon}`);

                    const form = new URLSearchParams();
                    form.append('latitude', String(lat));
                    form.append('longitude', String(lon));

                    fetch(this.api.saveData, {
                        method: 'POST',
                        headers: {
                            'Content-Type':
                                'application/x-www-form-urlencoded; charset=UTF-8',
                        },
                        credentials: 'include',
                        body: form.toString(),
                    })
                        .then((resp) => this.parseJSONResponse(resp))
                        .then(({ ok, status, data }) => {
                            console.debug('[Interval] location response', {
                                status,
                                saved: data?.saved,
                            });
                            if (!ok || data?.status !== 'success') {
                                console.warn('[Interval] Failed to save location', {
                                    status,
                                    data,
                                });
                            } else if (data?.saved?.db1_text) {
                                console.debug(
                                    '[Interval] Location request unexpectedly saved text',
                                    data,
                                );
                            }
                        })
                        .catch((error) => {
                            console.error('[Interval] Error sending location', error);
                        });
                },
                (error) => {
                    console.warn(
                        `[Interval] Could not get location: ${error.message}`,
                    );
                },
                {
                    enableHighAccuracy: true,
                    timeout: 5_000,
                    maximumAge: 0,
                },
            );
        },

        // ---------------------------
        // データ送信（UTF-8 / x-www-form-urlencoded）
        // ---------------------------
        sendDataToPHP(rawText) {
            const text = (rawText || '').trim();
            if (!text) {
                console.warn('[sendDataToPHP] empty text, skip sending');
                return Promise.resolve(null);
            }

            const latitude = this.state.currentLatitude;
            const longitude = this.state.currentLongitude;

            const form = new URLSearchParams();
            form.append('sound_text', text);
            form.append('text', text);
            if (latitude != null) form.append('latitude', String(latitude));
            if (longitude != null) form.append('longitude', String(longitude));
            form.append('ts', String(Date.now()));

            console.info('[sendDataToPHP] sending transcript', {
                textLength: text.length,
                preview: text.slice(0, 120),
                latitude,
                longitude,
            });

            return fetch(this.api.saveData, {
                method: 'POST',
                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded; charset=UTF-8',
                },
                credentials: 'same-origin',
                body: form.toString(),
            })
                .then((resp) => this.parseJSONResponse(resp))
                .then(({ ok, status, data }) => {
                    const debugId = data?.debug_id;
                    console.debug('[save_data] <-', status, data);
                    if (!ok || data?.status !== 'success') {
                        const error = new Error(`save_data failed: ${status}`);
                        error.response = { status, data, debugId };
                        throw error;
                    }

                    return fetch(this.api.runDiary, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json; charset=UTF-8' },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            date: new Date().toISOString().slice(0, 10),
                            debug_id: debugId,
                        }),
                    });
                })
                .then((resp) => this.parseJSONResponse(resp))
                .then(({ ok, status, data }) => {
                    console.debug('[run_diary_generation] <-', status, data);
                    if (!ok || data?.status !== 'processing_started') {
                        console.warn('[run_diary_generation] unexpected response', {
                            status,
                            data,
                        });
                    }
                })
                .catch((error) => {
                    const { response } = error || {};
                    console.error('[sendDataToPHP] error', error);
                    if (response) {
                        console.error('[sendDataToPHP] response payload', response);
                    }
                })
                .finally(() => {
                    // 次回送信用に位置情報をクリア
                    this.state.currentLatitude = null;
                    this.state.currentLongitude = null;
                });
        },
    };

    VoiceApp.init();
});
