
document.addEventListener('DOMContentLoaded', async function () {
    const titleEl = document.getElementById('video-title');
    const viewsEl = document.getElementById('video-views');
    const wrapperEl = document.getElementById('video-wrapper');
    const statusEl = document.getElementById('status-message');
    const iframeContainer = document.getElementById('iframe-container');

    // MENGAMBIL SLUG VIDEO DARI URL
    // Mendukung Format Hash (domain.com/#/slug), Query (domain.com/?v=slug), dan Path (domain.com/amplify_video/slug)
    let videoSlug = '';

    if (window.location.hash) {
        // Mode Hash (Paling Aman untuk Github Pages)
        videoSlug = window.location.hash.replace('#/', '').replace('#', '');
    } else if (window.location.search) {
        // Mode Query string (?v=slug)
        const urlParams = new URLSearchParams(window.location.search);
        videoSlug = urlParams.get('v');
    } else if (window.location.pathname && window.location.pathname !== '/') {
        // Mode Path Asli (Butuh rewrite URL di hosting)
        const parts = window.location.pathname.split('/');
        videoSlug = parts[parts.length - 1];
    }

    const landingPageEl = document.getElementById('landing-page');
    const playerPageEl = document.getElementById('player-page');

    if (!videoSlug) {
        // Tampilkan Landing Page
        if (landingPageEl) landingPageEl.style.display = 'flex';
        if (playerPageEl) playerPageEl.style.display = 'none';
        return;
    } else {
        // Tampilkan Player Page
        if (landingPageEl) landingPageEl.style.display = 'none';
        if (playerPageEl) playerPageEl.style.display = 'flex';
    }

    try {
        // FETCH DATA VIDEO DARI API PUSAT
        const response = await fetch(`${CONFIG.API_BASE_URL}/api/video/${videoSlug}`);
        if (!response.ok) throw new Error('Video tidak ditemukan (404)');

        const json = await response.json();
        const video = json.video;

        statusEl.style.display = 'none';
        iframeContainer.style.display = 'block';
        titleEl.textContent = video.title;
        viewsEl.textContent = `👀 Dilihat: ${video.views.toLocaleString('id-ID')} kali`;

        iframeContainer.innerHTML = `
            <iframe id="bunny-player" 
                    src="https://iframe.mediadelivery.net/embed/681218/${video.bunny_id}?autoplay=false&loop=false&muted=false&preload=true" 
                    loading="lazy" 
                    allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;" 
                    allowfullscreen="true">
            </iframe>
        `;

        setupAdOverlays();

        // Dengarkan event dari Bunny Player
        const iframe = document.getElementById('bunny-player');
        if (window.playerjs && iframe) {
            const player = new playerjs.Player(iframe);
            player.on('ready', () => {
                player.on('pause', () => {
                    const overlay = document.getElementById('overlay-layer-1');
                    if (overlay) {
                        overlay.style.display = 'flex'; // Munculkan kembali iklan
                    }
                });
            });
        }

    } catch (error) {
        console.error(error);
        statusEl.innerHTML = '⚠️ Video tidak ditemukan atau Server Pusat sedang gangguan.';
        titleEl.innerHTML = 'Error';
    }
});

function setupAdOverlays() {
    const overlay1 = document.getElementById('overlay-layer-1');

    function triggerPopunder(url) {
        const popWin = window.open(url, '_blank');
        if (popWin) {
            popWin.blur();
            window.focus();
        } else {
            console.log('Popunder terblokir popup blocker browser.');
        }
    }

    if (overlay1) {
        overlay1.addEventListener('click', function (e) {
            e.preventDefault();
            triggerPopunder(CONFIG.CLIENT_POPUNDER_URL);
            overlay1.style.display = 'none'; // Sembunyikan, jangan di-remove

            // Coba mainkan video otomatis
            const iframe = document.getElementById('bunny-player');
            if (window.playerjs && iframe) {
                const player = new playerjs.Player(iframe);
                player.play();
            }
        });
    }
}
