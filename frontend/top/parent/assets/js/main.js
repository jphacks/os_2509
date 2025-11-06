(() => {
  'use strict';

  // ===== DOM =====
  const yEl     = document.getElementById('y');
  const titleEl = document.getElementById('title');
  const imageEl = document.getElementById('entryImage');
  const textEl  = document.getElementById('entryText');
  const noImageEl = document.getElementById('noImage');
  const backBtn = document.getElementById('backBtn');
  const linesEl = document.getElementById('noteLines');
  const noteEl  = document.getElementById('note');
  const sheet   = document.getElementById('sheet');
  const book    = document.getElementById('book');
  const paper   = document.getElementById('paper');

  if (yEl) yEl.textContent = new Date().getFullYear();

  // ===== URL params =====
  const params   = new URLSearchParams(location.search || "");
  const paramId  = params.get('id');
  const paramDate= params.get('date'); // YYYY-MM-DD 期待

  // ===== 状態（history.stateに依存しない）=====
  let currentDate = paramDate || null;  // 画面が“いま”示す日付の真実
  const CALENDAR_URL = '../calendar/calendar.html'; // 戻る先は常にここ

  // ===== ユーティリティ =====
  const dowList = ['日','月','火','水','木','金','土'];
  function formatEntryDate(dateStr){
    try{
      const d = new Date(dateStr);
      const m = d.getMonth()+1, day = d.getDate(), dow = dowList[d.getDay()];
      return `${m}月${day}日<span class="dow">${dow}曜日</span>`;
    }catch{ return '日付不明<span class="dow">?曜日</span>'; }
  }
  function esc(s){ return String(s ?? '').replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
  function ymdShift(yyyy_mm_dd, delta){
    const [y,m,d] = yyyy_mm_dd.split('-').map(v=>parseInt(v,10));
    const base = new Date(y, m-1, d);
    base.setDate(base.getDate()+delta);
    const yy = base.getFullYear(), mm = String(base.getMonth()+1).padStart(2,'0'), dd = String(base.getDate()).padStart(2,'0');
    return `${yy}-${mm}-${dd}`;
  }
  function setURLDate(dateStr, {replace=false}={}){
    const u = new URL(location.href);
    u.searchParams.set('date', dateStr);
    u.searchParams.delete('id');
    if (replace) history.replaceState({}, '', u.pathname + u.search);
    else history.pushState({}, '', u.pathname + u.search);
  }

  // ===== 画像（プリロード＋キャッシュバスター）=====
  async function setImageFromURL(u){
    if(!u){
      imageEl?.removeAttribute('src');
      noImageEl?.classList.remove('hidden');
      imageEl?.classList.add('hidden');
      return false;
    }
    const src = u + (u.includes('?') ? '&' : '?') + 't=' + Date.now();
    return new Promise((resolve)=>{
      const pre = new Image();
      pre.decoding = 'async';
      pre.referrerPolicy = 'no-referrer';
      pre.onload  = () => { if(imageEl){ imageEl.src = src; imageEl.classList.remove('hidden'); } noImageEl?.classList.add('hidden'); resolve(true); };
      pre.onerror = () => { imageEl?.removeAttribute('src'); imageEl?.classList.add('hidden'); noImageEl?.classList.remove('hidden'); resolve(false); };
      pre.src = src;
    });
  }

  // ===== エラー表示（ベース準拠＋縦線OFF）=====
  function showEntryError(message='絵日記が見つかりません'){
    if (titleEl) titleEl.innerHTML = 'エラー';
    if (noImageEl){ noImageEl.textContent = message; noImageEl.classList.remove('hidden'); }
    imageEl?.classList.add('hidden');
    if (textEl) textEl.textContent = 'データがありませんでした。';
    if (linesEl){ linesEl.style.display='none'; linesEl.style.backgroundImage='none'; }
  }

  // ===== エントリ非存在時の描画（要求の日付は反映）=====
  function renderNoEntry(forDate){
    if (titleEl) titleEl.innerHTML = formatEntryDate(forDate);
    imageEl?.removeAttribute('src');
    imageEl?.classList.add('hidden');
    noImageEl?.classList.remove('hidden');
    if (textEl) textEl.textContent = '（本文はありません）';
    if (linesEl){ linesEl.style.display='none'; linesEl.style.backgroundImage='none'; }
  }

  // ===== API =====
  async function fetchEntryBy({id=null,date=null}){
    const url = new URL('get_entry.php', location.href);
    if (id)   url.searchParams.set('id', id);
    if (date) url.searchParams.set('date', date);
    url.searchParams.set('ts', Date.now());
    const res = await fetch(url.toString(), {cache:'no-store'});
    if (!res.ok) throw new Error('HTTP '+res.status);
    return res.json(); // { ok:boolean, entry?:{date,image,sentence}, message? }
  }

  // ===== 縦線 =====
  const LINES_MODE = 'follow'; // or 'percent'
  const PCT_STEP = 5;
  const LINE_MIN_GAP_PX = 10;

  function drawNoteLines(){
    if(!noteEl || !linesEl || !textEl || linesEl.style.display==='none') return;

    const rectNote = noteEl.getBoundingClientRect();
    const W = rectNote.width, H = rectNote.height;

    let xPositions = [];
    if (LINES_MODE === 'percent'){
      const step = Math.max(1, Math.min(50, PCT_STEP));
      for(let p=0;p<=100;p+=step) xPositions.push(Math.round(W*(p/100)));
    }else{
      const paras = Array.from(textEl.querySelectorAll('p'));
      paras.forEach(p=>{
        const range = document.createRange();
        range.selectNodeContents(p);
        const rects = Array.from(range.getClientRects());
        rects.forEach(r => xPositions.push(Math.round(r.left - rectNote.left)));
        range.detach();
      });
      xPositions.sort((a,b)=>a-b);
      const merged=[]; for(const x of xPositions){ if(!merged.length || Math.abs(x-merged[merged.length-1])>=LINE_MIN_GAP_PX) merged.push(x); }
      xPositions = merged.length ? merged : [0, Math.round(W*0.33), Math.round(W*0.66), W];
      if(!xPositions.includes(0)) xPositions.unshift(0);
      if(!xPositions.includes(W)) xPositions.push(W);
    }

    const cs = getComputedStyle(document.documentElement);
    const lineColor = cs.getPropertyValue('--note-line-color').trim() || 'rgba(0,0,80,.2)';
    const lineW = parseFloat(cs.getPropertyValue('--note-line-w')) || 1;

    const images=[], positions=[], sizes=[];
    xPositions.forEach(x=>{
      images.push(`linear-gradient(90deg, ${lineColor} 0, ${lineColor} ${lineW}px, transparent ${lineW}px)`);
      positions.push(`${x}px 0`);
      sizes.push(`${lineW}px ${H}px`);
    });
    linesEl.style.backgroundImage = images.join(',');
    linesEl.style.backgroundRepeat= 'no-repeat' + (images.length>1 ? (', no-repeat').repeat(images.length-1):'');
    linesEl.style.backgroundPosition = positions.join(',');
    linesEl.style.backgroundSize = sizes.join(',');
  }
  addEventListener('resize', ()=>requestAnimationFrame(drawNoteLines), {passive:true});
  if (document.fonts && document.fonts.ready){ document.fonts.ready.then(()=>drawNoteLines()); }

  // ===== 描画 =====
  async function renderEntry(entry){
    // entry あり：内容描画し currentDate を“実データの日付”に更新
    currentDate = (entry.date || '').slice(0,10) || currentDate;
    if (titleEl) titleEl.innerHTML = formatEntryDate(currentDate);

    // 本文
    const body = (entry.sentence || '').trim();
    if (textEl){
      if (body){
        textEl.innerHTML = body.split(/\n+/).map(p=>`<p>${esc(p)}</p>`).join('');
        if (linesEl){ linesEl.style.display='block'; requestAnimationFrame(drawNoteLines); }
      }else{
        textEl.textContent = '（本文はありません）';
        if (linesEl){ linesEl.style.display='none'; linesEl.style.backgroundImage='none'; }
      }
    }

    // 画像
    await setImageFromURL(entry.image);
  }

  // ===== 戻るボタン（常にカレンダーへ）=====
  if (backBtn){
    backBtn.addEventListener('click', (e)=>{
      e.preventDefault();
      window.location.href = '../calendar/calendar.html';
    });
  }

  // ===== “めくり”アニメ =====
  function peelFromCorner(corner /* 'left'|'right' */, after){
    if (!sheet) return;
    const curl = document.createElement('div');
    curl.className = 'page-curl ' + (corner === 'left' ? 'left anim-left' : 'right anim-right');
    sheet.appendChild(curl);
    curl.addEventListener('animationend', ()=>{ curl.remove(); after && after(); }, {once:true});
  }

  // ===== 日付更新 =====
  async function updateTo(dateStr, animateFrom /* 'left'|'right' */){
    if (!dateStr) return;

    currentDate = dateStr;
    if (titleEl) titleEl.innerHTML = formatEntryDate(dateStr);
    setURLDate(dateStr, {replace:false});

    if (paper) paper.classList.add('fade-out');

    try{
      const data = await fetchEntryBy({date: dateStr});
      const doRender = async ()=>{
        if (data.ok && data.entry) await renderEntry(data.entry);
        else renderNoEntry(dateStr);
        paper?.classList.remove('fade-out');
        paper?.classList.add('fade-in');
        setTimeout(()=>paper?.classList.remove('fade-in'), 180);
      };
      peelFromCorner(animateFrom, doRender);
    }catch{
      paper?.classList.remove('fade-out');
    }
  }

  // ===== スワイプ =====
  function navigateSwipe(dx){
    const base = currentDate || (paramDate || new Date().toISOString().slice(0,10));
    const next = dx < 0 ? ymdShift(base, +1) : ymdShift(base, -1);
    updateTo(next, dx < 0 ? 'right' : 'left');
  }
  (function enableSwipe(){
    if (!book) return;
    let startX=0, startY=0, tracking=false;
    const THRESHOLD=40, V_LIMIT=60;
    function onStart(e){ const p=e.touches?e.touches[0]:e; startX=p.clientX; startY=p.clientY; tracking=true; }
    function onMove(e){
      if(!tracking) return;
      const p=e.touches?e.touches[0]:e;
      const dx=p.clientX-startX, dy=p.clientY-startY;
      if(Math.abs(dy)>V_LIMIT){ tracking=false; return; }
      if(Math.abs(dx)>THRESHOLD){ tracking=false; navigateSwipe(dx); }
    }
    function onEnd(){ tracking=false; }
    book.addEventListener('touchstart', onStart, {passive:true});
    book.addEventListener('touchmove',  onMove,  {passive:true});
    book.addEventListener('touchend',   onEnd,   {passive:true});
    book.addEventListener('mousedown',  (e)=>onStart(e));
    book.addEventListener('mousemove',  (e)=>onMove(e));
    book.addEventListener('mouseup',    onEnd);
    book.addEventListener('mouseleave', onEnd);
  })();

  // ===== popstate =====
  window.addEventListener('popstate', async ()=>{
    const p = new URLSearchParams(location.search);
    const d = p.get('date');
    if (!d) return;
    currentDate = d;
    if (titleEl) titleEl.innerHTML = formatEntryDate(d);
    try{
      const data = await fetchEntryBy({date:d});
      if (data.ok && data.entry) await renderEntry(data.entry);
      else renderNoEntry(d);
    }catch{}
  });

  // ===== 初期ロード =====
  (async function init(){
    if (yEl) yEl.textContent = new Date().getFullYear();
    if (paramDate && titleEl) titleEl.innerHTML = formatEntryDate(paramDate);

    try{
      let resp;
      if (paramId)       resp = await fetchEntryBy({id: paramId});
      else if (paramDate)resp = await fetchEntryBy({date: paramDate});
      else { return; }

      if (resp.ok && resp.entry){
        await renderEntry(resp.entry);
        currentDate = (resp.entry.date || '').slice(0,10) || paramDate || currentDate;
        if (currentDate) setURLDate(currentDate, {replace:true});
      }else{
        const d = paramDate || currentDate;
        if (d) renderNoEntry(d);
      }
    }catch(e){
      console.error('絵日記データの取得に失敗:', e);
      const d = paramDate || currentDate;
      if (d) renderNoEntry(d);
      else showEntryError('データの読み込みに失敗しました');
    }
  })();
})();
