document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('playerModal');
    const iframe = document.getElementById('playerFrame');
    const modalTitle = document.getElementById('modalTitle');
    const modalSummary = document.getElementById('modalSummary');
    const modalTopics = document.getElementById('modalTopics');
    const searchInput = document.querySelector('[data-search]');
    const searchEmpty = createSearchEmptyState();

    function openModal(videoId, meta = {}) {
        if (!videoId) {
            return;
        }

        const query = new URLSearchParams({
            autoplay: '1',
            rel: '0',
        });

        iframe.src = `https://www.youtube.com/embed/${encodeURIComponent(videoId)}?${query.toString()}`;
        modalTitle.textContent = meta.title || 'Untitled video';
        modalSummary.textContent = meta.summary || 'No summary available yet.';
        modalTopics.replaceChildren();

        const topics = Array.isArray(meta.topics)
            ? meta.topics
            : typeof meta.topics === 'string'
                ? meta.topics.split(',').map((topic) => topic.trim()).filter(Boolean)
                : [];

        topics.slice(0, 5).forEach((topic) => {
            const chip = document.createElement('span');
            chip.textContent = topic;
            modalTopics.appendChild(chip);
        });

        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.hidden = true;
        iframe.src = '';
        document.body.style.overflow = '';
    }

    function extractMeta(element, button) {
        const source = element?.closest('[data-video]') || element;
        return {
            title: button?.dataset.title || source?.dataset.title || '',
            summary: button?.dataset.summary || source?.dataset.summary || '',
            topics: button?.dataset.topics || source?.dataset.topics || '',
        };
    }

    document.querySelectorAll('[data-play]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const videoId = button.dataset.play;
            const meta = extractMeta(button.closest('[data-video]'), button);
            openModal(videoId, meta);
        });
    });

    document.querySelectorAll('[data-dismiss]').forEach((element) => {
        element.addEventListener('click', () => closeModal());
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.querySelectorAll('[data-scroll]').forEach((button) => {
        button.addEventListener('click', (event) => {
            const target = button.getAttribute('data-scroll');
            if (!target) {
                return;
            }
            event.preventDefault();
            const anchor = document.querySelector(target);
            if (anchor) {
                anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    document.querySelectorAll('[data-carousel]').forEach((carousel) => {
        carousel.addEventListener('wheel', (event) => {
            if (Math.abs(event.deltaY) > Math.abs(event.deltaX)) {
                event.preventDefault();
                carousel.scrollBy({
                    left: event.deltaY * 1.2,
                });
            }
        }, { passive: false });
    });

    document.querySelectorAll('[data-rail]').forEach((rail) => {
        const scroller = rail.querySelector('[data-carousel]');
        const prev = rail.querySelector('.rail-btn.prev');
        const next = rail.querySelector('.rail-btn.next');

        if (prev && scroller) {
            prev.addEventListener('click', () => {
                scroller.scrollBy({
                    left: -scroller.clientWidth * 0.8,
                    behavior: 'smooth',
                });
            });
        }

        if (next && scroller) {
            next.addEventListener('click', () => {
                scroller.scrollBy({
                    left: scroller.clientWidth * 0.8,
                    behavior: 'smooth',
                });
            });
        }
    });

    if (searchInput) {
        const items = Array.from(document.querySelectorAll('[data-video]'));
        const rails = Array.from(document.querySelectorAll('[data-rail]'));
        const grids = Array.from(document.querySelectorAll('.masonry__card'));

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            const searching = query.length > 0;
            document.body.classList.toggle('searching', searching);

            let matches = 0;

            items.forEach((item) => {
                const haystack = [
                    item.dataset.title || '',
                    item.dataset.topics || '',
                    item.dataset.summary || '',
                ].join(' ').toLowerCase();

                const visible = !searching || haystack.includes(query);
                item.style.display = visible ? '' : 'none';

                if (visible) {
                    matches += 1;
                }
            });

            rails.forEach((rail) => {
                const visibleItems = rail.querySelectorAll('[data-video]')
                    .length;
                const hiddenItems = rail.querySelectorAll('[data-video][style*="display: none"]').length;
                const isVisible = visibleItems > hiddenItems;
                rail.classList.toggle('is-hidden', searching && !isVisible);
            });

            grids.forEach((card) => {
                const isVisible = card.style.display !== 'none';
                card.classList.toggle('is-hidden', searching && !isVisible);
            });

            if (!matches && searching) {
                searchEmpty.removeAttribute('hidden');
            } else {
                searchEmpty.setAttribute('hidden', 'hidden');
            }
        });
    }

    function createSearchEmptyState() {
        let banner = document.querySelector('.search-empty');
        if (!banner) {
            banner = document.createElement('div');
            banner.className = 'search-empty';
            banner.setAttribute('hidden', 'hidden');
            banner.innerHTML = `
                <p>No videos matched your search. Try a different keyword or clear the search box.</p>
            `;
            document.body.appendChild(banner);
        }
        return banner;
    }
});

