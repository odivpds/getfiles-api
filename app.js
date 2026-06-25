
document.addEventListener('DOMContentLoaded', async function () {

    // ==========================================
    // DETEKSI MODE: Gallery atau Player
    // ==========================================
    let videoSlug = '';

    if (window.location.hash) {
        videoSlug = window.location.hash.replace('#/', '').replace('#', '');
    } else if (window.location.search) {
        const urlParams = new URLSearchParams(window.location.search);
        videoSlug = urlParams.get('v') || urlParams.get('id') || '';
    } else {
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        if (pathParts.length > 0) {
            videoSlug = pathParts[pathParts.length - 1];
        }
    }

    if (videoSlug) {
        // ========== MODE PLAYER ==========
        document.getElementById('gallery-mode').classList.add('hidden');
        document.getElementById('player-mode').classList.remove('hidden');
        initPlayer(videoSlug);
    } else {
        // ========== MODE GALLERY ==========
        document.getElementById('gallery-mode').classList.remove('hidden');
        document.getElementById('player-mode').classList.add('hidden');
        initGallery();
    }
});

// ==========================================
// GALLERY MODE
// ==========================================
let currentPage = 1;
let totalPages = 1;

async function initGallery() {
    // Video grid has been removed for this client, replaced with a button to generator page.
    // No need to fetch videos or initialize pagination.
}

async function fetchVideos(page) {
    const grid = document.getElementById('video-grid');
    const loading = document.getElementById('gallery-loading');
    const pagination = document.getElementById('pagination');

    loading.classList.remove('hidden');
    grid.innerHTML = '';
    pagination.classList.add('hidden');

    try {
        const response = await fetch(`${CONFIG.API_BASE_URL}/api/videos?page=${page}&limit=10`);
        if (!response.ok) throw new Error('Gagal memuat video');

        const json = await response.json();
        if (!json.success) throw new Error('API error');

        const videos = json.data;
        const pag = json.pagination;
        currentPage = pag.current_page;
        totalPages = pag.total_pages;

        // Update total
        document.getElementById('total-videos').textContent = `Total Video Server: ${pag.total_items}`;

        // Render cards
        grid.innerHTML = videos.map(function(video) {
            const thumbUrl = `https://vz-80a83061-403.b-cdn.net/${video.bunny_id}/thumbnail.jpg`;
            const views = (video.views || 0).toLocaleString();
            return `
                <a href="?v=${video.slug}" class="video-card" onclick="event.preventDefault(); window.location.href='?v=${video.slug}';">
                    <div class="thumb-wrapper">
                        <img src="${thumbUrl}" alt="${video.title}" loading="lazy" 
                             onerror="this.style.display='none'">
                        <div class="play-overlay">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="hd-badge">HD</div>
                    </div>
                    <div class="card-body">
                        <h3>${video.title}</h3>
                        <div class="meta">
                            <span class="views">👀 ${views} views</span>
                            <span class="watch-btn">Tonton</span>
                        </div>
                    </div>
                </a>
            `;
        }).join('');

        // Update pagination
        if (totalPages > 1) {
            pagination.classList.remove('hidden');
            document.getElementById('btn-prev').disabled = (currentPage === 1);
            document.getElementById('btn-next').disabled = (currentPage === totalPages);
            document.getElementById('page-info').innerHTML = `Hal <span>${currentPage}</span> dari <span>${totalPages}</span>`;
        }

        // Scroll ke atas gallery jika bukan halaman pertama
        if (page > 1) {
            document.querySelector('.gallery-section').scrollIntoView({ behavior: 'smooth' });
        }

    } catch (error) {
        console.error('Gallery Error:', error);
        grid.innerHTML = '<p style="text-align:center;color:#8b949e;grid-column:1/-1;padding:40px;">⚠️ Gagal memuat video. Periksa koneksi atau coba lagi nanti.</p>';
    } finally {
        loading.classList.add('hidden');
    }
}

// ==========================================
// PLAYER MODE
// ==========================================
async function initPlayer(videoSlug) {
    const titleEl = document.getElementById('video-title');
    const viewsEl = document.getElementById('video-views');
    const statusEl = document.getElementById('status-message');
    const iframeContainer = document.getElementById('iframe-container');

    try {
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

    } catch (error) {
        console.error(error);
        statusEl.innerHTML = '⚠️ Video tidak ditemukan atau Server Pusat sedang gangguan.';
        titleEl.innerHTML = 'Error';
    }
}

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
            overlay1.remove();
        });
    }
}
