/*
 * Web Speech API を使用した音声認識ロジック
 * file: app.js
 */

document.addEventListener('DOMContentLoaded', () => {
    const VoiceApp = {
        // --- 設定項目 ---
        config: {
            lang: 'ja-JP',
            platform: {
                isMobile: /iPhone|iPad|iPod|Android/i.test(navigator.userAgent),
                isIOS: /iPhone|iPad|iPod/i.test(navigator.userAgent),
            }
        },

        // --- DOM要素キャッシュ ---
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

        // --- 状態管理 ---
        state: {
            isRecognizing: false, // アプリとして認識中かどうかの状態
            finalTranscript: '',
            ignoreOnend: false, // 意図的な停止時に onend を無視するフラグ
        },

        recognition: null,

        init() {
            this.updateUI('stopped');

            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                this.handleUnsupportedBrowser();
                return;
            }

            this.recognition = new SpeechRecognition();
            this.configureRecognition();
            this.bindEvents();
            console.log("音声認識アプリの初期化が完了しました。");
        },

        configureRecognition() {
            this.recognition.lang = this.config.lang;
            this.recognition.interimResults = true;
            // モバイル環境で continuous: false の方が安定しやすいため、条件分岐を残す
            this.recognition.continuous = this.config.platform.isMobile ? false : true;
        },

        bindEvents() {
            this.dom.toggleBtn.addEventListener('click', () => this.toggleRecognition());
            this.dom.clearBtn.addEventListener('click', () => this.clearTranscript());

            this.recognition.onstart = () => {
                this.updateUI('waiting');
            };

            this.recognition.onaudiostart = () => {
                this.updateUI('recognizing');
            };

            this.recognition.onend = () => {
                if (this.state.ignoreOnend) {
                    this.state.ignoreOnend = false;
                    return;
                }
                if (this.state.isRecognizing) {
                    // 自動再開
                    this.updateUI('waiting');
                    this.recognition.start();
                } else {
                    this.updateUI('stopped');
                }
            };

            this.recognition.onerror = (event) => {
                this.state.isRecognizing = false;

                if (event.error === 'no-speech') {
                    // 無音タイムアウトは自動再開を期待するため、UIエラーは表示しない
                    this.updateUI('waiting');
                    return;
                }

                this.updateUI('error', `エラー: ${event.error}`);
                this.state.ignoreOnend = true;
            };

            this.recognition.onresult = (event) => {
                this.handleRecognitionResult(event);
            };
        },

        toggleRecognition() {
            if (this.state.isRecognizing) {
                this.stopRecognition();
            } else {
                this.startRecognition();
            }
        },

        startRecognition() {
            if (this.state.isRecognizing) return;
            this.state.isRecognizing = true;
            this.state.finalTranscript = this.dom.finalTranscript.textContent;
            this.dom.interimTranscript.textContent = '';
            this.dom.errorDisplay.classList.add('hidden');
            this.state.ignoreOnend = false;
            try {
                this.recognition.start();
            } catch (e) {
                console.error("認識開始時にエラーが発生しました。", e);
                this.updateUI('error', 'マイクへのアクセス権を確認してください。');
                this.state.isRecognizing = false;
            }
        },

        stopRecognition() {
            if (!this.state.isRecognizing) return;
            this.state.isRecognizing = false;
            this.state.ignoreOnend = true;

            // 確定テキストをサーバーに送信
            const currentText = this.state.finalTranscript + (this.dom.interimTranscript.textContent || '');
            if (currentText.trim().length > 0) {
                this.sendTranscriptToPHP(currentText);
                this.dom.finalTranscript.textContent = currentText.trim();
                this.dom.interimTranscript.textContent = '';
                this.state.finalTranscript = currentText.trim();
            }

            // iOS向けの停止ワークアラウンド
            if (this.config.platform.isIOS) {
                try {
                    this.recognition.start();
                    setTimeout(() => {
                        this.recognition.stop();
                        this.updateUI('stopped');
                    }, 50);
                } catch (e) {
                    this.recognition.stop();
                    this.updateUI('stopped');
                }
            } else {
                this.recognition.stop();
                this.updateUI('stopped');
            }
        },

        sendTranscriptToPHP(text) {
            console.log(`サーバーにデータを送信中... テキスト: "${text.substring(0, 20)}..."`);
            fetch('save_transcript.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `sound_text=${encodeURIComponent(text)}`
            })
                .then(response => {
                    if (!response.ok) {
                        console.error('サーバー応答エラー:', response.status);
                    }
                    return response.text();
                })
                .then(data => {
                    console.log('サーバー応答詳細:', data);
                })
                .catch(error => {
                    console.error('データの送信中にエラーが発生しました:', error);
                });
        },

        handleRecognitionResult(event) {
            let interimTranscript = '';
            for (let i = event.resultIndex; i < event.results.length; ++i) {
                const transcript = event.results[i][0].transcript;
                const confidence = event.results[i][0].confidence;

                if (event.results[i].isFinal) {
                    if (confidence > 0) {
                        this.state.finalTranscript += transcript + '。';
                    }
                } else {
                    interimTranscript += transcript;
                }
            }
            this.dom.finalTranscript.textContent = this.state.finalTranscript;
            this.dom.interimTranscript.textContent = interimTranscript;
        },

        clearTranscript() {
            if (this.state.isRecognizing) {
                this.state.isRecognizing = false;
                this.state.ignoreOnend = true;
                try {
                    this.recognition.stop();
                } catch (e) { /* ignore */ }
            }

            this.state.finalTranscript = '';
            this.dom.finalTranscript.textContent = '';
            this.dom.interimTranscript.textContent = '';
            this.dom.errorDisplay.classList.add('hidden');
            this.updateUI('stopped');
        },

        updateUI(state, message = '') {
            const light = this.dom.statusLight;
            const toggleBtn = this.dom.toggleBtn;

            // クラスをリセット
            light.className = 'status-light';
            toggleBtn.className = 'diary-button';
            this.dom.errorDisplay.classList.add('hidden');

            switch (state) {
                case 'recognizing':
                    light.classList.add('recognizing');
                    this.dom.statusText.textContent = '録音中...話してね';
                    toggleBtn.textContent = '作成を完了する';
                    toggleBtn.classList.add('stop-btn');
                    break;
                case 'waiting':
                    light.classList.add('waiting');
                    this.dom.statusText.textContent = 'マイク待機中';
                    toggleBtn.textContent = '作成を完了する';
                    toggleBtn.classList.add('stop-btn');
                    break;
                case 'stopped':
                    light.classList.add('stopped');
                    this.dom.statusText.textContent = '停止中';
                    toggleBtn.textContent = '話して絵日記を作成';
                    toggleBtn.classList.add('primary-btn');
                    break;
                case 'error':
                    light.classList.add('stopped');
                    this.dom.statusText.textContent = 'エラー';
                    this.dom.errorMessage.textContent = message;
                    this.dom.errorDisplay.classList.remove('hidden');
                    toggleBtn.textContent = '再試行';
                    toggleBtn.classList.add('primary-btn');
                    break;
            }
        },

        handleUnsupportedBrowser() {
            this.dom.toggleBtn.disabled = true;
            this.dom.clearBtn.disabled = true;
            this.updateUI('error', 'お使いのブラウザは音声認識に対応していません。');
            this.dom.toggleBtn.textContent = '非対応';
        }
    };

    VoiceApp.init();
});