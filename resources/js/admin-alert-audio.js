/**
 * Subtle Front Desk Alert Audio & Notification System
 *
 * Uses the Web Audio API to synthesize a gentle, natural two-tone chime
 * (warm harmonic sine waves at 587.33 Hz [D5] and 880.00 Hz [A5]) without
 * loading any external MP3/WAV assets or relying on network downloads.
 *
 * Also provides an interface for standard HTML5 desktop notifications
 * with persistent localStorage preference controls.
 */

const STORAGE_KEY_SOUND = 'vm_admin_alert_sound';
const STORAGE_KEY_DESKTOP = 'vm_admin_alert_desktop';

let audioCtx = null;

const getAudioContext = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) {
        return null;
    }

    if (!audioCtx) {
        audioCtx = new AudioContextClass();
    }

    if (audioCtx.state === 'suspended') {
        audioCtx.resume().catch(() => {});
    }

    return audioCtx;
};

/**
 * Checks if sound alert is enabled in staff browser preferences.
 * Defaults to true.
 */
export const isSoundEnabled = () => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY_SOUND);
        return stored === null ? true : stored === 'true';
    } catch {
        return true;
    }
};

export const setSoundEnabled = (enabled) => {
    try {
        localStorage.setItem(STORAGE_KEY_SOUND, enabled ? 'true' : 'false');
    } catch {}
};

/**
 * Checks if desktop push notifications are enabled and granted.
 */
export const isDesktopEnabled = () => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY_DESKTOP);
        return stored === 'true' && typeof Notification !== 'undefined' && Notification.permission === 'granted';
    } catch {
        return false;
    }
};

export const setDesktopEnabled = async (enabled) => {
    try {
        if (enabled && typeof Notification !== 'undefined' && Notification.permission !== 'granted') {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                localStorage.setItem(STORAGE_KEY_DESKTOP, 'false');
                return false;
            }
        }
        localStorage.setItem(STORAGE_KEY_DESKTOP, enabled ? 'true' : 'false');
        return enabled;
    } catch {
        return false;
    }
};

/**
 * Synthesizes a delicate, organic hotel front-desk chime.
 * Two soft notes: D5 (587.3 Hz) fading into A5 (880.0 Hz) with an exponential decay curve.
 */
export const playFrontDeskChime = () => {
    if (!isSoundEnabled()) {
        return;
    }

    try {
        const ctx = getAudioContext();
        if (!ctx) {
            return;
        }

        const now = ctx.currentTime;

        // Note 1: D5 (Warm chime fundamental)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, now); // D5

        gain1.gain.setValueAtTime(0.001, now);
        gain1.gain.exponentialRampToValueAtTime(0.12, now + 0.03);
        gain1.gain.exponentialRampToValueAtTime(0.0001, now + 0.7);

        osc1.connect(gain1);
        gain1.connect(ctx.destination);

        osc1.start(now);
        osc1.stop(now + 0.75);

        // Note 2: A5 (Harmonic bright chime after 120ms)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(880.00, now + 0.12); // A5

        gain2.gain.setValueAtTime(0.001, now + 0.12);
        gain2.gain.exponentialRampToValueAtTime(0.09, now + 0.15);
        gain2.gain.exponentialRampToValueAtTime(0.0001, now + 1.1);

        osc2.connect(gain2);
        gain2.connect(ctx.destination);

        osc2.start(now + 0.12);
        osc2.stop(now + 1.15);
    } catch {
        // Audio synthesis failure should be silent and non-blocking
    }
};

/**
 * Triggers a browser desktop notification if permitted and enabled.
 */
export const sendDesktopNotification = (title, options = {}) => {
    if (!isDesktopEnabled() || typeof Notification === 'undefined' || Notification.permission !== 'granted') {
        return;
    }

    try {
        new Notification(title, {
            icon: '/images/logo-villa-mercedes-mark.png',
            badge: '/images/logo-villa-mercedes-mark.png',
            ...options,
        });
    } catch {}
};

/**
 * High-level alert dispatch for when new shift events arrive (new booking or payment).
 */
export const triggerShiftAlert = ({ title, body }) => {
    playFrontDeskChime();

    const isBackgroundTab = typeof document !== 'undefined' && Boolean(document.hidden);

    if (isBackgroundTab && title) {
        sendDesktopNotification(title, { body });
    }
};
