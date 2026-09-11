<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { AnimatePresence, motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stocks: Object,
    warehouseTabs: Array,
    categoryTabs: Array,
    selectedWarehouse: Object,
    selectedWarehouseId: [Number, String],
    summary: Object,
    filters: Object,
    indexUrl: String,
    syncBaseUrl: String,
    warehousesForSync: Array,
});

const form = reactive({
    warehouse_id: props.selectedWarehouseId,
    item_category_id: new URLSearchParams(window.location.search).get('item_category_id') || '',
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const hasActiveFilters = !!(props.filters.search || props.filters.status);

function applyFilters() {
    router.get(props.indexUrl, form, { preserveState: true, preserveScroll: true, replace: true });
}

function resetFilters() {
    router.get(props.indexUrl, { warehouse_id: props.selectedWarehouseId }, { replace: true });
}

const statusBadge = { empty: 'badge-red', low: 'badge-yellow', safe: 'badge-green' };
const statusLabel = { empty: 'Habis', low: 'Rendah', safe: 'Aman' };
const statusColor = { empty: 'var(--red)', low: '#E37400', safe: 'var(--text)' };

// ── Sync-from-ERP progress modal ──────────────────────────────────
const showSyncModal = ref(false);
const syncing = ref(false);
const syncDone = ref(false);
const syncHasError = ref(false);
const syncStatus = ref('Mempersiapkan...');
const syncProgress = ref(0);
const syncLog = ref([]); // [{id, state: running|done|error, label, detail}]

function upsertLog(id, entry) {
    const i = syncLog.value.findIndex((r) => r.id === id);
    if (i === -1) syncLog.value.push({ id, ...entry });
    else syncLog.value[i] = { id, ...entry };
}

async function syncFromBin() {
    showSyncModal.value = true;
    syncing.value = true;
    syncDone.value = false;
    syncHasError.value = false;
    syncLog.value = [];

    const total = props.warehousesForSync.length;
    let grandTotal = 0;
    syncStatus.value = `Memulai sync — ${total} gudang ditemukan...`;

    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    for (let i = 0; i < props.warehousesForSync.length; i++) {
        const wh = props.warehousesForSync[i];
        upsertLog(wh.id, { state: 'running', label: wh.label, isDefault: wh.is_default, detail: 'Mengambil data dari ERP HPY...' });
        syncStatus.value = `Memproses ${i + 1} dari ${total}: ${wh.label}`;
        syncProgress.value = Math.round((i / total) * 100);

        try {
            const resp = await fetch(`${props.syncBaseUrl}/${wh.id}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            });
            const res = await resp.json();

            if (res.success) {
                const removed = res.removed > 0 ? ` · Dihapus (tidak ada di ERP): ${res.removed}` : '';
                upsertLog(wh.id, {
                    state: 'done', label: wh.label, isDefault: wh.is_default,
                    detail: `Bin dari ERP: ${res.bin_count} · Diperbarui: ${res.updated} · Tidak cocok: ${res.skipped}${removed} · Tersimpan di DB: ${res.db_rows}`,
                });
                grandTotal += res.updated;
            } else {
                upsertLog(wh.id, { state: 'error', label: wh.label, detail: 'Gagal: ' + res.error });
                syncHasError.value = true;
            }
        } catch (e) {
            upsertLog(wh.id, { state: 'error', label: wh.label, detail: 'Error koneksi: ' + e.message });
            syncHasError.value = true;
        }
    }

    syncProgress.value = 100;
    syncStatus.value = syncHasError.value
        ? `Selesai dengan error — ${grandTotal} produk diperbarui`
        : `Sync selesai — ${grandTotal} produk diperbarui di ${total} gudang`;
    syncing.value = false;
    syncDone.value = true;
}

function closeSyncModal() {
    showSyncModal.value = false;
}
</script>

<template>
    <Head title="Stok Barang" />
    <AppLayout>
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-boxes" style="color:var(--blue);margin-right:8px;font-size:22px;vertical-align:-2px"></i>
                    Stok Barang
                </h1>
                <p class="page-subtitle">
                    Pantau stok per gudang ·
                    <strong v-if="selectedWarehouse">{{ selectedWarehouse.label }}</strong>
                    <span v-else style="color:var(--red)">Belum ada warehouse aktif</span>
                </p>
            </div>
            <button class="btn btn-outline" :disabled="syncing" @click="syncFromBin">
                <i class="fas fa-sync-alt" :class="{ 'fa-spin': syncing }"></i> Sync Stok dari ERP HPY
            </button>
        </div>

        <div v-if="warehouseTabs.length > 1" class="filter-tabs-group">
            <div class="filter-tabs-label">Gudang</div>
            <div class="warehouse-tabs">
                <Link v-for="wh in warehouseTabs" :key="wh.id" :href="wh.url" class="warehouse-tab" :class="{ active: wh.active }">
                    <i class="fas fa-warehouse" style="margin-right:4px;font-size:11px"></i>
                    {{ wh.label }}
                    <span v-if="wh.is_default" style="font-size:10px;opacity:.8">(default)</span>
                </Link>
            </div>
        </div>

        <div v-if="categoryTabs.length > 1" class="filter-tabs-group" style="margin-bottom:20px">
            <div class="filter-tabs-label">Kategori</div>
            <div class="warehouse-tabs">
                <Link v-for="cat in categoryTabs" :key="cat.id ?? 'all'" :href="cat.url" class="warehouse-tab" :class="{ active: cat.active }">
                    <i v-if="cat.id === null" class="fas fa-layer-group" style="margin-right:4px;font-size:11px"></i>
                    {{ cat.name }}
                </Link>
            </div>
        </div>

        <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-boxes"></i></div>
                <div><div class="stat-value">{{ summary.total.toLocaleString('id-ID') }}</div><div class="stat-label">Total Produk</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div><div class="stat-value">{{ summary.safe.toLocaleString('id-ID') }}</div><div class="stat-label">Stok Aman</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-exclamation-triangle"></i></div>
                <div><div class="stat-value">{{ summary.low.toLocaleString('id-ID') }}</div><div class="stat-label">Stok Rendah</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
                <div><div class="stat-value">{{ summary.empty.toLocaleString('id-ID') }}</div><div class="stat-label">Stok Habis</div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="padding:16px 20px">
                <form style="display:flex;gap:10px;flex-wrap:wrap;align-items:center" @submit.prevent="applyFilters">
                    <input v-model="form.search" type="text" class="form-control" placeholder="Cari nama / SKU..." style="width:240px">
                    <select v-model="form.status" class="form-control form-select" style="width:160px">
                        <option value="">Semua Status</option>
                        <option value="safe">Aman</option>
                        <option value="low">Stok Rendah</option>
                        <option value="empty">Habis</option>
                    </select>
                    <button type="submit" class="btn btn-outline">Filter</button>
                    <button v-if="hasActiveFilters" type="button" class="btn btn-ghost" @click="resetFilters">Reset</button>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Produk</th><th>SKU</th><th>Item Group</th><th>Kategori</th>
                            <th style="text-align:right">Stok</th><th style="text-align:right">Min Stok</th>
                            <th>Satuan</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!stocks.data.length">
                            <td colspan="8" style="text-align:center;padding:48px;color:var(--text2)">
                                <span v-if="!selectedWarehouse">Belum ada warehouse yang dikonfigurasi</span>
                                <span v-else>Tidak ada produk yang sesuai filter di gudang ini</span>
                            </td>
                        </tr>
                        <tr v-for="s in stocks.data" :key="s.id">
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <img v-if="s.product_image" :src="s.product_image" style="width:32px;height:32px;border-radius:6px;object-fit:cover;border:1px solid var(--border)">
                                    <div v-else style="width:32px;height:32px;border-radius:6px;background:var(--surface2);display:flex;align-items:center;justify-content:center">
                                        <i class="fas fa-box" style="font-size:12px;color:var(--text3)"></i>
                                    </div>
                                    <span style="font-weight:500">{{ s.product_name }}</span>
                                </div>
                            </td>
                            <td><code style="font-size:12px;color:var(--text2)">{{ s.sku }}</code></td>
                            <td><span v-if="s.category_name" style="font-size:13px">{{ s.category_name }}</span><span v-else style="color:var(--text3)">—</span></td>
                            <td><span v-if="s.item_category_name" class="badge badge-gray" style="font-size:12px">{{ s.item_category_name }}</span><span v-else style="color:var(--text3)">—</span></td>
                            <td style="text-align:right">
                                <span style="font-family:'Google Sans',sans-serif;font-size:16px;font-weight:700" :style="{ color: statusColor[s.status] }">
                                    {{ s.quantity.toLocaleString('id-ID') }}
                                </span>
                            </td>
                            <td style="text-align:right;color:var(--text2);font-size:13px">{{ s.min_stock.toLocaleString('id-ID') }}</td>
                            <td style="color:var(--text2);font-size:13px">{{ s.unit }}</td>
                            <td><span class="badge" :class="statusBadge[s.status]">{{ statusLabel[s.status] }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="stocks.links.length > 3" class="pagination-wrap">
                <span style="color:var(--text2);font-size:13px">
                    Menampilkan {{ stocks.from ?? 0 }}–{{ stocks.to ?? 0 }} dari {{ stocks.total }}
                </span>
                <div class="pagination">
                    <Link v-for="(link, i) in stocks.links" :key="i" v-show="link.url" :href="link.url" class="page-btn" :class="{ active: link.active }" v-html="link.label"></Link>
                </div>
            </div>
        </div>

        <AnimatePresence>
            <motion.div
                v-if="showSyncModal"
                class="modal-overlay show"
                :initial="{ opacity: 0 }" :animate="{ opacity: 1 }" :exit="{ opacity: 0 }"
            >
                <motion.div class="modal" style="width:520px;max-width:calc(100vw - 32px)" :initial="{ opacity: 0, y: -20, scale: 0.97 }" :animate="{ opacity: 1, y: 0, scale: 1 }">
                    <div class="modal-header">
                        <i class="fas fa-sync-alt" :class="syncing ? 'fa-spin' : (syncHasError ? 'fa-exclamation-triangle' : 'fa-check-circle')"
                           :style="{ color: syncing ? 'var(--blue)' : (syncHasError ? '#E37400' : 'var(--green)') }"></i>
                        <div class="modal-title" style="flex:1;margin-left:10px">Sinkronisasi Stok dari ERP HPY</div>
                    </div>
                    <div style="height:4px;background:var(--surface2)">
                        <motion.div style="height:4px;background:var(--blue)" :animate="{ width: syncProgress + '%' }"></motion.div>
                    </div>
                    <div style="padding:0 24px 12px;padding-top:12px;font-size:13px;color:var(--text2)">{{ syncStatus }}</div>
                    <div style="padding:0 24px 16px;max-height:320px;overflow-y:auto;display:flex;flex-direction:column;gap:8px">
                        <motion.div
                            v-for="row in syncLog" :key="row.id"
                            class="sync-log-row" :class="row.state"
                            :initial="{ opacity: 0, x: -8 }" :animate="{ opacity: 1, x: 0 }"
                        >
                            <div class="sync-log-icon">
                                <i v-if="row.state === 'running'" class="fas fa-circle-notch fa-spin" style="color:var(--blue)"></i>
                                <i v-else-if="row.state === 'done'" class="fas fa-check-circle" style="color:var(--green)"></i>
                                <i v-else class="fas fa-times-circle" style="color:var(--red)"></i>
                            </div>
                            <div class="sync-log-body">
                                <div class="sync-log-title">{{ row.label }} <span v-if="row.isDefault" style="color:var(--blue);font-size:11px">(default)</span></div>
                                <div class="sync-log-detail">{{ row.detail }}</div>
                            </div>
                        </motion.div>
                    </div>
                    <div style="padding:14px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px">
                        <button v-if="syncDone" class="btn btn-outline" @click="closeSyncModal">Tutup</button>
                        <button v-if="syncDone" class="btn btn-primary" @click="router.reload()">Muat Ulang Data</button>
                    </div>
                </motion.div>
            </motion.div>
        </AnimatePresence>
    </AppLayout>
</template>

<style scoped>
.filter-tabs-group { margin-bottom: 12px; }
.filter-tabs-label { font-size: 11px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
.warehouse-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.warehouse-tab { padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; text-decoration: none; border: 1px solid var(--border); color: var(--text2); transition: all .2s; }
.warehouse-tab:hover { border-color: var(--blue); color: var(--blue); }
.warehouse-tab.active { background: var(--blue); color: #fff; border-color: var(--blue); }
.page-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; font-size: 13px; font-weight: 500; color: var(--text2); text-decoration: none; transition: all .2s; }
.page-btn:hover { background: var(--surface2); color: var(--text); }
.page-btn.active { background: var(--blue); color: #fff; }
.sync-log-row { display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px; border-radius: 10px; background: var(--surface2); font-size: 13px; }
.sync-log-row.running { border-left: 3px solid var(--blue); }
.sync-log-row.done { border-left: 3px solid var(--green); }
.sync-log-row.error { border-left: 3px solid var(--red); }
.sync-log-icon { margin-top: 1px; width: 16px; text-align: center; flex-shrink: 0; }
.sync-log-body { flex: 1; }
.sync-log-title { font-weight: 600; color: var(--text); }
.sync-log-detail { color: var(--text2); margin-top: 2px; line-height: 1.5; }
</style>
