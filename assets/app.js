document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('playerModal');
    const iframe = document.getElementById('playerFrame');
    const closeBtn = modal?.querySelector('.modal-close');

    if (!modal || !iframe || !closeBtn) {
        return;
    }

    const openModal = (videoId) => {
        if (!videoId) return;

        iframe.src = `https://www.youtube.com/embed/${encodeURIComponent(videoId)}?autoplay=1&rel=0`;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
        modal.classList.remove('active');
        iframe.src = '';
        document.body.style.overflow = '';
    };

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    closeBtn.addEventListener('click', closeModal);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    });

    const bindTriggers = () => {
        document.querySelectorAll('[data-video-id]').forEach((element) => {
            element.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openModal(element.dataset.videoId);
            });
        });
    };

    bindTriggers();
});
