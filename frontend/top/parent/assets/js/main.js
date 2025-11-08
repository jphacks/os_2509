document.getElementById('y').textContent = new Date().getFullYear();

// URL パラメータ
const q = new URLSearchParams(location.search || "");
const paramId   = q.get('id');
const paramDate = q.get('date');

// API
const API = '/public_html/frontend/top/parent/entry_api.php';

// DOM
const title = document.getElementById('title');
const img   = document.getElementById('entryImage');
const text  = document.getElementById('entryText');
const lines = document.getElementById('noteLines');
const noImg = document.getElementById('noImage');
const sheet = document.getElementById('sheet');
const book  = document.getElementById('book');
const paper = document.getElementById('paper');

let currentDate = null;

// 先行でタイトルだけ描画（paramDateがあるなら即表示して「消える」体感をなくす）
if (paramDate) {
    title.innerHTML = titleHTML(paramDate);
    currentDate = paramDate;
}

// Util
function titleHTML(yyyy_mm_dd){
    if(!yyyy_mm_dd) return '—月—日<span class="dow">—曜日</span>';
    const [y,m,d] = yyyy_mm_dd.split('-').map(n=>parseInt(n,10));
    const w = '日月火水木金土'[new Date(y, m-1, d).getDay()];
    return `${m}月${d}日<span class="dow">${w}曜日</span>`;
}
function esc(s){ return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function ymdShift(yyyy_mm_dd, delta){
    const [y,m,d] = yyyy_mm_dd.split('-').map(n=>parseInt(n,10));
    const base = new Date(y, m-1, d);
    base.setDate(base.getDate() + delta);
    const yy = base.getFullYear(), mm = String(base.getMonth()+1).padStart(2,'0'), dd = String(base.getDate()).padStart(2,'0');
    return `${yy}-${mm}-${dd}`;
}

// API: JSON（no-store）
async function fetchEntry({id=null, date=null}){
    const url = new URL(API, location.origin);
    if (id)   url.searchParams.set('id', id);
    if (date) url.searchParams.set('date', date);
    const res = await fetch(url.toString(), { cache:'no-store', credentials:'same-origin' });
    if(!res.ok) throw new Error('HTTP '+res.status);
    const json = await res.json();
    if(!json.ok) throw new Error(json.error || 'unknown error');
    json.image_url = (json.image_url || '').trim() || null;
    return json;
}

// 画像：URL直読み＋キャッシュバスター
async function setImageFromURL(u){
    if (!u) {
    img.removeAttribute('src');
    noImg.classList.remove('hidden');
    return false;
    }
    const src = u + (u.includes('?') ? '&' : '?') + 't=' + Date.now();
    return new Promise((resolve)=>{
    const pre = new Image();
    pre.decoding = 'async';
    pre.onload = () => { img.src = src; noImg.classList.add('hidden'); resolve(true); };
    pre.onerror = () => { img.removeAttribute('src'); noImg.classList.remove('hidden'); resolve(false); };
    pre.referrerPolicy = 'no-referrer';
    pre.src = src;
    });
}

// 描画（空にしない方針）
async function renderFromData(data){
    if(!data) return;

    const d = (data.date || '').slice(0,10) || null;
    if (d) { currentDate = d; title.innerHTML = titleHTML(d); }

    const body = (data.sentence || '').trim();
    if (body){
    text.innerHTML = body.split(/\n+/).map(p=>`<p>${esc(p)}</p>`).join('');
    lines.style.display = 'block';
    requestAnimationFrame(drawNoteLines);
    }else{
    text.innerHTML = '';
    lines.style.display = 'none';
    lines.style.backgroundImage = 'none';
    }

    await setImageFromURL(data.image_url);
}

// 縦線描画
const LINES_MODE = 'follow';
const PCT_STEP = 5;
const LINE_MIN_GAP_PX = 10;

function drawNoteLines(){
    const note = document.getElementById('note');
    if(!note || lines.style.display === 'none') return;
    const rectNote = note.getBoundingClientRect();
    const W = rectNote.width, H = rectNote.height;

    let xPositions = [];
    if(LINES_MODE === 'percent'){
    const step = Math.max(1, Math.min(50, PCT_STEP));
    for(let p = 0; p <= 100; p += step){ xPositions.push(Math.round(W*(p/100))); }
    }else{
    const paras = Array.from(text.querySelectorAll('p'));
    paras.forEach(p=>{
        const range = document.createRange();
        range.selectNodeContents(p);
        const rects = Array.from(range.getClientRects());
        rects.forEach(r=> xPositions.push(Math.round(r.left - rectNote.left)));
        range.detach();
    });
    xPositions.sort((a,b)=>a-b);
    const merged=[]; for(const x of xPositions){ if(merged.length===0 || Math.abs(x-merged[merged.length-1])>=LINE_MIN_GAP_PX) merged.push(x); }
    xPositions = merged.length ? merged : [0, Math.round(W*0.33), Math.round(W*0.66), W];
    if(!xPositions.includes(0)) xPositions.unshift(0);
    if(!xPositions.includes(W)) xPositions.push(W);
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
addEventListener('resize', ()=>requestAnimationFrame(drawNoteLines), { passive:true });
if (document.fonts && document.fonts.ready){ document.fonts.ready.then(()=>drawNoteLines()); }

// 戻る
document.getElementById('backBtn').addEventListener('click', (e)=>{
    e.preventDefault();
    if (history.length > 1) history.back();
    else window.location.href = '/public_html/frontend/top/calendar/calendar.html';
});

// スワイプ & 角めくり
function peelFromCorner(corner, after){
    const curl = document.createElement('div');
    curl.className = 'page-curl ' + (corner === 'left' ? 'left anim-left' : 'right anim-right');
    sheet.appendChild(curl);
    curl.addEventListener('animationend', ()=>{ curl.remove(); after && after(); }, { once:true });
}
function navigateSwipe(dx){
    const cur = currentDate || (new Date()).toISOString().slice(0,10);
    const next = dx < 0 ? ymdShift(cur, +1) : ymdShift(cur, -1);
    updateTo(next, dx < 0 ? 'right' : 'left');
}
function updateTo(dateStr, animateFrom){
    // ★ ここで中身は消さない（フェードアウトは視覚のみ）
    paper.classList.add('fade-out');
    fetchEntry({date: dateStr})
    .then(json=>{
        const doRender = ()=>{
        renderFromData(json || null);
        paper.classList.remove('fade-out');
        paper.classList.add('fade-in');
        setTimeout(()=>paper.classList.remove('fade-in'), 180);
        };
        peelFromCorner(animateFrom, doRender);
        const u = new URL(location.href);
        u.searchParams.set('date', dateStr);
        u.searchParams.delete('id');
        history.pushState({date: dateStr}, '', u.pathname + u.search);
    })
    .catch(()=>{
        // 取得失敗でも既存表示は維持（空にしない）
        paper.classList.remove('fade-out');
    });
}
(function enableSwipe(){
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
window.addEventListener('popstate', async (ev)=>{
    const d = (ev.state && ev.state.date) ? ev.state.date : currentDate;
    if(!d) return;
    try{
    const json = await fetchEntry({date:d});
    renderFromData(json||null);
    }catch(_){ /* 既存表示は維持 */ }
});

// 初期ロード
(async function init(){
    try{
    let json;
    if (paramId)      json = await fetchEntry({id: paramId});
    else if(paramDate)json = await fetchEntry({date: paramDate});
    else { return; } // 既存の●表示を残す

    renderFromData(json);
    if (json && json.date){
        const u = new URL(location.href);
        u.searchParams.set('date', json.date.slice(0,10));
        u.searchParams.delete('id');
        history.replaceState({date: json.date.slice(0,10)}, '', u.pathname + u.search);
    }
    }catch(e){
    console.error('entry_api error:', e);
    // 既存表示は維持（空にしない）
    }
})();







