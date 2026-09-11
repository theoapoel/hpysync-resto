<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    transactions: Object,
    paymentMethods: Array,
    summary: Object,
    dateLocked: Boolean,
    filters: Object,
    posUrl: String,
    indexUrl: String,
});

const form = reactive({
    search: props.filters.search ?? '',
    erp_invoice: props.filters.erp_invoice ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    status: props.filters.status ?? '',
    payment_method: props.filters.payment_method ?? '',
});

const hasActiveFilters = computed(() => Object.values(props.filters).some((v) => !!v));

function applyFilters() {
    // preserveState keeps the form inputs as typed; only the table/summary data reloads.
    router.get(props.indexUrl, form, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    router.get(props.indexUrl, {}, { replace: true });
}

function rupiah(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

const paymentBadge = { cash: 'badge-green', card: 'badge-blue', transfer: 'badge-yellow', qris: 'badge-blue' };
const syncBadge = { pending: 'badge-yellow', synced: 'badge-green', failed: 'badge-red' };
const syncLabel = { pending: '⏳ Pending', synced: '✓ Synced', failed: '✕ Failed' };
</script>

<template>
    <Head title="Transaksi" />
    <AppLayout>
        <div class="page-header">
            <div>
                <div class="page-title"><i class="fas fa-receipt text-blue"></i> Riwayat Transaksi</div>
                <div class="page-subtitle">Total {{ transactions.total }} transaksi</div>
                <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap">
                    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:8px 14px">
                        <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Total Penjualan</div>
                        <div style="font-size:18px;font-weight:800;color:var(--blue)">{{ rupiah(summary.total_amount) }}</div>
                    </div>
                </div>
            </div>
            <!-- pos.index is still classic Blade -->
            <a :href="posUrl" class="btn btn-primary"><i class="fas fa-cash-register"></i> Buka Kasir</a>
        </div>

        <div class="card">
            <div class="card-header" style="flex-wrap:wrap;gap:10px">
                <form style="display:flex;gap:10px;flex-wrap:wrap;flex:1" @submit.prevent="applyFilters">
                    <input v-model="form.search" type="text" class="form-control" placeholder="Cari invoice..." style="max-width:200px">
                    <input v-model="form.erp_invoice" type="text" class="form-control" placeholder="Cari No. Sync ERP..." style="max-width:200px">
                    <span v-if="dateLocked" class="badge badge-yellow" style="display:inline-flex;align-items:center;gap:6px;padding:0 12px">
                        <i class="fas fa-calendar-day"></i> Hari ini
                    </span>
                    <template v-else>
                        <input v-model="form.date_from" type="date" class="form-control" style="max-width:160px">
                        <input v-model="form.date_to" type="date" class="form-control" style="max-width:160px">
                    </template>
                    <select v-model="form.status" class="form-control form-select" style="max-width:150px">
                        <option value="">Semua Status</option>
                        <option value="completed">Selesai</option>
                        <option value="cancelled">Dibatalkan</option>
                    </select>
                    <select v-model="form.payment_method" class="form-control form-select" style="max-width:170px">
                        <option value="">Semua Tipe Bayar</option>
                        <option v-for="pm in paymentMethods" :key="pm" :value="pm">{{ pm.toUpperCase() }}</option>
                    </select>
                    <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Filter</button>
                    <button v-if="hasActiveFilters" type="button" class="btn btn-ghost" @click="resetFilters">
                        <i class="fas fa-times"></i> Reset
                    </button>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice</th><th>Kasir</th><th>Customer</th><th>Total</th>
                            <th>Bayar</th><th>Status</th><th>Sync ERP</th><th>Status di HPY</th><th>Waktu</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!transactions.data.length">
                            <td colspan="10" style="text-align:center;padding:60px;color:var(--text3)">
                                <div style="font-size:40px;margin-bottom:12px">🧾</div>
                                <div style="font-size:15px;font-weight:600">Tidak ada transaksi ditemukan</div>
                                <div style="font-size:13px;margin-top:4px">Coba ubah filter pencarian</div>
                            </td>
                        </tr>
                        <tr v-for="tx in transactions.data" :key="tx.id">
                            <td><Link :href="tx.show_url" class="text-blue font-medium">{{ tx.invoice_no }}</Link></td>
                            <td class="text-sm">{{ tx.user_name ?? '-' }}</td>
                            <td class="text-sm">
                                <div v-if="tx.customer_name" style="display:flex;align-items:center;gap:6px">
                                    <div style="width:26px;height:26px;border-radius:50%;background:var(--blue-light);color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">
                                        {{ tx.customer_name.charAt(0) }}
                                    </div>
                                    {{ tx.customer_name }}
                                </div>
                                <span v-else style="color:var(--text3);font-style:italic;font-size:12px">
                                    <i class="fas fa-user" style="font-size:10px"></i> Walk-in
                                </span>
                            </td>
                            <td class="money font-bold">{{ rupiah(tx.total) }}</td>
                            <td><span class="badge" :class="paymentBadge[tx.payment_method] ?? 'badge-gray'">{{ tx.payment_method?.toUpperCase() }}</span></td>
                            <td>
                                <span class="badge" :class="tx.status === 'completed' ? 'badge-green' : 'badge-red'">
                                    {{ tx.status === 'completed' ? '✓ Selesai' : '✕ Batal' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge" :class="syncBadge[tx.erp_sync_status] ?? 'badge-gray'">
                                    {{ syncLabel[tx.erp_sync_status] ?? tx.erp_sync_status }}
                                </span>
                                <div v-if="tx.erp_pos_invoice" class="text-sm text-muted" style="margin-top:4px;font-family:monospace">{{ tx.erp_pos_invoice }}</div>
                            </td>
                            <td>
                                <span v-if="tx.erp_check.type === 'not_synced'" class="text-muted text-sm">Belum tersinkron</span>
                                <span v-else-if="tx.erp_check.type === 'unchecked'" class="badge badge-gray" title="ERP HPY tidak dapat dihubungi saat memuat halaman">? Tidak terperiksa</span>
                                <template v-else-if="tx.erp_check.type === 'not_found'">
                                    <span class="badge badge-red">✕ Tidak ada di HPY</span>
                                    <div class="text-sm text-muted" style="margin-top:4px">Dokumen sudah dihapus di HPY</div>
                                </template>
                                <template v-else-if="tx.erp_check.type === 'cancelled_at_erp'">
                                    <span class="badge badge-red">✕ Dibatalkan di HPY</span>
                                    <div v-if="tx.erp_check.stillCompletedLocally" class="text-sm text-red" style="margin-top:4px">Di sini masih selesai — nilainya ikut terhitung</div>
                                </template>
                                <template v-else-if="tx.erp_check.type === 'draft_at_erp'">
                                    <span class="badge badge-yellow">⏳ Draft di HPY</span>
                                    <div class="text-sm text-muted" style="margin-top:4px">Belum di-submit</div>
                                </template>
                                <span v-else class="badge badge-green">✓ {{ tx.erp_check.label }}</span>
                            </td>
                            <td class="text-sm text-muted">{{ tx.created_at }}</td>
                            <td style="white-space:nowrap">
                                <Link :href="tx.show_url" class="btn btn-ghost btn-sm" title="Detail"><i class="fas fa-eye"></i></Link>
                                <a :href="tx.print_url" target="_blank" class="btn btn-ghost btn-sm" title="Cetak Struk"><i class="fas fa-print"></i></a>
                                <a :href="tx.print_kitchen_url" target="_blank" class="btn btn-ghost btn-sm" title="Cetak Kitchen"><i class="fas fa-utensils"></i></a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="transactions.links.length > 3" class="pagination-wrap">
                <div class="pagination-info">
                    Menampilkan {{ transactions.from ?? 0 }}–{{ transactions.to ?? 0 }}
                    dari <strong>{{ transactions.total }}</strong> transaksi
                </div>
                <ul class="pagination">
                    <li v-for="(link, i) in transactions.links" :key="i" :class="{ active: link.active, disabled: !link.url }">
                        <Link v-if="link.url" :href="link.url" v-html="link.label"></Link>
                        <span v-else v-html="link.label"></span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
