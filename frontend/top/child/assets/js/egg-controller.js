/**
 * 卵割れアニメーション制御
 * 音声認識の文字数に応じて卵が段階的に割れる
 * 40文字でdb5テーブルからランダムに動物GIFを表示
 */

class EggController {
  constructor() {
    this.eggShell = document.getElementById('egg-shell');
    this.hatchedChick = document.getElementById('hatched-chick');
    this.shellPieces = document.getElementById('shell-pieces');
    this.charCountDisplay = document.getElementById('char-count');
    this.progressDots = document.querySelectorAll('.progress-dots .dot');
    
    // ========================================
    // 🎯 ここを変更するだけで全ての閾値が変わります！
    // ========================================
    this.thresholds = [1, 2, 3];  // ← この数値を変更してください
    this.hatchThreshold = 10;        // 🆕 孵化する文字数（別途設定）
    // 例: [10, 20, 30] にすると10文字ごとに割れます
    // 例: [50, 100, 150] にすると50文字ごとに割れます
    // ========================================
    
    this.currentStage = 0;
    this.isHatched = false;
    
    this.init();
  }

  init() {
    // MutationObserverで認識結果を監視
    this.observeTranscript();
    
    // クリアボタンのイベントを監視
    this.observeClearButton();
    
    console.log('🥚 卵コントローラーを初期化しました');
    console.log(`📊 閾値: ${this.thresholds.join('文字, ')}文字, ${this.thresholds[2]}文字超で孵化`);
    console.log(`🐣 孵化: ${this.hatchThreshold}文字`);
  }

  /**
   * 音声認識結果のテキストを監視
   */
  observeTranscript() {
    const finalTranscript = document.getElementById('final-transcript');
    const interimTranscript = document.getElementById('interim-transcript');

    if (!finalTranscript) {
      console.warn('transcript要素が見つかりません');
      return;
    }

    // MutationObserverで変更を監視
    const observer = new MutationObserver(() => {
      this.updateCharacterCount();
    });

    observer.observe(finalTranscript, {
      characterData: true,
      childList: true,
      subtree: true
    });

    if (interimTranscript) {
      observer.observe(interimTranscript, {
        characterData: true,
        childList: true,
        subtree: true
      });
    }

    // 定期的にもチェック（念のため）
    setInterval(() => {
      this.updateCharacterCount();
    }, 500);
  }

  /**
   * クリアボタンを監視してリセット
   */
  observeClearButton() {
    const clearBtn = document.getElementById('clear-btn');
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        setTimeout(() => {
          this.reset();
        }, 100);
      });
    }
  }

  /**
   * 文字数をカウントして卵の状態を更新
   */
  updateCharacterCount() {
    const finalText = document.getElementById('final-transcript')?.textContent || '';
    const interimText = document.getElementById('interim-transcript')?.textContent || '';
    const totalText = finalText + interimText;
    const charCount = totalText.length;

    // 文字数表示を更新
    if (this.charCountDisplay) {
      this.charCountDisplay.textContent = charCount;
    }

    // 進捗ドットを更新
    this.updateProgressDots(charCount);

    // 卵の状態を更新
    this.updateEggStage(charCount);
  }

  /**
   * 進捗ドットの表示を更新
   */
  updateProgressDots(charCount) {
    this.progressDots.forEach((dot, index) => {
      const threshold = this.thresholds[index];
      if (charCount >= threshold) {
        dot.classList.add('active');
      } else {
        dot.classList.remove('active');
      }
    });
  }

  /**
   * 卵の段階を更新（閾値配列に基づいて自動判定）
   */
  updateEggStage(charCount) {
    let newStage = 0;

    // 閾値配列に基づいて段階を判定
    if (charCount >= this.hatchThreshold) {
      newStage = 4;
    } else if (charCount >= this.thresholds[2]) {
      newStage = 3;
    } else if (charCount >= this.thresholds[1]) {
      newStage = 2;
    } else if (charCount >= this.thresholds[0]) {
      newStage = 1;
    }

    // 段階が変わった時のみ処理
    if (newStage !== this.currentStage) {
      this.currentStage = newStage;
      this.applyStage(newStage);
    }
  }

  /**
   * 卵の段階を適用
   */
  applyStage(stage) {
    if (!this.eggShell) return;

    // 既存のクラスをクリア
    this.eggShell.classList.remove('stage-1', 'stage-2', 'stage-3', 'hatching');

    if (stage === 4) {
      // 孵化
      this.hatch();
    } else if (stage > 0) {
      // ひび割れ段階
      this.eggShell.classList.add(`stage-${stage}`);
      console.log(`🥚 卵が割れました: ステージ ${stage} (${this.thresholds[stage - 1]}文字)`);
      
      // 振動効果（最終段階のみ）
      if ('vibrate' in navigator && stage === 3) {
        navigator.vibrate(200);
      }
    }
  }

  /**
   * 卵を孵化させる
   */
  async hatch() {
    if (this.isHatched) return;
    
    console.log(`🐣 卵が孵化しました! (${this.hatchThreshold}文字)`);
    this.isHatched = true;

    // 卵を消す
    if (this.eggShell) {
      this.eggShell.classList.add('hatching');
    }

    // 殻の破片を表示
    setTimeout(() => {
      if (this.shellPieces) {
        this.shellPieces.classList.remove('hidden');
        this.shellPieces.classList.add('show');
      }
    }, 200);

    // ランダムな動物GIFを取得して表示
    setTimeout(async () => {
      await this.showRandomAnimal();
      
      // 振動効果
      if ('vibrate' in navigator) {
        navigator.vibrate([100, 50, 100, 50, 200]);
      }
    }, 400);
  }

  /**
   * db5からランダムに動物GIFを取得して表示
   */
  async showRandomAnimal() {
    try {
      const response = await fetch('/public_html/backend/gifs/get_random_gif.php');
      const result = await response.json();
      
      if (result.success && result.data) {
        const animal = result.data;
        
        if (this.hatchedChick) {
          // GIF URLが存在する場合は画像を表示、なければデフォルトの絵文字
          if (animal.gif_url) {
            this.hatchedChick.innerHTML = `
              <img src="${animal.gif_url}" 
                   alt="${animal.animal_name}" 
                   class="animal-gif"
                   title="${animal.animal_name}">
              <p class="animal-name">${animal.animal_name}</p>
            `;
            console.log(`🎉 ${animal.animal_name}が現れました！`);
          } else {
            // デフォルト（ヒヨコの絵文字）
            this.hatchedChick.innerHTML = '<span class="chick-emoji">🐣</span>';
          }
          
          this.hatchedChick.classList.remove('hidden');
          this.hatchedChick.classList.add('show');
        }
      }
    } catch (error) {
      console.error('動物GIFの取得に失敗しました:', error);
      
      // エラー時はデフォルトのヒヨコを表示
      if (this.hatchedChick) {
        this.hatchedChick.innerHTML = '<span class="chick-emoji">🐣</span>';
        this.hatchedChick.classList.remove('hidden');
        this.hatchedChick.classList.add('show');
      }
    }
  }

  /**
   * 卵をリセット
   */
  reset() {
    console.log('🔄 卵をリセットします');
    
    this.currentStage = 0;
    this.isHatched = false;

    // 卵の表示を元に戻す
    if (this.eggShell) {
      this.eggShell.classList.remove('stage-1', 'stage-2', 'stage-3', 'hatching');
    }

    // ひよこと殻を非表示
    if (this.hatchedChick) {
      this.hatchedChick.classList.remove('show');
      this.hatchedChick.classList.add('hidden');
      // 内容もリセット
      this.hatchedChick.innerHTML = '<span class="chick-emoji">🐣</span>';
    }

    if (this.shellPieces) {
      this.shellPieces.classList.remove('show');
      this.shellPieces.classList.add('hidden');
    }

    // 文字数表示をリセット
    if (this.charCountDisplay) {
      this.charCountDisplay.textContent = '0';
    }

    // 進捗ドットをリセット
    this.progressDots.forEach(dot => {
      dot.classList.remove('active');
    });
  }
}

// ページ読み込み時に初期化
document.addEventListener('DOMContentLoaded', () => {
  // 少し遅延させて他のスクリプトが読み込まれるのを待つ
  setTimeout(() => {
    window.eggController = new EggController();
  }, 100);
});

// デバッグ用（開発時のみ）
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
  window.testEgg = {
    setChars: (count) => {
      const finalTranscript = document.getElementById('final-transcript');
      if (finalTranscript) {
        finalTranscript.textContent = 'あ'.repeat(count);
      }
    },
    reset: () => {
      if (window.eggController) {
        window.eggController.reset();
      }
    }
  };
  console.log('🧪 デバッグモード: testEgg.setChars(文字数) でテストできます');
}