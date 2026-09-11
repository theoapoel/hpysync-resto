// Small imperative toast helper for Inertia/Vue pages, replacing the global
// `toast()` that used to live in resources/views/layouts/app.blade.php's
// inline <script>. Renders into the #toast-container element AppLayout.vue
// mounts, using the same .toast CSS classes as before.
const icons = { success: 'check-circle', error: 'exclamation-circle', warning: 'exclamation-triangle' };

export function toast(message, type = 'success', duration = 3500) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i> ${message}`;
    container.appendChild(el);

    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateX(40px)';
        el.style.transition = '.3s';
        setTimeout(() => el.remove(), 300);
    }, duration);
}
