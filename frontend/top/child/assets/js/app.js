/*
 * Web Speech API を使用した音声認識ロジック
 * file: app.js
 * (位置情報取得機能を追加)
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

        // // --- 状態管理 ---
        // state: {
        //     isRecognizing: false, // アプリとして認識中かどうかの状態
        //     finalTranscript: '',
        //     ignoreOnend: false, // 意図的な停止時に onend を無視するフラグ
        //     currentLatitude: null,  // (追加) 現在の緯度
        //     currentLongitude: null, // (追加) 現在の経度
        // },

        // --- 状態管理 ---
        state: {
            isRecognizing: false, // アプリとして認識中かどうかの状態
            finalTranscript: '',
            ignoreOnend: false, // 意図的な停止時に onend を無視するフラグ
            currentLatitude: null,  // (追加) 現在の緯度
            currentLongitude: null, // (追加) 現在の経度
            locationIntervalId: null, // (★ これを追加 ★)
        },

        recognition: null,

        init() {
            this.updateUI('stopped');

            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                this.handleUnsupportedBrowser();
                return;
            }

            // (追加) 位置情報が使えるかも確認
            if (!navigator.geolocation) {
                console.warn("このブラウザは位置情報取得に対応していません。");
                // エラー表示も可能だが、ここではコンソール警告のみ
            }

            this.recognition = new SpeechRecognition();
            this.configureRecognition();
            this.bindEvents();
            console.log("音声認識アプリの初期化が完了しました。");
        },

        configureRecognition() {
            this.recognition.lang = this.config.lang;
            this.recognition.interimResults = true;
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
                
                // (変更) 'not-allowed' (マイク拒否) の場合、位置情報拒否も考慮
                // let errorMsg = `エラー: ${event.error}`;
                // if (event.error === 'not-allowed') {
                //     errorMsg = 'マイクの使用が許可されていません。ブラウザの設定を確認してください。';
                // }

            // ★ ここから修正・追加 ★
            if (event.error === 'aborted' && this.state.ignoreOnend) {
                // 意図的な stopRecognition() の後に発生した aborted エラーを無視
                console.log("意図的な中断による 'aborted' エラーを無視しました。");
                this.updateUI('stopped'); // UIだけ停止状態に戻す
                this.state.ignoreOnend = false;
                return;
            }
            // ★ ここまで修正・追加 ★

                this.updateUI('error', errorMsg);
                this.state.ignoreOnend = true;
            };

            this.recognition.onresult = (event) => {
                this.handleRecognitionResult(event);
            };
        },

        // --- (関数追加) 位置情報取得ロジック ---
        getLocation() {
            // 以前の位置情報をクリア
            this.state.currentLatitude = null;
            this.state.currentLongitude = null;

            if (navigator.geolocation) {
                console.log("位置情報を取得中...");
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.state.currentLatitude = position.coords.latitude;
                        this.state.currentLongitude = position.coords.longitude;
                        console.log(`位置情報取得成功: ${this.state.currentLatitude}, ${this.state.currentLongitude}`);
                    },
                    (error) => {
                        // 位置情報取得が失敗または拒否されても、音声認識は続行
                        console.warn(`位置情報の取得に失敗しました: ${error.message}`);
                        let errorMsg = `位置情報の取得に失敗 (${error.code})。`;
                        if (error.code === 1) { // PERMISSION_DENIED
                           errorMsg = '位置情報の使用が許可されていません。日記には位置が記録されません。';
                        }
                        // UIにエラーを短時間表示するなども可能
                    },
                    {
                        enableHighAccuracy: true, // 高精度
                        timeout: 10000,           // 10秒タイムアウト
                        maximumAge: 0             // キャッシュを使用しない
                    }
                );
            } else {
                console.warn("このブラウザは位置情報取得に対応していません。");
            }
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
            
            // // --- (追加) 認識開始時に位置情報を取得 ---
            // this.getLocation(); // ← 1秒間隔で取得するため、これはコメントアウトでOKです
            // // ----------------------------------------

            // --- (★追加) 1秒ごとの位置情報保存を開始 ---
            if (this.state.locationIntervalId) {
                clearInterval(this.state.locationIntervalId);
            }
            // 1000ms = 1秒
            this.state.locationIntervalId = setInterval(() => {
                this.sendPeriodicLocation();
            }, 1000); 
            // ----------------------------------------

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

                // --- (★ 修正点 ★) ---
                // エラー時にも必ずインターバルを停止する
                if (this.state.locationIntervalId) {
                    clearInterval(this.state.locationIntervalId);
                    this.state.locationIntervalId = null;
                }
                // -------------------------
            }
        },

        stopRecognition() {
            if (!this.state.isRecognizing) return;

            // --- (★ 修正点 ★) ---
            // 1秒ごとの保存を停止
            if (this.state.locationIntervalId) {
                clearInterval(this.state.locationIntervalId);
                this.state.locationIntervalId = null;
            }
            // -------------------------

            this.state.isRecognizing = false;
            this.state.ignoreOnend = true;

            // 確定テキストをサーバーに送信
            const currentText = this.state.finalTranscript + (this.dom.interimTranscript.textContent || '');
            
            // --- (変更) テキストがある場合のみ送信処理 ---
            if (currentText.trim().length > 0) {
                // (変更) sendDataToPHP を呼び出す
                this.sendDataToPHP(currentText.trim());
                
                this.dom.finalTranscript.textContent = currentText.trim();
                this.dom.interimTranscript.textContent = '';
                this.state.finalTranscript = currentText.trim();
            } else {
                // テキストがない場合は、位置情報もリセット
                this.state.currentLatitude = null;
                this.state.currentLongitude = null;
            }
            // --------------------------------------------

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

        // // --- (変更) 関数名を変更し、位置情報も送信 ---
        // sendDataToPHP(text) {
        //     console.log(`サーバーにデータを送信中...`);
            
        //     const latitude = this.state.currentLatitude;
        //     const longitude = this.state.currentLongitude;

        //     // FormData (URLSearchParams) を使ってデータを準備
        //     const formData = new URLSearchParams();
        //     formData.append('sound_text', text);

        //     // 位置情報が取得できている場合のみ追加
        //     if (latitude !== null && longitude !== null) {
        //         formData.append('latitude', latitude);
        //         formData.append('longitude', longitude);
        //         console.log(`送信データ: テキストあり, 位置情報 (${latitude}, ${longitude})`);
        //     } else {
        //         console.log(`送信データ: テキストあり, 位置情報なし`);
        //     }

        //     // (変更) 送信先PHPファイルを 'save_data.php' に
        //     fetch('save_data.php', { 
        //         method: 'POST',
        //         headers: {
        //             'Content-Type': 'application/x-www-form-urlencoded',
        //         },
        //         body: formData.toString() // URLSearchParams を文字列化
        //     })
        //         .then(response => {
        //             if (!response.ok) {
        //                 console.error('サーバー応答エラー:', response.status);
        //             }
        //             return response.json(); // (変更) PHPからJSONで応答を受け取る
        //         })
        //         .then(data => {
        //             console.log('サーバー応答詳細:', data);
        //         })
        //         .catch(error => {
        //             console.error('データの送信中にエラーが発生しました:', error);
        //         });
            
        //     // 送信後、位置情報をリセット
        //     this.state.currentLatitude = null;
        //     this.state.currentLongitude = null;
        // },
        // --- (変更) 関数名を変更し、位置情報も送信 ---
        sendDataToPHP(text) {
            console.log(`サーバーにデータを送信中...`);
            
            const latitude = this.state.currentLatitude;
            const longitude = this.state.currentLongitude;

            // FormData (URLSearchParams) を使ってデータを準備
            const formData = new URLSearchParams();
            formData.append('sound_text', text);

            // 位置情報が取得できている場合のみ追加
            if (latitude !== null && longitude !== null) {
                formData.append('latitude', latitude);
                formData.append('longitude', longitude);
                console.log(`送信データ: テキストあり, 位置情報 (${latitude}, ${longitude})`);
            } else {
                console.log(`送信データ: テキストあり, 位置情報なし`);
            }

            // --- ▼ ここから修正 ▼ ---

            // (変更) 送信先PHPファイルを 'save_data.php' に
            // STEP 1: まず、テキストと最終位置を保存します
            fetch('save_data.php', { 
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString() // URLSearchParams を文字列化
            })
            .then(response => {
                if (!response.ok) {
                    console.error('サーバー応答エラー (save_data):', response.status);
                    throw new Error('Data save failed'); // エラーを投げて catch に移す
                }
                return response.json(); // (変更) PHPからJSONで応答を受け取る
            })
            .then(data => {
                console.log('サーバー応答詳細 (save_data):', data);
                // STEP 2: 保存が成功したら、重い処理をキックする
                console.log('データ保存成功。バックグラウンド処理をトリガーします...');
                // この fetch は、すぐに {status: 'processing_started'} が返ってくる
                return fetch('run_diary_generation.php', {
                    method: 'POST'
                });
            })
            .then(response => {
                if (!response.ok) {
                    console.error('サーバー応答エラー (run_diary):', response.status);
                    throw new Error('Trigger failed');
                }
                return response.json();
            })
            .then(triggerData => {
                console.log('バックグラウンド処理のトリガー応答:', triggerData);
            })
            .catch(error => {
                // save_data.php か run_diary_generation.php のどちらかで失敗
                console.error('データ保存または処理トリガー中にエラーが発生しました:', error);
            });
        },
        

        sendPeriodicLocation() {
            if (!navigator.geolocation) {
                console.warn("[Interval] Geolocation is not supported.");
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;

                    // --- (★ 修正点 ★) ---
                    // 状態も更新して、最後の保存 (sendDataToPHP) で使えるようにする
                    this.state.currentLatitude = lat;
                    this.state.currentLongitude = lon;
                    // -------------------------

                    console.log(`[Interval] Sending location: ${lat}, ${lon}`);

                    const formData = new URLSearchParams();
                    formData.append('latitude', lat);
                    formData.append('longitude', lon);
                    // ★注: ここでは sound_text は送信しない

                    fetch('save_data.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status !== 'success' || !data.saved.db0_location) {
                            console.warn('[Interval] Failed to save location.', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('[Interval] Error sending location:', error);
                    });
                },
                (error) => {
                    // エラーが出てもUIは止めず、コンソールに警告のみ
                    console.warn(`[Interval] Could not get location: ${error.message}`);
                },
                { 
                    enableHighAccuracy: true, // 高精度
                    timeout: 5000,            // 5秒タイムアウト
                    maximumAge: 0             // キャッシュしない
                }
            );
        },

        handleRecognitionResult(event) {
            let interimTranscript = '';
            for (let i = event.resultIndex; i < event.results.length; ++i) {
                const transcript = event.results[i][0].transcript;
                const confidence = event.results[i][0].confidence;

                if (event.results[i].isFinal) {
                    if (confidence > 0) {
                        // (変更) 句点を自動で追加（文脈に応じて調整可）
                        this.state.finalTranscript += transcript.trim() + '。';
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

            // --- (★ 修正点 ★) ---
            // 1秒ごとの保存を停止
            if (this.state.locationIntervalId) {
                clearInterval(this.state.locationIntervalId);
                this.state.locationIntervalId = null;
            }
            // -------------------------

            this.state.finalTranscript = '';
            this.dom.finalTranscript.textContent = '';
            this.dom.interimTranscript.textContent = '';
            this.dom.errorDisplay.classList.add('hidden');
            this.updateUI('stopped');
            
            // (追加) クリア時に位置情報もリセット
            this.state.currentLatitude = null;
            this.state.currentLongitude = null;
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