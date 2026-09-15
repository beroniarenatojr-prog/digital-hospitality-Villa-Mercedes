export const mountSupportChat = (root) => {
    if (root.supportChatInstance) return root.supportChatInstance;

    const panel = root.querySelector('[data-chat-panel]');
    const launcher = root.querySelector('[data-chat-launcher]');
    const closeButton = root.querySelector('[data-chat-minimize]');
    const openIcon = root.querySelector('[data-chat-open-icon]');
    const closeIcon = root.querySelector('[data-chat-close-icon]');

    const setOpen = (open) => {
        panel.hidden = !open;
        launcher.setAttribute('aria-expanded', String(open));
        launcher.setAttribute('aria-label', open ? 'Close Messenger chat' : 'Open Messenger chat');
        openIcon.toggleAttribute('hidden', open);
        closeIcon.toggleAttribute('hidden', !open);
        (open ? closeButton : launcher).focus();
    };

    closeButton.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) setOpen(false);
    });
    document.addEventListener('click', (event) => {
        if (!panel.hidden && event.target instanceof Element
            && !root.contains(event.target) && !event.target.closest('[data-support-chat-open]')) {
            setOpen(false);
        }
    });

    root.supportChatInstance = {
        open: () => setOpen(true),
        toggle: () => setOpen(panel.hidden),
    };

    return root.supportChatInstance;
};
