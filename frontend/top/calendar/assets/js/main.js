(function () {
  'use strict';

  /* ========= 使い方メモ =========
     - 元画像は 1024x1024 を想定（SRC_SIZE で変更可）
     - 切り抜き座標 {x,y,w,h} は px（元画像座標系）
     - 既定は「左上 512x512」を切り抜き
     - もし特定日/URLの専用座標を使うなら THUMB_CROP_MAP に追記
  ================================= */

  const SRC_SIZE = 1024;
  const DEFAULT_CROP = { x: 0, y: 0, w: 512, h: 512 };

  // 例：テスト用画像URLごとの個別座標（必要に応じて追加）
  // キーはサムネURLの完全一致。日付で分けたい場合は renderMonth 内で dateStr をキーにしてもOK
  const THUMB_CROP_MAP = {
    // 'https://example.com/test_image.png': { x: 24, y: 18, w: 560, h: 560 },
  };

  // 年表記（要素が無い環境もガード）
  const yEl = document.getElementById('y');
  if (yEl) yEl.textContent = new Date().getFullYear();

  // 遷移先
  const ENTRY_PAGE = '/os_2509/frontend/top/parent/parent.html';

  const startOfWeek = 0;
  const dowHead = ['日','月','火','水','木','金','土'];

  let diaryIndex = {};
  let diaryThumbs = {};  // { 'YYYY-MM-DD': 'thumbUrl' }

  function hasDiary(dateStr){ return !!diaryIndex[dateStr] && diaryIndex[dateStr].length > 0; }
  function firstDiaryId(dateStr){
    if (!hasDiary(dateStr)) return null;
    return Math.min(...diaryIndex[dateStr]);
  }

  function eventEmoji(y,m,d){
    const md = `${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    switch(md){
      case '01-01': return '🎍'; case '02-14': return '💝'; case '03-03': return '🎎';
      case '04-29': return '🌸'; case '05-05': return '🎏'; case '07-07': return '🎋';
      case '08-15': return '🏮'; case '09-15': return '🌕'; case '10-31': return '🎃';
      case '12-24': return '🎄'; case '12-31': return '🧧'; default: return '';
    }
  }

  const monthLabel = document.getElementById('monthLabel');
  const dowRow = document.getElementById('dowRow');
  const grid = document.getElementById('grid');
  const board = document.getElementById('board');
  const now = new Date();
  let viewY = now.getFullYear();
  let viewM = now.getMonth(); // 0..11

  function jpMonth(y,m){ return `${y}年${m}月`; }
  function ymd(y,m,d){ return `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`; }

  function renderDow(){
    if (!dowRow) return;
    dowRow.innerHTML = '';
    for(let i=0;i<7;i++){
      const w = (i + startOfWeek) % 7;
      const el = document.createElement('div');
      el.className = 'dow' + (w===0?' sun' : w===6?' sat':'');
      el.textContent = dowHead[w];
      dowRow.appendChild(el);
    }
  }

  function gotoEntry({ id=null, date=null }) {
    const url = new URL(ENTRY_PAGE, location.origin);
    if (id != null)   url.searchParams.set('id', String(id));
    else if (date)    url.searchParams.set('date', date);
    const root = document.querySelector('.page');
    if (root) {
      root.classList.add('leaving');
      setTimeout(()=>{ window.location.href = url.toString(); }, 200);
    } else {
      window.location.href = url.toString();
    }
  }

  /** サムネをセル幅に応じてレイアウトし、left-top を切り抜く */
  function layoutThumbInCell(cell, mark, thumbUrl, crop){
    // セルの内幅に応じてサムネ幅を決定（大きすぎるとかぶる）
    const cellRect = cell.getBoundingClientRect();
    const maxW = cellRect.width * 0.72; // セル幅の72%を上限
    const minW = Math.min(72, cellRect.width * 0.5); // 最低でもこれくらい
    const targetW = Math.max(minW, Math.min(maxW, 80)); // 80px前後に収まるように
    const scale = targetW / crop.w;
    const targetH = Math.round(crop.h * scale);

    // サイズ反映
    mark.style.width  = `${Math.round(targetW)}px`;
    mark.style.height = `${targetH}px`;

    // 切り抜き：背景を原寸→スケール、負オフセットで (x,y) を左上に持ってくる
    mark.style.backgroundImage = `url("${thumbUrl}")`;
    mark.style.backgroundSize  = `${SRC_SIZE * scale}px ${SRC_SIZE * scale}px`;
    mark.style.backgroundPosition = `${-crop.x * scale}px ${-crop.y * scale}px`;
  }

  function renderMonth(y, m){
    if (!grid) return;
    if (board) board.classList.remove('slide-left','slide-right');
    grid.innerHTML = '';
    if (monthLabel) monthLabel.textContent = jpMonth(y, m);

    const first = new Date(y, m-1, 1);
    const lastDate = new Date(y, m, 0).getDate();
    const firstDow = first.getDay();
    const startPad = (firstDow - startOfWeek + 7) % 7;

    for(let i=0;i<startPad;i++){
      const pad = document.createElement('div');
      pad.className = 'cell';
      grid.appendChild(pad);
    }

    for(let d=1; d<=lastDate; d++){
      const wd = (startPad + (d-1)) % 7;
      const cell = document.createElement('div');
      cell.className = 'cell' + (wd===0?' sun' : wd===6?' sat':'');

      const num = document.createElement('div'); num.className='d'; num.textContent=d;
      const emo = document.createElement('div'); emo.className='emo'; emo.textContent = eventEmoji(y,m,d);
      const dateStr = ymd(y,m,d);

      let mark = null;
      if (hasDiary(dateStr)) {
        const thumbUrl = diaryThumbs[dateStr];
        if (thumbUrl) {
          mark = document.createElement('div');
          mark.className = 'thumb-mark';
          mark.setAttribute('aria-label', 'サムネイル');
          mark.setAttribute('role', 'img');
          cell.append(mark); // 先に入れておかないと幅が取れないことがある

          // 個別座標があれば使い、なければ既定（左上）
          const crop = THUMB_CROP_MAP[thumbUrl] || DEFAULT_CROP;

          // 初期レイアウト（描画後にサイズが確定するので rAF で）
          requestAnimationFrame(() => layoutThumbInCell(cell, mark, thumbUrl, crop));

          // リサイズにも追随
          const ro = new ResizeObserver(() => layoutThumbInCell(cell, mark, thumbUrl, crop));
          ro.observe(cell);
        }
      }

      const btn = document.createElement('button');
      btn.setAttribute('aria-label', `${dateStr}を開く`);
      btn.addEventListener('click', () => {
        if (hasDiary(dateStr)) {
          const id = firstDiaryId(dateStr);
          gotoEntry({ id: id ?? null, date: dateStr });
        } else {
          alert('この日付は絵日記が存在しません');
        }
      });

      cell.append(num, emo, btn);
      grid.appendChild(cell);
    }

    const rem = (startPad + lastDate) % 7;
    if(rem !== 0){
      for(let i=rem; i<7; i++){
        const pad = document.createElement('div');
        pad.className = 'cell';
        grid.appendChild(pad);
      }
    }
    layoutRopes();
  }

  function shiftMonth(delta){
    viewM += delta;
    if(viewM < 0){ viewM = 11; viewY--; }
    if(viewM > 11){ viewM = 0;  viewY++; }
    if (board) board.classList.add(delta > 0 ? 'slide-left' : 'slide-right');
    renderMonth(viewY, viewM+1);
  }

  (function attachSwipe(){
    if (!board) return;
    const hintL = document.getElementById('hintLeft');
    const hintR = document.getElementById('hintRight');
    let sx=0, dx=0, down=false; const threshold=40;

    function showHints(){
      if (!hintL || !hintR) return;
      const a=Math.min(1, Math.max(0,   dx/threshold));
      const b=Math.min(1, Math.max(0, -dx/threshold));
      hintL.style.opacity = a>0 ? String(0.2+0.6*a) : '0';
      hintR.style.opacity = b>0 ? String(0.2+0.6*b) : '0';
    }

    board.addEventListener('touchstart', e=>{down=true; sx=e.touches[0].clientX; dx=0;},{passive:true});
    board.addEventListener('touchmove',  e=>{if(!down)return; dx=e.touches[0].clientX - sx; showHints();},{passive:true});
    board.addEventListener('touchend',   ()=>{if(!down)return; down=false; if(dx>threshold)shiftMonth(-1); else if(dx<-threshold)shiftMonth(1);},{passive:true});
  })();

  function layoutRopes(){
    const wrap=document.getElementById('boardWrap');
    const pin=document.getElementById('pin');
    const ropes=document.getElementById('ropes');
    const ropeL=document.getElementById('ropeL');
    const ropeR=document.getElementById('ropeR');
    if(!wrap || !pin || !ropes || !ropeL || !ropeR || !board) return;

    const wrapRect=wrap.getBoundingClientRect();
    const pinRect=pin.getBoundingClientRect();
    const boardRect=board.getBoundingClientRect();
    const svgW=wrapRect.width;
    const svgH=Math.max(boardRect.top - wrapRect.top, 10);
    ropes.setAttribute('viewBox', `0 0 ${svgW} ${svgH}`);
    ropes.style.width=svgW+'px'; ropes.style.height=svgH+'px';
    const pinX=pinRect.left + pinRect.width/2 - wrapRect.left;
    const pinY=pinRect.top + pinRect.height - wrapRect.top;
    const insetX=12, insetY=8;
    const blX=boardRect.left + insetX - wrapRect.left;
    const brX=boardRect.right - insetX - wrapRect.left;
    const bY =boardRect.top + insetY - wrapRect.top;
    ropeL.setAttribute('x1', pinX); ropeL.setAttribute('y1', pinY);
    ropeL.setAttribute('x2', blX ); ropeL.setAttribute('y2', bY  );
    ropeR.setAttribute('x1', pinX); ropeR.setAttribute('y1', pinY);
    ropeR.setAttribute('x2', brX ); ropeR.setAttribute('y2', bY  );
  }

  window.addEventListener('resize', layoutRopes, {passive:true});
  window.addEventListener('orientationchange', () => { setTimeout(layoutRopes, 200); }, {passive:true});

  async function init(){
    renderDow();
    renderMonth(viewY, viewM+1);

    try{
      const url = '/os_2509/frontend/top/calendar/diary_dates.php?ts=' + Date.now();
      const res = await fetch(url, { cache:'no-store', credentials:'same-origin' });
      if(!res.ok){
        console.error('diary_dates HTTP error', res.status, await res.text());
      }else{
        const json = await res.json();
        if(json.ok){
          const normalized = {};
          for(const k of Object.keys(json.days)){
            const k2 = String(k).trim().slice(0,10);
            if(!normalized[k2]) normalized[k2] = [];
            const arr = Array.isArray(json.days[k]) ? json.days[k] : [json.days[k]];
            normalized[k2].push(...arr);
          }
          diaryIndex = normalized;
          diaryThumbs = json.thumbs || {};
          renderMonth(viewY, viewM+1);
        }
      }
    }catch(e){ console.error('diary_dates fetch error', e); }

    layoutRopes();
  }

  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded', init, {once:true});
  } else {
    init();
  }

  document.getElementById('prevBtn')?.addEventListener('click', () => shiftMonth(-1));
  document.getElementById('nextBtn')?.addEventListener('click', () => shiftMonth(1));
})();
