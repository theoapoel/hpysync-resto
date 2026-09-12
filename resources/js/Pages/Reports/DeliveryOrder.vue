<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    orders: Array,
    filters: Object,
    dateLocked: Boolean,
    summary: Object,
    indexUrl: String,
});

const form = reactive({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    status: props.filters.status,
    payment: props.filters.payment,
});

function applyFilters() {
    router.get(props.indexUrl, form, { preserveScroll: true });
}

function rupiah(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

const statusBadge = { draft: 'badge-gray', confirmed: 'badge-blue', delivering: 'badge-yellow', completed: 'badge-green' };
const payBadge = { unpaid: 'badge-red', partial: 'badge-yellow', paid: 'badge-green' };
const payLabel = { unpaid: 'Belum Lunas', partial: 'DP', paid: 'Lunas' };
const docColor = { Submitted: '#16a34a', Draft: '#6b7280', Dibatalkan: '#dc2626' };

// ── Per-row ERP verification panel (toggle + cache) ───────────────
const openRow = ref(null); // order id currently expanded
const erpData = reactive({}); // id -> { loading, error, data }

async function checkErp(order) {
    if (openRow.value === order.id) {
        openRow.value = null;
        return;
    }
    openRow.value = order.id;
    if (erpData[order.id]?.data) return; // already loaded, just re-show

    erpData[order.id] = { loading: true, error: null, data: null };
    try {
        const resp = await fetch(order.check_erp_url, { headers: { Accept: 'application/json' } });
        const json = await resp.json();
        if (!json.success) {
            erpData[order.id] = { loading: false, error: json.error, data: null };
            return;
        }
        erpData[order.id] = { loading: false, error: null, data: json };
    } catch (e) {
        erpData[order.id] = { loading: false, error: e.message, data: null };
    }
}
</script>

<template>
    <Head title="Laporan DO" />
    <AppLayout>
        <div class="page-header">
            <div>
                <div class="page-title"><i class="fas fa-file-invoice-dollar text-blue"></i> Laporan Delivery Order</div>
                <div class="page-subtitle">Penjualan Delivery Order dari data lokal — verifikasi Sales Invoice &amp; Delivery Note ke ERP HPY</div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form style="display:grid;grid-template-columns:repeat(4,1fr) auto;gap:12px;align-items:flex-end" @submit.prevent="applyFilters">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Dari Tgl Kirim</label>
                        <input v-model="form.date_from" type="date" class="form-control" :disabled="dateLocked">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Sampai Tgl Kirim</label>
                        <input v-model="form.date_to" type="date" class="form-control" :disabled="dateLocked">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Status Order</label>
                        <select v-model="form.status" class="form-control">
                            <option value="">Semua</option>
                            <option value="draft">Draft</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="delivering">Delivering</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Pembayaran</label>
                        <select v-model="form.payment" class="form-control">
                            <option value="">Semua</option>
                            <option value="unpaid">Belum Lunas</option>
                            <option value="partial">DP</option>
                            <option value="paid">Lunas</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="height:42px;border-radius:8px"><i class="fas fa-search"></i> Tampilkan</button>
                    <p v-if="dateLocked" style="grid-column:1/-1;margin:0;font-size:12px;color:var(--text3)">
                        <i class="fas fa-lock"></i> Laporan dikunci ke tanggal hari ini.
                    </p>
                </form>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px">
            <motion.div class="card" :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }" :transition="{ delay: 0.05, duration: 0.3 }"><div class="card-body">
                <div class="text-muted text-xs" style="text-transform:uppercase;letter-spacing:.5px">Total Penjualan</div>
                <div style="font-size:22px;font-weight:800">{{ rupiah(summary.total_sales) }}</div>
                <div class="text-muted text-xs">{{ summary.count }} order</div>
            </div></motion.div>
            <motion.div class="card" :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }" :transition="{ delay: 0.1, duration: 0.3 }"><div class="card-body">
                <div class="text-muted text-xs" style="text-transform:uppercase;letter-spacing:.5px">Sudah Dibayar</div>
                <div style="font-size:22px;font-weight:800;color:var(--green)">{{ rupiah(summary.total_paid) }}</div>
            </div></motion.div>
            <motion.div class="card" :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }" :transition="{ delay: 0.15, duration: 0.3 }"><div class="card-body">
                <div class="text-muted text-xs" style="text-transform:uppercase;letter-spacing:.5px">Outstanding (Piutang)</div>
                <div style="font-size:22px;font-weight:800" :style="{ color: summary.total_outstanding > 0 ? 'var(--red)' : 'var(--green)' }">{{ rupiah(summary.total_outstanding) }}</div>
            </div></motion.div>
            <motion.div class="card" :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }" :transition="{ delay: 0.2, duration: 0.3 }"><div class="card-body">
                <div class="text-muted text-xs" style="text-transform:uppercase;letter-spacing:.5px">Invoice HPY Terbit</div>
                <div style="font-size:22px;font-weight:800;color:var(--blue)">{{ summary.with_invoice }} / {{ summary.count }}</div>
                <div class="text-muted text-xs">DO dengan Sales Invoice</div>
            </div></motion.div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No. DO</th><th>Tgl Kirim</th><th>Customer</th>
                            <th class="text-right">Total</th><th class="text-right">Dibayar</th><th class="text-right">Outstanding</th>
                            <th>Status</th><th>Bayar</th><th>Invoice HPY</th><th style="white-space:nowrap">Verifikasi HPY</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!orders.length">
                            <td colspan="10" style="text-align:center;padding:40px;color:var(--text3)">Tidak ada Delivery Order pada rentang ini</td>
                        </tr>
                        <template v-for="o in orders" :key="o.id">
                            <tr>
                                <td><Link :href="o.show_url" class="text-blue font-medium">{{ o.order_no }}</Link></td>
                                <td>{{ o.delivery_date ?? '-' }}</td>
                                <td>{{ o.customer_name ?? '-' }}</td>
                                <td class="text-right money">{{ rupiah(o.total) }}</td>
                                <td class="text-right money">{{ rupiah(o.paid) }}</td>
                                <td class="text-right money" :style="{ color: o.outstanding > 0 ? 'var(--red)' : 'var(--green)', fontWeight: 700 }">{{ rupiah(o.outstanding) }}</td>
                                <td><span class="badge" :class="statusBadge[o.status] ?? 'badge-gray'">{{ o.status.toUpperCase() }}</span></td>
                                <td><span class="badge" :class="payBadge[o.payment_status] ?? 'badge-gray'">{{ payLabel[o.payment_status] ?? '-' }}</span></td>
                                <td>
                                    <span v-if="o.erp_sales_invoice" class="text-xs" style="font-family:monospace">{{ o.erp_sales_invoice }}</span>
                                    <span v-else class="text-muted text-xs">belum terbit</span>
                                </td>
                                <td style="white-space:nowrap">
                                    <button type="button" class="btn btn-ghost btn-sm" @click="checkErp(o)"><i class="fas fa-cloud-download-alt"></i> Cek HPY</button>
                                </td>
                            </tr>
                            <tr v-if="openRow === o.id" style="background:#f8fafc">
                                <td colspan="10" style="padding:16px 20px">
                                    <motion.div :key="o.id" :initial="{ opacity: 0, y: -6 }" :animate="{ opacity: 1, y: 0 }" :transition="{ duration: 0.2 }">
                                    <div v-if="erpData[o.id]?.loading" style="color:#5F6368"><i class="fas fa-spinner fa-spin"></i> Mengambil data dari ERP HPY...</div>
                                    <div v-else-if="erpData[o.id]?.error" style="color:#dc2626"><i class="fas fa-exclamation-triangle"></i> {{ erpData[o.id].error }}</div>
                                    <div v-else-if="erpData[o.id]?.data">
                                        <div style="font-weight:700;margin-bottom:6px"><i class="fas fa-file-invoice"></i> Sales Invoice</div>
                                        <div v-if="erpData[o.id].data.sales_invoice" style="margin-bottom:14px;font-size:13px;line-height:1.9">
                                            <span style="font-family:monospace;font-weight:600">{{ erpData[o.id].data.sales_invoice.name }}</span>
                                            <span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;color:#fff;margin-left:4px" :style="{ background: docColor[erpData[o.id].data.sales_invoice.docstatus_label] ?? '#6b7280' }">{{ erpData[o.id].data.sales_invoice.docstatus_label }}</span>
                                            &nbsp;·&nbsp; Status: <b>{{ erpData[o.id].data.sales_invoice.status ?? '-' }}</b>
                                            &nbsp;·&nbsp; Total: {{ rupiah(erpData[o.id].data.sales_invoice.grand_total) }}
                                            &nbsp;·&nbsp; Outstanding: <b :style="{ color: erpData[o.id].data.sales_invoice.outstanding > 0 ? '#dc2626' : '#16a34a' }">{{ rupiah(erpData[o.id].data.sales_invoice.outstanding) }}</b>
                                            &nbsp;·&nbsp; per_billed: {{ erpData[o.id].data.sales_invoice.per_billed }}%
                                            <template v-if="erpData[o.id].data.sales_invoice.posting_date">&nbsp;·&nbsp; {{ erpData[o.id].data.sales_invoice.posting_date }}</template>
                                        </div>
                                        <div v-else style="color:#6b7280;margin-bottom:14px;font-size:13px">Belum ada Sales Invoice untuk order ini di HPY.</div>

                                        <div style="font-weight:700;margin-bottom:6px"><i class="fas fa-truck"></i> Delivery Note</div>
                                        <div v-if="erpData[o.id].data.delivery_notes && erpData[o.id].data.delivery_notes.length" style="font-size:13px;line-height:1.9">
                                            <div v-for="(dn, i) in erpData[o.id].data.delivery_notes" :key="i">
                                                <span style="font-family:monospace;font-weight:600">{{ dn.name }}</span>
                                                <span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;color:#fff;margin-left:4px" :style="{ background: docColor[dn.docstatus_label] ?? '#6b7280' }">{{ dn.docstatus_label }}</span>
                                                &nbsp;·&nbsp; Status: <b>{{ dn.status ?? '-' }}</b>
                                                &nbsp;·&nbsp; Total: {{ rupiah(dn.grand_total) }}
                                                &nbsp;·&nbsp; per_billed: {{ dn.per_billed }}%
                                                <template v-if="dn.posting_date">&nbsp;·&nbsp; {{ dn.posting_date }}</template>
                                            </div>
                                        </div>
                                        <div v-else style="color:#6b7280;font-size:13px">Belum ada Delivery Note — barang belum diterbitkan pengirimannya.</div>

                                        <div style="font-weight:700;margin:14px 0 6px"><i class="fas fa-money-check-alt"></i> Payment Entry</div>
                                        <div v-if="erpData[o.id].data.payment_entries && erpData[o.id].data.payment_entries.length" style="font-size:13px;line-height:1.9">
                                            <div v-for="(pe, i) in erpData[o.id].data.payment_entries" :key="i">
                                                <span style="font-family:monospace;font-weight:600">{{ pe.name }}</span>
                                                <span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;color:#fff;margin-left:4px" :style="{ background: docColor[pe.docstatus_label] ?? '#6b7280' }">{{ pe.docstatus_label }}</span>
                                                &nbsp;·&nbsp; {{ pe.method ?? '-' }}
                                                &nbsp;·&nbsp; Jumlah: {{ rupiah(pe.paid_amount != null ? pe.paid_amount : pe.local_amount) }}
                                                <template v-if="pe.posting_date">&nbsp;·&nbsp; {{ pe.posting_date }}</template>
                                            </div>
                                        </div>
                                        <div v-else style="color:#6b7280;font-size:13px">Belum ada Payment Entry — pembayaran belum tersinkron ke HPY.</div>
                                    </div>
                                    </motion.div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
