<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { AnimatePresence, motion } from 'motion-v';

const props = defineProps({
    title: { type: String, default: '' },
});

const page = usePage();
const auth = computed(() => page.props.auth?.user);
const nav = computed(() => page.props.nav ?? []);
const pendingSync = computed(() => page.props.pendingSync ?? 0);
const flash = computed(() => page.props.flash ?? {});

// ── Sidebar collapse / mobile drawer ─────────────────────────────
const collapsed = ref(false);
const mobileOpen = ref(false);

function isMobile() {
    return typeof window !== 'undefined' && window.innerWidth <= 767;
}

function toggleSidebar() {
    if (isMobile()) {
        mobileOpen.value = !mobileOpen.value;
    } else {
        collapsed.value = !collapsed.value;
        try { localStorage.setItem('sidebarCollapsed', collapsed.value ? '1' : '0'); } catch (e) {}
    }
}

function closeMobile() {
    mobileOpen.value = false;
}

function onResize() {
    if (!isMobile()) mobileOpen.value = false;
}

onMounted(() => {
    try {
        if (!isMobile() && localStorage.getItem('sidebarCollapsed') === '1') collapsed.value = true;
    } catch (e) {}
    window.addEventListener('resize', onResize);
});
onUnmounted(() => window.removeEventListener('resize', onResize));

// ── ERP connectivity indicator ───────────────────────────────────
const erpState = ref('checking'); // checking | online | offline | hidden
let erpTimer = null;

async function checkErp() {
    if (!page.props.erpPingUrl) { erpState.value = 'hidden'; return; }
    if (typeof navigator !== 'undefined' && !navigator.onLine) { erpState.value = 'offline'; return; }
    try {
        const res = await fetch(page.props.erpPingUrl, { headers: { Accept: 'application/json' } });
        const json = await res.json();
        if (json.reason === 'not_configured') erpState.value = 'hidden';
        else erpState.value = json.reachable ? 'online' : 'offline';
    } catch (e) {
        erpState.value = 'offline';
    }
}

onMounted(() => {
    checkErp();
    erpTimer = setInterval(checkErp, 30000);
});
onUnmounted(() => clearInterval(erpTimer));

const erpLabel = computed(() => ({
    checking: 'ERP…',
    online: 'ERP Online',
    offline: 'ERP Offline',
    hidden: '',
}[erpState.value]));

function logout() {
    if (page.props.logoutUrl) router.post(page.props.logoutUrl);
}
</script>

<template>
    <div class="sidebar-backdrop" v-show="mobileOpen" @click="closeMobile"></div>

    <header class="header">
        <button class="sidebar-toggle" title="Toggle menu" @click="toggleSidebar">
            <i class="fas fa-bars"></i>
        </button>
        <Link href="/" class="header-brand" :style="{ width: collapsed && !isMobile() ? '64px' : undefined }">
            <img src="/images/happypos.png" alt="HPYSync" style="height:52px;width:auto;object-fit:contain;">
        </Link>
        <div class="header-right">
            <div v-if="erpState !== 'hidden'" :class="['st-' + erpState]" id="erpStatus">
                <span class="erp-dot"></span>
                <span>{{ erpLabel }}</span>
            </div>
            <Link v-if="pendingSync > 0" href="/sync" class="sync-badge warn">
                <i class="fas fa-sync-alt"></i> {{ pendingSync }} Pending Sync
            </Link>
            <div class="user-menu">
                <div class="user-avatar">{{ (auth?.name || '?').charAt(0) }}</div>
                <span class="user-name">{{ auth?.name }}</span>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" @click="logout">
                <i class="fas fa-sign-out-alt"></i>
            </button>
        </div>
    </header>

    <nav class="sidebar" :class="{ 'nav-collapsed-body': collapsed }" :style="{
        width: isMobile() ? undefined : (collapsed ? '64px' : undefined),
        transform: isMobile() ? (mobileOpen ? 'translateX(0)' : 'translateX(-100%)') : undefined,
    }">
        <template v-for="section in nav" :key="section.section">
            <div class="nav-section" v-show="!collapsed || isMobile()">{{ section.section }}</div>
            <Link
                v-for="item in section.items"
                :key="item.key"
                :href="item.href"
                class="nav-item"
                :class="{ active: item.active }"
                :style="item.danger ? { color: 'var(--red)' } : undefined"
                @click="closeMobile"
            >
                <i :class="['fas', item.icon, 'nav-icon']" :style="item.danger ? { color: 'var(--red)' } : undefined"></i>
                <span class="nav-label" v-show="!collapsed || isMobile()">{{ item.label }}</span>
                <span v-if="item.badge" class="nav-badge">{{ item.badge }}</span>
            </Link>
        </template>
    </nav>

    <main class="main" :style="{ marginLeft: !isMobile() && collapsed ? '64px' : undefined }">
        <AnimatePresence mode="wait">
            <motion.div v-if="flash.success" :key="'s-' + flash.success" class="alert alert-success"
                :initial="{ opacity: 0, y: -8 }" :animate="{ opacity: 1, y: 0 }" :exit="{ opacity: 0 }">
                <i class="fas fa-check-circle"></i> {{ flash.success }}
            </motion.div>
        </AnimatePresence>
        <div v-if="flash.error" class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> {{ flash.error }}
        </div>

        <slot />
    </main>
</template>
