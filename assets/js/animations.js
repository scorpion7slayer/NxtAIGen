/**
 * NxtAIGen - Animations globales avec Anime.js v4
 * API: anime.animate(targets, props) - anime.stagger(value)
 */

(function() {
    'use strict';

    // Vérifier que anime.js v4 est chargé
    if (typeof anime === 'undefined' || typeof anime.animate === 'undefined') {
        console.warn('anime.js v4 non chargé - animations désactivées');
        window.NxtAnim = {};
        return;
    }

    const { animate, stagger } = anime;

    // ===== CONFIGURATION =====
    const DURATION = {
        fast: 200,
        normal: 400,
        slow: 600
    };

    const EASE = {
        smooth: 'outCubic',
        bounce: 'outBack',
        elastic: 'outElastic(1, 0.5)'
    };

    // ===== ANIMATIONS DE BASE =====

    function fadeIn(el, options = {}) {
        if (!el) return null;
        el.style.opacity = '0';

        return animate(el, {
            opacity: [0, 1],
            duration: options.duration || DURATION.normal,
            ease: options.ease || EASE.smooth,
            delay: options.delay || 0,
            onComplete: options.complete
        });
    }

    function fadeOut(el, options = {}) {
        if (!el) return null;

        return animate(el, {
            opacity: [1, 0],
            duration: options.duration || DURATION.normal,
            ease: options.ease || EASE.smooth,
            delay: options.delay || 0,
            onComplete: options.complete
        });
    }

    function slideInUp(el, options = {}) {
        if (!el) return null;
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';

        return animate(el, {
            opacity: [0, 1],
            translateY: [30, 0],
            duration: options.duration || DURATION.normal,
            ease: options.ease || EASE.smooth,
            delay: options.delay || 0,
            onComplete: options.complete
        });
    }

    function slideOutDown(el, options = {}) {
        if (!el) return null;

        return animate(el, {
            opacity: [1, 0],
            translateY: [0, 30],
            duration: options.duration || DURATION.fast,
            ease: options.ease || EASE.smooth,
            delay: options.delay || 0,
            onComplete: options.complete
        });
    }

    function scaleIn(el, options = {}) {
        if (!el) return null;
        el.style.opacity = '0';
        el.style.transform = 'scale(0.9)';

        return animate(el, {
            opacity: [0, 1],
            scale: [0.9, 1],
            duration: options.duration || DURATION.normal,
            ease: options.ease || EASE.bounce,
            delay: options.delay || 0,
            onComplete: options.complete
        });
    }

    function scaleOut(el, options = {}) {
        if (!el) return null;

        return animate(el, {
            opacity: [1, 0],
            scale: [1, 0.9],
            duration: options.duration || DURATION.fast,
            ease: options.ease || EASE.smooth,
            delay: options.delay || 0,
            onComplete: options.complete
        });
    }

    // ===== ANIMATIONS DE MESSAGES =====

    function messageIn(el, options = {}) {
        if (!el) return null;
        el.style.opacity = '0';

        return animate(el, {
            opacity: [0, 1],
            translateY: [20, 0],
            duration: options.duration || DURATION.normal,
            ease: EASE.smooth,
            delay: options.delay || 0
        });
    }

    function messageOut(el, callback) {
        if (!el) return null;

        return animate(el, {
            opacity: [1, 0],
            translateX: [0, -30],
            duration: DURATION.fast,
            ease: EASE.smooth,
            onComplete: callback
        });
    }

    // ===== ANIMATIONS DE MODAL =====

    function modalOpen(overlay, content) {
        if (overlay) {
            overlay.style.opacity = '0';
            overlay.style.display = 'flex';

            animate(overlay, {
                opacity: [0, 1],
                duration: DURATION.fast,
                ease: 'linear'
            });
        }

        if (content) {
            content.style.opacity = '0';
            content.style.transform = 'scale(0.9) translateY(20px)';

            animate(content, {
                opacity: [0, 1],
                scale: [0.9, 1],
                translateY: [20, 0],
                duration: DURATION.normal,
                ease: EASE.bounce,
                delay: 50
            });
        }
    }

    function modalClose(overlay, content, callback) {
        if (content) {
            animate(content, {
                opacity: [1, 0],
                scale: [1, 0.95],
                translateY: [0, 10],
                duration: DURATION.fast,
                ease: EASE.smooth
            });
        }

        if (overlay) {
            animate(overlay, {
                opacity: [1, 0],
                duration: DURATION.fast,
                delay: 100,
                ease: 'linear',
                onComplete: function() {
                    overlay.style.display = 'none';
                    if (callback) callback();
                }
            });
        }
    }

    // ===== ANIMATIONS DE SIDEBAR =====

    function sidebarOpen(el, direction = 'left') {
        if (!el) return null;

        const translateFrom = direction === 'left' ? '-100%' : '100%';
        el.style.transform = `translateX(${translateFrom})`;
        el.style.display = 'flex';

        return animate(el, {
            translateX: [translateFrom, '0%'],
            opacity: [0.5, 1],
            duration: DURATION.normal,
            ease: EASE.smooth
        });
    }

    function sidebarClose(el, direction = 'left', callback) {
        if (!el) return null;

        const translateTo = direction === 'left' ? '-100%' : '100%';

        return animate(el, {
            translateX: ['0%', translateTo],
            opacity: [1, 0.5],
            duration: DURATION.fast,
            ease: EASE.smooth,
            onComplete: function() {
                el.style.display = 'none';
                if (callback) callback();
            }
        });
    }

    // ===== ANIMATIONS DE LISTE =====

    function staggerIn(elements, options = {}) {
        if (!elements || elements.length === 0) return null;

        Array.from(elements).forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(15px)';
        });

        return animate(elements, {
            opacity: [0, 1],
            translateY: [15, 0],
            duration: options.duration || DURATION.normal,
            delay: stagger(options.stagger || 50, { start: options.delay || 0 }),
            ease: options.ease || EASE.smooth,
            onComplete: options.complete
        });
    }

    // ===== ANIMATIONS DE BOUTONS =====

    function buttonClick(el) {
        if (!el) return null;

        return animate(el, {
            scale: [1, 0.95, 1],
            duration: 200,
            ease: EASE.smooth
        });
    }

    function setupButtonHover(el, scale = 1.05) {
        if (!el) return;

        el.addEventListener('mouseenter', function() {
            animate(this, {
                scale: scale,
                duration: 150,
                ease: EASE.smooth
            });
        });

        el.addEventListener('mouseleave', function() {
            animate(this, {
                scale: 1,
                duration: 150,
                ease: EASE.smooth
            });
        });
    }

    // ===== ANIMATIONS DE TOAST =====

    function toastIn(el) {
        if (!el) return null;

        el.style.opacity = '0';
        el.style.transform = 'translateY(50px) scale(0.9)';

        return animate(el, {
            opacity: [0, 1],
            translateY: [50, 0],
            scale: [0.9, 1],
            duration: DURATION.normal,
            ease: EASE.bounce
        });
    }

    function toastOut(el, callback) {
        if (!el) return null;

        return animate(el, {
            opacity: [1, 0],
            translateY: [0, -20],
            duration: DURATION.fast,
            ease: EASE.smooth,
            onComplete: callback
        });
    }

    // ===== ANIMATIONS DE TYPING =====

    function typingDots(container) {
        if (!container) return null;

        const dots = container.querySelectorAll('.dot');
        if (dots.length === 0) return null;

        return animate(dots, {
            scale: [0.6, 1, 0.6],
            opacity: [0.4, 1, 0.4],
            duration: 800,
            delay: stagger(150),
            loop: true,
            ease: 'inOutSine'
        });
    }

    // ===== ANIMATIONS DE DROPDOWN =====

    function dropdownOpen(el) {
        if (!el) return null;

        el.style.opacity = '0';
        el.style.transform = 'scaleY(0.8) translateY(-10px)';
        el.style.transformOrigin = 'top';
        el.style.display = 'block';

        return animate(el, {
            opacity: [0, 1],
            scaleY: [0.8, 1],
            translateY: [-10, 0],
            duration: DURATION.fast,
            ease: EASE.smooth
        });
    }

    function dropdownClose(el, callback) {
        if (!el) return null;

        return animate(el, {
            opacity: [1, 0],
            scaleY: [1, 0.8],
            translateY: [0, -10],
            duration: DURATION.fast,
            ease: EASE.smooth,
            onComplete: function() {
                el.style.display = 'none';
                if (callback) callback();
            }
        });
    }

    // ===== ANIMATIONS SPÉCIALES =====

    function pulse(el, options = {}) {
        if (!el) return null;

        return animate(el, {
            scale: [1, 1.05, 1],
            opacity: [1, 0.8, 1],
            duration: options.duration || 1500,
            loop: options.loop !== false,
            ease: 'inOutSine'
        });
    }

    function shake(el) {
        if (!el) return null;

        return animate(el, {
            translateX: [0, -10, 10, -10, 10, 0],
            duration: 500,
            ease: 'inOutSine'
        });
    }

    function successPop(el) {
        if (!el) return null;

        el.style.opacity = '0';
        el.style.transform = 'scale(0)';

        return animate(el, {
            opacity: [0, 1],
            scale: [0, 1.2, 1],
            duration: 500,
            ease: EASE.bounce
        });
    }

    // ===== INITIALISATION AUTOMATIQUE =====

    function init() {
        // Observer pour animer les nouveaux éléments
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType !== 1) return;

                    if (node.classList && node.classList.contains('message')) {
                        messageIn(node);
                    }

                    if (node.classList && node.classList.contains('conversation-item')) {
                        slideInUp(node, { delay: 0 });
                    }

                    if (node.classList && (node.classList.contains('toast') || node.id === 'toast')) {
                        toastIn(node);
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });

        document.querySelectorAll('.btn-hover-animate').forEach(setupButtonHover);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ===== EXPORT GLOBAL =====
    window.NxtAnim = {
        fadeIn,
        fadeOut,
        slideInUp,
        slideOutDown,
        scaleIn,
        scaleOut,
        messageIn,
        messageOut,
        modalOpen,
        modalClose,
        sidebarOpen,
        sidebarClose,
        staggerIn,
        buttonClick,
        setupButtonHover,
        toastIn,
        toastOut,
        typingDots,
        dropdownOpen,
        dropdownClose,
        pulse,
        shake,
        successPop,
        DURATION,
        EASE
    };

})();
