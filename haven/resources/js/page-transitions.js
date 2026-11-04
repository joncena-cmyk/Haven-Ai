/**
 * HAVEN AI — Smooth Navigation Progress Laser Beam
 * 
 * Provides instant, silky-smooth visual feedback on page switching:
 * - Electric Cyan Top Progress Laser Beam on link click
 * - Non-blinking layout stability
 * - Full BFCache support
 */

(function () {
    'use strict';

    function getLaser() {
        return document.getElementById('haven-top-laser');
    }

    function triggerNavLaser() {
        const laser = getLaser();
        if (!laser) return;
        laser.style.transition = 'none';
        laser.style.width = '0%';
        laser.style.opacity = '1';
        laser.classList.add('active');
        void laser.offsetWidth; // Force DOM reflow

        // Step 1: Rapid glide to 45%
        laser.style.transition = 'width 0.22s cubic-bezier(0.1, 0.8, 0.1, 1)';
        laser.style.width = '45%';

        // Step 2: Steady advance while server responds
        setTimeout(() => {
            if (laser.classList.contains('active')) {
                laser.style.transition = 'width 1.2s cubic-bezier(0.1, 0.5, 0.1, 1)';
                laser.style.width = '85%';
            }
        }, 180);
    }

    function completeNavLaser() {
        const laser = getLaser();
        if (!laser) return;
        laser.style.transition = 'width 0.18s ease-out, opacity 0.22s ease 0.1s';
        laser.style.width = '100%';
        laser.style.opacity = '0';
        setTimeout(() => {
            laser.classList.remove('active');
            laser.style.width = '0%';
        }, 380);
    }

    // Intercept clicks on navigation links
    document.addEventListener('click', function (e) {
        const link = e.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href) return;

        // Ignore external, download, targets, hashtags
        if (
            link.target === '_blank' ||
            link.hasAttribute('download') ||
            link.getAttribute('rel')?.includes('external') ||
            link.dataset.noTransition === 'true' ||
            href.startsWith('#') ||
            href.startsWith('javascript:') ||
            href.startsWith('mailto:') ||
            href.startsWith('tel:')
        ) {
            return;
        }

        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) {
            return;
        }

        try {
            const url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin) return;

            // Same page anchor
            if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
                return;
            }

            // Immediately shoot laser beam across the top
            triggerNavLaser();
        } catch (err) {}
    });

    // When the new page finishes mounting or BFCache restores
    window.addEventListener('pageshow', completeNavLaser);
    document.addEventListener('DOMContentLoaded', completeNavLaser);
    completeNavLaser();
})();
