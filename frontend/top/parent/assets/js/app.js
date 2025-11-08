(() => {
  'use strict';

  const API_BASE = '/frontend/top/calendar/entry_api.php';

  // ===== 年度 =====
  const yEl = document.getElementById('y');
  if (yEl) yEl.textContent = new Date().getFullYear();

  // ===== URL パラメータ =====
  const q = new URLSearchParams(location.search || "");
  const paramId   = q.get('id');          // 例: "4"
  const paramDate = q.get('date');        // 例: "2025-10-19"

  // ===== DOM =====
  const title = document.getElementById('title');
  const img   = document.getElementById('entryImage');
  const text  = document.getElementById('entryText');
  const lines = document.getElementById('noteLines');
  const noImg = document.getElementById('noImage');
  const sheet = document.getElementById('sheet');
  const book  = document.getElementById('book');
  const paper = document.getElementById('paper');

  // ===== 状態 =====
  let currentDate = null;  // "YYYY-MM-DD"
  let currentId   = null;  // 数字 or null

  // ===== ユーティリティ =====
  function ymdFromDateObj(d){
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const dd = String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${dd}`;
  }
  function titleHTML(yyyy_mm_dd){
    if (!yyyy_mm_dd) return '—月—日<span class="dow">—曜日</span>';
    const [y,m,d] = yyyy_mm_dd.split('-').map(n=>parseInt(n,10));
    const w = '日月火水木金土'[new Date(y, m-1, d).getDay()];
    return `${m}月${d}日<span class="dow">${w}曜日</span>`;
  }
  function esc(s){ return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  // ===== API =====
  async function fetchEntry({id=null, date=null}){
    const url = new URL(API_BASE, location.origin);
    if (id)   url.searchParams.set('id', id);
    if (date) url.searchParams.set('date', date);
    url.searchParams.set('ts', Date.now());

    const res = await fetch(url.toString(), { cache:'no-store', credentials:'same-origin' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'unknown error');

    // 期待形式：
    // { ok:true, id:4, date:"2025-10-19", sentence:"...", image_url:"..." | null, image_data:"data:image/png;base64,..." | null }
    return json;
  }

  // ===== 描画 =====
  function clearView(){
    if (title) title.innerHTML = titleHTML(null);
    if (img) img.removeAttribute('src');
    if (noImg) noImg.classList.remove('hidden');
    if (text) text.innerHTML = '';
    if (lines){
      lines.style.display = 'none';
      lines.style.backgroundImage = 'none';
    }
  }

  function renderFromData(data){
    // data は null のこともある
    if (!data){
      clearView();
      return;
    }

    currentDate = (data.date || '').trim().slice(0, 10) || null;
    currentId   = data.id ?? null;

    if (title) title.innerHTML = titleHTML(currentDate);

    const imageSrc = data.image_url || data.image_data || '';
    if (imageSrc){
      if (img) img.src = imageSrc;
      if (noImg) noImg.classList.add('hidden');
    }else{
      if (img) img.removeAttribute('src');
      if (noImg) noImg.classList.remove('hidden');
    }

    const body = (data.sentence || '').trim();
    if (text){
      const ps = body ? body.split(/\n+/).map(p=>`<p>${esc(p)}</p>`).join('') : '';
      text.innerHTML = ps;
    }

    if (lines){
      if (body){
        lines.style.display = 'block';
        requestAnimationFrame(drawNoteLines);
      }else{
        lines.style.display = 'none';
        lines.style.backgroundImage = 'none';
      }
    }
  }

  // ===== 縦線（本文に追随） =====
  const LINES_MODE = 'follow';        // 'follow' | 'percent'
  const PCT_STEP = 5;
  const LINE_MIN_GAP_PX = 10;

  function drawNoteLines(){
    const noteEl = document.getElementById('note');
    if(!noteEl || !lines || !text || lines.style.display === 'none') return;

    const rectNote = noteEl.getBoundingClientRect();
    const W = rectNote.width;
    const H = rectNote.height;

    let xPositions = [];

    if(LINES_MODE === 'percent'){
      const step = Math.max(1, Math.min(50, PCT_STEP));
      for(let p = 0; p <= 100; p += step){
        xPositions.push(Math.round(W * (p / 100)));
      }
    }else{
      const paras = Array.from(text.querySelectorAll('p'));
      paras.forEach(p=>{
        const range = document.createRange();
        range.selectNodeContents(p);
        const rects = Array.from(range.getClientRects());
        rects.forEach(r=>{
          const x = Math.round(r.left - rectNote.left);
          xPositions.push(x);
        });
        range.detach();
      });
      xPositions.sort((a,b)=>a-b);
      const merged = [];
      for(const x of xPositions){
        if(merged.length === 0 || Math.abs(x - merged[merged.length-1]) >= LINE_MIN_GAP_PX){
          merged.push(x);
        }
      }
      xPositions = merged;
      if(xPositions.length === 0){
        xPositions = [0, Math.round(W*0.33), Math.round(W*0.66), W];
      }else{
        if(!xPositions.includes(0)) xPositions.unshift(0);
        if(!xPositions.includes(W)) xPositions.push(W);
      }
    }

    const cs = getComputedStyle(document.documentElement);
    const lineColor = cs.getPropertyValue('--note-line-color').trim() || 'rgba(0,0,80,.2)';
    const lineW = parseFloat(cs.getPropertyValue('--note-line-w')) || 1;

    const images = [], positions = [], sizes = [];
    xPositions.forEach(x=>{
      images.push(`linear-gradient(90deg, ${lineColor} 0, ${lineColor} ${lineW}px, transparent ${lineW}px)`);
      positions.push(`${x}px 0`);
      sizes.push(`${lineW}px ${H}px`);
    });

    lines.style.backgroundImage = images.join(',');
    lines.style.backgroundRepeat = 'no-repeat' + (images.length>1 ? (', no-repeat').repeat(images.length-1) : '');
    lines.style.backgroundPosition = positions.join(',');
    lines.style.backgroundSize = sizes.join(',');
  }

  // ===== “めくり”演出と画面切替 =====
  function peelFromCorner(corner /* 'right' | 'left' */, after){
    if (!sheet) return;
    const curl = document.createElement('div');
    curl.className = 'page-curl ' + (corner === 'left' ? 'left anim-left' : 'right anim-right');
    sheet.appendChild(curl);
    curl.addEventListener('animationend', ()=>{
      curl.remove();
      after && after();
    }, { once:true });
  }

  async function updateTo(dateStr, animateFrom /* 'left'|'right' */){
    if (!paper) return;
    paper.classList.add('fade-out');

    // APIで再取得（その日付にデータがなければ null を描画）
    let data = null;
    try{
      const json = await fetchEntry({date: dateStr});
      data = json || null;
    }catch(_){ data = null; }

    const doRender = ()=> {
      renderFromData(data);
      paper.classList.remove('fade-out');
      paper.classList.add('fade-in');
      setTimeout(()=>paper.classList.remove('fade-in'), 250);
    };
    peelFromCorner(animateFrom, doRender);

    const u = new URL(location.href);
    u.searchParams.set('date', dateStr);
    u.searchParams.delete('id');
    history.pushState({date: dateStr}, '', u.pathname + u.search);
  }

  function shiftDate(yyyy_mm_dd, delta){
    const [y,m,d] = yyyy_mm_dd.split('-').map(n=>parseInt(n,10));
    const base = new Date(y, m-1, d);
    base.setDate(base.getDate() + delta);
    return ymdFromDateObj(base);
  }

  function navigateSwipe(dx){
    const cur = currentDate || ymdFromDateObj(new Date());
    if(dx < 0){
      const next = shiftDate(cur, +1);
      updateTo(next, 'right');
    }else{
      const prev = shiftDate(cur, -1);
      updateTo(prev, 'left');
    }
  }

  // ===== 初期ロード =====
  async function init(){
    // 戻る
    const backBtn = document.getElementById('backBtn');
    if (backBtn){
      backBtn.addEventListener('click', (e)=>{
        e.preventDefault();
        if (history.length > 1) history.back();
        else window.location.href = '/frontend/top/calendar/calendar.html';
      });
    }

    // スワイプ
    if (book){
      let startX=0, startY=0, tracking=false;
      const THRESHOLD = 40, V_LIMIT=60;
      function onStart(e){
        const p = e.touches ? e.touches[0] : e;
        startX = p.clientX; startY = p.clientY; tracking = true;
      }
      function onMove(e){
        if(!tracking) return;
        const p = e.touches ? e.touches[0] : e;
        const dx = p.clientX - startX;
        const dy = p.clientY - startY;
        if (Math.abs(dy) > V_LIMIT) { tracking=false; return; }
        if (Math.abs(dx) > THRESHOLD){
          tracking=false; navigateSwipe(dx);
        }
      }
      function onEnd(){ tracking=false; }
      book.addEventListener('touchstart', onStart, {passive:true});
      book.addEventListener('touchmove',  onMove,  {passive:true});
      book.addEventListener('touchend',   onEnd,   {passive:true});
      book.addEventListener('mousedown',  (e)=>onStart(e));
      book.addEventListener('mousemove',  (e)=>onMove(e));
      book.addEventListener('mouseup',    onEnd);
      book.addEventListener('mouseleave', onEnd);
    }

    // 初回データ取得
    try{
      let json;
      if (paramId){
        json = await fetchEntry({id: paramId});
      } else if (paramDate){
        json = await fetchEntry({date: paramDate});
      } else {
        clearView();
        return;
      }
      renderFromData(json);
      // URLを date 優先に正規化
      if (json && json.date){
        const u = new URL(location.href);
        u.searchParams.set('date', json.date.slice(0,10));
        u.searchParams.delete('id');
        history.replaceState({date: json.date.slice(0,10)}, '', u.pathname + u.search);
      }
    }catch(err){
      console.error('entry_api error:', err);
      clearView();
    }

    // 文字ロード後の縦線再描画
    addEventListener('resize', ()=>requestAnimationFrame(drawNoteLines), { passive:true });
    if (document.fonts && document.fonts.ready){ document.fonts.ready.then(()=>drawNoteLines()); }

    // 履歴で戻った時
    window.addEventListener('popstate', async (ev)=>{
      const d = (ev.state && ev.state.date) ? ev.state.date : currentDate;
      if (!d) return;
      try{
        const json = await fetchEntry({date: d});
        renderFromData(json || null);
      }catch(_){
        renderFromData(null);
      }
    });
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init, {once:true});
  } else {
    init();
  }

})();