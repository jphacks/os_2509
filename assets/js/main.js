// ========================================
// スムーススクロール
// ========================================
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// ========================================
// スクロール時のヘッダー効果
// ========================================
const header = document.querySelector('.header');
let lastScroll = 0;

window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset;
    
    if (currentScroll > 100) {
        header.style.boxShadow = '0 5px 20px rgba(0,0,0,0.1)';
    } else {
        header.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
    }
    
    lastScroll = currentScroll;
});

// ========================================
// 機能カードのアニメーション
// ========================================
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, index) => {
        if (entry.isIntersecting) {
            setTimeout(() => {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }, index * 100);
        }
    });
}, observerOptions);

// 機能カードに適用
document.querySelectorAll('.feature-card').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(30px)';
    card.style.transition = 'all 0.6s ease';
    observer.observe(card);
});

// ステップカードに適用
document.querySelectorAll('.step').forEach(step => {
    step.style.opacity = '0';
    step.style.transform = 'translateY(30px)';
    step.style.transition = 'all 0.6s ease';
    observer.observe(step);
});

// ========================================
// FABボタンの表示/非表示
// ========================================
const fab = document.querySelector('.fab-container');

window.addEventListener('scroll', () => {
    if (window.pageYOffset > 300) {
        fab.style.opacity = '1';
        fab.style.transform = 'scale(1)';
    } else {
        fab.style.opacity = '0';
        fab.style.transform = 'scale(0.8)';
    }
});

// 初期状態
fab.style.transition = 'all 0.3s ease';
fab.style.opacity = '0';
fab.style.transform = 'scale(0.8)';

// ========================================
// ページ読み込み時のアニメーション
// ========================================
window.addEventListener('load', () => {
    document.body.style.opacity = '0';
    setTimeout(() => {
        document.body.style.transition = 'opacity 0.5s ease';
        document.body.style.opacity = '1';
    }, 100);
});

// ========================================
// データベース初期化確認
// ========================================
document.querySelectorAll('a[href*="table.php"]').forEach(link => {
    link.addEventListener('click', (e) => {
        const confirmed = confirm('データベースを初期化しますか?\n(既にテーブルが存在する場合は影響ありません)');
        if (!confirmed) {
            e.preventDefault();
        }
    });
});