
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

    // Bersihkan akhiran .mp4 jika ada, agar API tidak kebingungan
    if (videoSlug) {
        videoSlug = videoSlug.replace(/\.mp4$/i, '');
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

    // Sembunyikan overlay dulu sampai player siap
    const overlay1 = document.getElementById('overlay-layer-1');
    if (overlay1) overlay1.style.display = 'none';

    try {
        // FETCH DATA VIDEO DARI API PUSAT
        const response = await fetch(`${CONFIG.API_BASE_URL}/api/video/${videoSlug}`);
        if (!response.ok) throw new Error('Video tidak ditemukan (404)');

        const json = await response.json();
        const video = json.video;

        titleEl.textContent = video.title;
        viewsEl.textContent = `👀 Dilihat: ${video.views.toLocaleString('id-ID')} kali`;

        const videoContainer = document.getElementById('video-container');
        const mainVideo = document.getElementById('main-video');

        // URL stream Bunny CDN
        const videoSrc = `https://vz-80a83061-403.b-cdn.net/${video.bunny_id}/playlist.m3u8`;

        if (Hls.isSupported()) {
            const hls = new Hls();
            hls.loadSource(videoSrc);
            hls.attachMedia(mainVideo);
        } else if (mainVideo.canPlayType('application/vnd.apple.mpegurl')) {
            mainVideo.src = videoSrc;
        }

        statusEl.style.display = 'none';
        if (videoContainer) videoContainer.style.display = 'block';

        // Tampilkan overlay selalu (pancingan agresif)
        if (overlay1) {
            overlay1.style.display = 'flex';
        }

        // Load Lazy Ads (Supaya Adsterra tidak mendeteksi display: none)
        document.querySelectorAll('.lazy-ad').forEach(iframe => {
            if (iframe.dataset.src && !iframe.src) {
                iframe.src = iframe.dataset.src;
            }
        });

        // Event listener saat user pause dari kontrol bawaan HTML5
        if (mainVideo) {
            mainVideo.addEventListener('pause', function() {
                if (overlay1) overlay1.style.display = 'flex';
            });
            mainVideo.addEventListener('play', function() {
                if (overlay1) overlay1.style.display = 'none';
            });
        }

        // Setup klik overlay
        setupAdOverlays(mainVideo);

    } catch (error) {
        console.error(error);
        if (overlay1) overlay1.style.display = 'none';
        statusEl.innerHTML = '⚠️ Video tidak ditemukan atau Server Pusat sedang gangguan.';
        titleEl.innerHTML = 'Error';
    }
});

function setupAdOverlays(mainVideo) {
    const overlay1 = document.getElementById('overlay-layer-1');

    function triggerPopunder(url) {
        if (!url) return;
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
            overlay1.style.display = 'none'; // Sembunyikan overlay

            // Coba mainkan video otomatis
            if (mainVideo) {
                mainVideo.play().catch(err => console.log('Auto-play gagal:', err));
            }
        });
    }
}
