<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { toast } from '@/toast';

const props = defineProps({
    transaction: Object,
    printUrl: String,
    printKitchenUrl: String,
    indexUrl: String,
    cancelCheckUrl: String,
    cancelUrl: String,
    syncUrl: String,
});

function rupiah(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

const syncBadge = { pending: 'badge-yellow', synced: 'badge-green', failed: 'badge-red' };

const cancelling = ref(false);
const cancelLabel = ref('');

// Same flow as the old Blade page: check ERP HPY role before showing the
// confirm dialog (server re-checks on submit regardless — client-side check
// is only to avoid a confirmation that would be rejected anyway).
async function cancelTransaction() {
    cancelling.value = true;
    cancelLabel.value = 'Memeriksa wewenang...';

    try {
        const resp = await fetch(props.cancelCheckUrl, { headers: { Accept: 'application/json' } });
        const check = await resp.json();

        if (!check.allowed) {
            toast(check.error || 'Anda tidak berwenang membatalkan transaksi ini.', 'error');
            return;
        }

        const erpNote = check.erp_pos_invoice
            ? `\n\nTransaksi ini sudah tersinkron ke ERP HPY sebagai:\n${check.erp_pos_invoice}\n\nPembatalan di sini TIDAK membatalkannya di ERP. Anda harus membatalkan invoice tersebut secara manual di ERP HPY, kalau tidak penjualannya tetap terhitung di sana.`
            : '';

        if (!confirm(`Batalkan transaksi ${props.transaction.invoice_no}?\n\nStok akan dikembalikan dan transaksi ditandai dibatalkan.${erpNote}`)) {
            return;
        }

        cancelLabel.value = 'Membatalkan...';
        const cancelResp = await fetch(props.cancelUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        });
        const data = await cancelResp.json();

        if (!data.success) {
            toast(data.error || 'Gagal membatalkan transaksi', 'error');
            return;
        }

        toast('Transaksi dibatalkan', 'success');
        if (data.warning) alert(data.warning);
        router.reload();
    } catch (e) {
        toast('Gagal menghubungi server', 'error');
    } finally {
        cancelling.value = false;
    }
}

const syncing = ref(false);
async function syncThis() {
    syncing.value = true;
    try {
        const resp = await fetch(props.syncUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        });
        const data = await resp.json();
        toast(data.success ? 'Berhasil sync!' : 'Gagal: ' + (data.error || ''), data.success ? 'success' : 'error');
        if (data.success) router.reload();
    } finally {
        syncing.value = false;
    }
}
</script>

<template>
    <Head :title="`Detail Transaksi ${transaction.invoice_no}`" />
    <AppLayout>
        <div class="page-header">
            <div>
                <div class="page-title">Detail Transaksi</div>
                <div class="page-subtitle">{{ transaction.invoice_no }}</div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a :href="printKitchenUrl" target="_blank" class="btn btn-warning"><i class="fas fa-utensils"></i> Cetak Kitchen</a>
                <a :href="printUrl" target="_blank" class="btn btn-outline"><i class="fas fa-print"></i> Cetak Struk</a>
                <button v-if="transaction.status === 'completed'" class="btn btn-danger" :disabled="cancelling" @click="cancelTransaction">
                    <i :class="cancelling ? 'fas fa-spinner fa-spin' : 'fas fa-ban'"></i> {{ cancelling ? cancelLabel : 'Batalkan' }}
                </button>
                <Link :href="indexUrl" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Kembali</Link>
            </div>
        </div>

        <div class="grid-2" style="grid-template-columns:2fr 1fr">
            <div>
                <div class="card" style="margin-bottom:16px">
                    <div class="card-header"><div class="card-title">Item Transaksi</div></div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Produk</th><th>Harga</th><th>Qty</th><th>Subtotal</th></tr></thead>
                            <tbody>
                                <tr v-for="(item, i) in transaction.items" :key="i">
                                    <td>
                                        <div class="font-medium">{{ item.product_name }}</div>
                                        <div class="text-sm text-muted">SKU: {{ item.product_sku }}</div>
                                    </td>
                                    <td class="money">{{ rupiah(item.price) }}</td>
                                    <td class="font-bold">{{ item.quantity }}</td>
                                    <td class="money text-blue">{{ rupiah(item.subtotal) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div>
                <div class="card" style="margin-bottom:16px">
                    <div class="card-header"><div class="card-title">Ringkasan</div></div>
                    <div class="card-body">
                        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px"><span class="text-muted">Kasir</span><span>{{ transaction.user_name ?? '-' }}</span></div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px"><span class="text-muted">Customer</span><span>{{ transaction.customer_name }}</span></div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px"><span class="text-muted">Pembayaran</span><span class="badge badge-blue">{{ transaction.payment_method?.toUpperCase() }}</span></div>
                        <div v-if="transaction.pos_class" style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px"><span class="text-muted">POS Class</span><span class="badge badge-gray">{{ transaction.pos_class }}</span></div>
                        <hr class="divider">
                        <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:14px"><span>Subtotal</span><span>{{ rupiah(transaction.subtotal) }}</span></div>
                        <div v-if="transaction.discount_amount > 0" style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:14px;color:var(--red)"><span>Diskon</span><span>- {{ rupiah(transaction.discount_amount) }}</span></div>
                        <div v-if="transaction.tax_amount > 0" style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:14px"><span>Pajak</span><span>{{ rupiah(transaction.tax_amount) }}</span></div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:18px;font-weight:700"><span>TOTAL</span><span class="text-blue">{{ rupiah(transaction.total) }}</span></div>
                        <div style="display:flex;justify-content:space-between;font-size:14px"><span>Bayar</span><span>{{ rupiah(transaction.paid_amount) }}</span></div>
                        <div v-if="transaction.change_amount > 0" style="display:flex;justify-content:space-between;font-size:14px;color:var(--green)"><span>Kembalian</span><span>{{ rupiah(transaction.change_amount) }}</span></div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><div class="card-title">Status HPY</div></div>
                    <div class="card-body">
                        <span class="badge" :class="syncBadge[transaction.erp_sync_status] ?? 'badge-gray'">
                            {{ transaction.erp_sync_status?.toUpperCase() }}
                        </span>
                        <template v-if="transaction.erp_pos_invoice">
                            <div class="mt-2 text-sm"><strong>Doc:</strong> {{ transaction.erp_pos_invoice }}</div>
                            <div class="text-sm text-muted">{{ transaction.erp_synced_at }}</div>
                        </template>
                        <template v-if="transaction.erp_sync_error && transaction.erp_sync_status === 'failed'">
                            <div style="background:#FCE8E6;border-radius:6px;padding:10px;margin-top:8px;font-size:12px;color:var(--red)">{{ transaction.erp_sync_error }}</div>
                            <button class="btn btn-outline btn-sm mt-2" :disabled="syncing" @click="syncThis">
                                <i :class="syncing ? 'fas fa-spinner fa-spin' : 'fas fa-sync-alt'"></i> Retry Sync
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
