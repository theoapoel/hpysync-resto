<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    transfers: Object,
    filters: Object,
    indexUrl: String,
    reportUrl: String,
    sendUrl: String,
    receiveUrl: String,
});

const form = reactive({
    search: props.filters.search ?? '',
    type: props.filters.type ?? '',
    status: props.filters.status ?? '',
});
const hasActiveFilters = Object.values(props.filters).some((v) => !!v);

function applyFilters() {
    router.get(props.indexUrl, form, { preserveState: true, preserveScroll: true, replace: true });
}

const localStatusBadge = { received: 'badge-green', sent: 'badge-blue' };
const localStatusLabel = { received: 'Diterima', sent: 'Dikirim' };
const syncBadge = { synced: 'badge-green', failed: 'badge-red', pending: 'badge-yellow' };
</script>

<template>
    <Head title="Transfer Barang" />
    <AppLayout>
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-6px;margin-right:8px" class="text-blue">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                    Transfer Barang
                </h1>
                <p class="page-subtitle">Kirim &amp; terima barang melalui ERP HPY Material Transfer</p>
            </div>
            <div style="display:flex;gap:8px">
                <a :href="reportUrl" class="btn btn-ghost btn-sm"><i class="fas fa-file-alt"></i> Laporan Detail</a>
                <Link :href="receiveUrl" class="btn btn-outline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:4px"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                    Terima Barang
                </Link>
                <Link :href="sendUrl" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:4px"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    Kirim Barang
                </Link>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="padding:16px 20px">
                <form style="display:flex;gap:10px;flex-wrap:wrap;align-items:center" @submit.prevent="applyFilters">
                    <input v-model="form.search" type="text" class="form-control" placeholder="Cari no. transfer / gudang..." style="width:260px">
                    <select v-model="form.type" class="form-select" style="width:150px">
                        <option value="">Semua Tipe</option>
                        <option value="outgoing">Kirim</option>
                        <option value="incoming">Terima</option>
                    </select>
                    <select v-model="form.status" class="form-select" style="width:150px">
                        <option value="">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="cancelled">Dibatalkan</option>
                    </select>
                    <button type="submit" class="btn btn-outline">Filter</button>
                    <Link v-if="hasActiveFilters" :href="indexUrl" class="btn btn-ghost">Reset</Link>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No. Transfer</th><th>Tipe</th><th>Dari Gudang</th><th>Ke Gudang</th>
                            <th>ERP HPY Entry</th><th>Status</th><th>Sync</th><th>Tanggal</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!transfers.data.length">
                            <td colspan="9" style="text-align:center;padding:48px;color:var(--text2)">Belum ada data transfer barang</td>
                        </tr>
                        <tr v-for="t in transfers.data" :key="t.id">
                            <td><strong>{{ t.transfer_no }}</strong></td>
                            <td>
                                <span v-if="t.type === 'outgoing'" class="badge badge-blue">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px;margin-right:3px"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                                    Kirim
                                </span>
                                <span v-else class="badge badge-green">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px;margin-right:3px"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                                    Terima
                                </span>
                            </td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" :title="t.from_warehouse">{{ t.from_warehouse }}</td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" :title="t.to_warehouse">{{ t.to_warehouse }}</td>
                            <td>
                                <code v-if="t.erp_stock_entry" style="font-size:12px;color:var(--blue)">{{ t.erp_stock_entry }}</code>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td><span class="badge" :class="localStatusBadge[t.local_status] ?? 'badge-gray'">{{ localStatusLabel[t.local_status] ?? 'Draft' }}</span></td>
                            <td><span class="badge" :class="syncBadge[t.erp_sync_status] ?? 'badge-yellow'">{{ t.erp_sync_status === 'synced' ? 'Synced' : (t.erp_sync_status === 'failed' ? 'Failed' : 'Pending') }}</span></td>
                            <td style="white-space:nowrap;color:var(--text2);font-size:13px">{{ t.created_at }}</td>
                            <td><Link :href="t.show_url" class="btn btn-sm btn-outline">Detail</Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="transfers.links.length > 3" class="pagination-wrap">
                <span style="color:var(--text2);font-size:13px">Menampilkan {{ transfers.from ?? 0 }}–{{ transfers.to ?? 0 }} dari {{ transfers.total }}</span>
                <div class="pagination">
                    <Link v-for="(link, i) in transfers.links" :key="i" v-show="link.url" :href="link.url" class="page-btn" :class="{ active: link.active }" v-html="link.label"></Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.page-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; font-size: 13px; font-weight: 500; color: var(--text2); text-decoration: none; transition: all .2s; }
.page-btn:hover { background: var(--surface2); color: var(--text); }
.page-btn.active { background: var(--blue); color: #fff; }
</style>
