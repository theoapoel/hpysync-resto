<script setup>
import { computed, reactive, ref, shallowRef } from 'vue';
import { Head } from '@inertiajs/vue3';
import { AnimatePresence, motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';
import { loadChartJs } from '@/loadChartJs';

const props = defineProps({
    posProfile: String,
    dateLocked: Boolean,
    fetchUrl: String,
    detailUrlBase: String,
});

const PAY_COLORS = ['#4285F4', '#34A853', '#FBBC04', '#EA4335', '#A142F4', '#00ACC1', '#FF7043', '#9E9E9E'];

function fmt(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 0 });
}

function iso(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
}

const filters = reactive({
    dateFrom: props.dateLocked ? iso(new Date()) : iso(new Date(new Date().getFullYear(), new Date().getMonth(), 1)),
    dateTo: iso(new Date()),
    posProfile: props.posProfile ?? '',
});

function setRange(range) {
    const today = new Date();
    let from, to;
    if (range === 'today') {
        from = to = iso(today);
    } else if (range === 'yesterday') {
        const y = new Date(today); y.setDate(y.getDate() - 1);
        from = to = iso(y);
    } else if (range === 'week') {
        const w = new Date(today); w.setDate(w.getDate() - 6);
        from = iso(w); to = iso(today);
    } else if (range === 'month') {
        from = iso(new Date(today.getFullYear(), today.getMonth(), 1));
        to = iso(today);
    } else if (range === 'last_month') {
        from = iso(new Date(today.getFullYear(), today.getMonth() - 1, 1));
        to = iso(new Date(today.getFullYear(), today.getMonth(), 0));
    }
    filters.dateFrom = from;
    filters.dateTo = to;
}

const loading = ref(false);
const error = ref('');
const result = ref(null); // { stats, invoices, truncated }
const tableSearch = ref('');

const filteredInvoices = computed(() => {
    if (!result.value) return [];
    const q = tableSearch.value.trim().toLowerCase();
    if (!q) return result.value.invoices;
    return result.value.invoices.filter((inv) => (
        inv.name + ' ' + (inv.local_invoice || '') + ' ' + (inv.customer_name || '') + ' ' + (inv.customer || '') + ' ' + (inv.mode_of_payment || '')
    ).toLowerCase().includes(q));
});

const dailyChartCanvas = ref(null);
const paymentChartCanvas = ref(null);
let dailyChartInstance = shallowRef(null);
let paymentChartInstance = shallowRef(null);

async function fetchReport() {
    if (!filters.dateFrom || !filters.dateTo) {
        alert('Pilih rentang tanggal terlebih dahulu.');
        return;
    }
    loading.value = true;
    error.value = '';
    // Keep the previous result visible (dimmed via .is-refetching) while the
    // new one loads instead of clearing it — an empty flash mid-refetch reads
    // as broken, not as "loading".

    try {
        const resp = await fetch(props.fetchUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ date_from: filters.dateFrom, date_to: filters.dateTo, pos_profile: filters.posProfile.trim() }),
        });
        const json = await resp.json();
        if (!json.success) throw new Error(json.error || 'Gagal mengambil data dari ERP HPY.');

        result.value = json;
        tableSearch.value = '';
        await renderCharts(json.stats);
    } catch (e) {
        error.value = e.message;
        result.value = null;
    } finally {
        loading.value = false;
    }
}

async function renderCharts(stats) {
    const Chart = await loadChartJs();
    const daily = stats.daily_data;

    const labels = Object.keys(daily).map((d) => new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' }));
    const totals = Object.values(daily).map((v) => v.total);
    const counts = Object.values(daily).map((v) => v.count);

    dailyChartInstance.value?.destroy();
    dailyChartInstance.value = new Chart(dailyChartCanvas.value, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Penjualan', data: totals, backgroundColor: '#4285F4', borderRadius: 5, borderSkipped: false, yAxisID: 'y' },
                { label: 'Transaksi', data: counts, type: 'line', borderColor: '#34A853', backgroundColor: 'rgba(52,168,83,.12)', pointBackgroundColor: '#34A853', tension: 0.3, yAxisID: 'y2' },
            ],
        },
        options: {
            responsive: true,
            interaction: { mode: 'index' },
            plugins: {
                legend: { position: 'top' },
                tooltip: { callbacks: { label: (ctx) => ctx.dataset.label === 'Penjualan' ? 'Penjualan: ' + fmt(ctx.raw) : 'Transaksi: ' + ctx.raw } },
            },
            scales: {
                y: { ticks: { callback: (v) => 'Rp ' + (v / 1000).toFixed(0) + 'k' }, grid: { color: '#F1F3F4' } },
                y2: { position: 'right', grid: { display: false }, ticks: { precision: 0 } },
                x: { grid: { display: false } },
            },
        },
    });

    const rows = stats.payment_data || [];
    paymentChartInstance.value?.destroy();
    paymentChartInstance.value = new Chart(paymentChartCanvas.value, {
        type: 'doughnut',
        data: {
            labels: rows.map((r) => r.mode_of_payment),
            datasets: [{ data: rows.map((r) => r.total), backgroundColor: rows.map((_, i) => PAY_COLORS[i % PAY_COLORS.length]), borderWidth: 2, borderColor: '#fff' }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const total = ctx.dataset.data.reduce((s, v) => s + v, 0);
                            const pct = total > 0 ? (ctx.raw / total * 100).toFixed(1) : 0;
                            return `${ctx.label}: ${fmt(ctx.raw)} (${pct}%)`;
                        },
                    },
                },
            },
            cutout: '60%',
        },
    });
}

const paymentRowsWithPct = computed(() => {
    if (!result.value) return { rows: [], grandTotal: 0, grandCount: 0 };
    const rows = result.value.stats.payment_data || [];
    const grandTotal = rows.reduce((s, r) => s + Number(r.total), 0);
    const grandCount = rows.reduce((s, r) => s + Number(r.count), 0);
    return { rows, grandTotal, grandCount };
});

// ── Detail modal ────────────────────────────────────────────────────
const showDetail = ref(false);
const detailLoading = ref(false);
const detailError = ref('');
const detail = ref(null);
const detailName = ref('');

async function openDetail(name) {
    detailName.value = name;
    showDetail.value = true;
    detailLoading.value = true;
    detailError.value = '';
    detail.value = null;

    try {
        const resp = await fetch(`${props.detailUrlBase}/${encodeURIComponent(name)}`);
        const json = await resp.json();
        if (!json.success) throw new Error(json.error);
        detail.value = json.data;
    } catch (e) {
        detailError.value = e.message;
    } finally {
        detailLoading.value = false;
    }
}
</script>

<template>
    <Head title="Laporan Online HPY" />
    <AppLayout>
        <div class="page-header">
            <div>
                <div class="page-title"><i class="fas fa-cloud-download-alt text-blue"></i> Laporan Online HPY</div>
                <div class="page-subtitle">Tarik data transaksi historis langsung dari ERP HPY</div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr auto auto;gap:12px;align-items:flex-end">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Dari Tanggal</label>
                        <input v-model="filters.dateFrom" type="date" class="form-control" :disabled="dateLocked">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Sampai Tanggal</label>
                        <input v-model="filters.dateTo" type="date" class="form-control" :disabled="dateLocked">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">POS Profile</label>
                        <input v-model="filters.posProfile" type="text" class="form-control" placeholder="Kosong = semua profile" style="min-width:200px">
                    </div>
                    <button class="btn btn-primary" style="height:42px;border-radius:8px" :disabled="loading" @click="fetchReport">
                        <span v-if="loading" class="spinner"></span>
                        <i v-else class="fas fa-search"></i> {{ loading ? 'Mengambil...' : 'Tampilkan' }}
                    </button>
                </div>

                <p v-if="dateLocked" style="margin:12px 0 0;font-size:12px;color:var(--text3)">
                    <i class="fas fa-lock"></i> Laporan dikunci ke tanggal hari ini.
                </p>
                <div v-else style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
                    <button class="btn btn-ghost btn-sm" @click="setRange('today')">Hari Ini</button>
                    <button class="btn btn-ghost btn-sm" @click="setRange('yesterday')">Kemarin</button>
                    <button class="btn btn-ghost btn-sm" @click="setRange('week')">7 Hari Terakhir</button>
                    <button class="btn btn-ghost btn-sm" @click="setRange('month')">Bulan Ini</button>
                    <button class="btn btn-ghost btn-sm" @click="setRange('last_month')">Bulan Lalu</button>
                </div>
            </div>
        </div>

        <AnimatePresence mode="wait">
            <motion.div v-if="error" key="error" :initial="{ opacity: 0, y: -6 }" :animate="{ opacity: 1, y: 0 }" :exit="{ opacity: 0 }">
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> {{ error }}</div>
            </motion.div>
        </AnimatePresence>

        <motion.div
            v-if="result"
            class="report-result"
            :class="{ 'is-refetching': loading }"
            :initial="{ opacity: 0 }" :animate="{ opacity: 1 }" :transition="{ duration: 0.25 }"
        >
            <div v-if="result.truncated" class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                Data terlalu banyak — <strong>tabel di bawah</strong> hanya menampilkan 1.000 transaksi pertama.
                Angka ringkasan, grafik, dan rincian metode bayar tetap mencakup seluruh rentang tanggal.
                Perkecil rentang tanggal bila ingin tabelnya lengkap.
            </div>

            <div class="stat-grid" style="margin-bottom:20px">
                <motion.div class="stat-card" :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }" :transition="{ delay: 0.05, duration: 0.3 }">
                    <div class="stat-icon blue"><i class="fas fa-receipt"></i></div>
                    <div><div class="stat-value text-blue">{{ result.stats.total_count.toLocaleString('id-ID') }}</div><div class="stat-label">Total Transaksi</div></div>
                </motion.div>
                <motion.div class="stat-card" :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }" :transition="{ delay: 0.1, duration: 0.3 }">
                    <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
                    <div><div class="stat-value text-green money">{{ fmt(result.stats.total_sales) }}</div><div class="stat-label">Total Penjualan</div></div>
                </motion.div>
                <motion.div class="stat-card" :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }" :transition="{ delay: 0.15, duration: 0.3 }">
                    <div class="stat-icon yellow"><i class="fas fa-calculator"></i></div>
                    <div><div class="stat-value money" style="color:#E37400">{{ fmt(result.stats.avg_per_tx) }}</div><div class="stat-label">Rata-rata per Transaksi</div></div>
                </motion.div>
                <motion.div class="stat-card" :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }" :transition="{ delay: 0.2, duration: 0.3 }">
                    <div class="stat-icon blue"><i class="fas fa-calendar-check"></i></div>
                    <div><div class="stat-value text-blue">{{ Object.keys(result.stats.daily_data).length }}</div><div class="stat-label">Hari dengan Transaksi</div></div>
                </motion.div>
            </div>

            <div class="card" style="margin-bottom:20px">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-chart-bar text-blue"></i> Penjualan per Hari</div>
                    <span class="text-sm text-muted">{{ filters.dateFrom }} — {{ filters.dateTo }}</span>
                </div>
                <div class="card-body"><canvas ref="dailyChartCanvas" height="120"></canvas></div>
            </div>

            <div class="card" style="margin-bottom:20px">
                <div class="card-header"><div class="card-title"><i class="fas fa-wallet text-blue"></i> Metode Pembayaran</div></div>
                <div class="card-body" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.2fr);gap:24px;align-items:center">
                    <div style="max-width:260px;margin:0 auto;width:100%"><canvas ref="paymentChartCanvas"></canvas></div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Metode</th><th style="text-align:right">Transaksi</th><th style="text-align:right">Total</th><th style="text-align:right">%</th></tr></thead>
                            <tbody>
                                <tr v-if="!paymentRowsWithPct.rows.length"><td colspan="4" class="text-muted" style="text-align:center;padding:24px">Tidak ada data pembayaran</td></tr>
                                <tr v-for="(r, i) in paymentRowsWithPct.rows" :key="r.mode_of_payment">
                                    <td>
                                        <span style="display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:8px" :style="{ background: PAY_COLORS[i % PAY_COLORS.length] }"></span>
                                        <span class="font-medium">{{ r.mode_of_payment }}</span>
                                    </td>
                                    <td style="text-align:right">{{ Number(r.count).toLocaleString('id-ID') }}</td>
                                    <td style="text-align:right" class="money">{{ fmt(r.total) }}</td>
                                    <td style="text-align:right" class="text-muted">{{ (paymentRowsWithPct.grandTotal > 0 ? r.total / paymentRowsWithPct.grandTotal * 100 : 0).toFixed(1) }}%</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="paymentRowsWithPct.rows.length">
                                <tr style="border-top:2px solid #DADCE0;font-weight:600">
                                    <td>Total</td>
                                    <td style="text-align:right">{{ paymentRowsWithPct.grandCount.toLocaleString('id-ID') }}</td>
                                    <td style="text-align:right" class="money">{{ fmt(paymentRowsWithPct.grandTotal) }}</td>
                                    <td style="text-align:right">100%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-list text-blue"></i> Daftar Transaksi</div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <input v-model="tableSearch" type="text" placeholder="Cari invoice / customer..." class="form-control form-control-sm" style="width:220px;height:34px;border-radius:20px;font-size:13px">
                        <span class="text-sm text-muted">{{ filteredInvoices.length === result.invoices.length ? result.invoices.length + ' transaksi' : filteredInvoices.length + ' dari ' + result.invoices.length + ' transaksi' }}</span>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Invoice HPY</th><th>Invoice Lokal</th><th>Tanggal</th><th>Waktu</th>
                                <th>Customer</th><th>Grand Total</th><th>Metode Bayar</th><th>POS Profile</th><th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="inv in filteredInvoices" :key="inv.name">
                                <td><span class="font-medium text-blue" style="cursor:pointer" @click="openDetail(inv.name)">{{ inv.name }}</span></td>
                                <td class="text-sm"><span v-if="inv.local_invoice" class="font-medium">{{ inv.local_invoice }}</span><span v-else class="text-muted">-</span></td>
                                <td>{{ inv.posting_date }}</td>
                                <td class="text-muted text-sm">{{ (inv.posting_time || '').substring(0, 5) }}</td>
                                <td>
                                    <span v-if="inv.customer_name || inv.customer">{{ inv.customer_name || inv.customer }}</span>
                                    <span v-else class="text-muted">Walk-in</span>
                                </td>
                                <td class="money">{{ fmt(inv.grand_total) }}</td>
                                <td class="text-sm">{{ inv.mode_of_payment || '-' }}</td>
                                <td class="text-sm text-muted">{{ inv.pos_profile || '-' }}</td>
                                <td><span class="badge" :class="inv.status === 'Submitted' ? 'badge-green' : 'badge-red'">{{ inv.status.toUpperCase() }}</span></td>
                                <td><button class="btn btn-ghost btn-sm" @click="openDetail(inv.name)"><i class="fas fa-eye"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </motion.div>

        <AnimatePresence>
        <motion.div v-if="showDetail" class="modal-overlay show" :initial="{ opacity: 0 }" :animate="{ opacity: 1 }" :exit="{ opacity: 0 }" @click.self="showDetail = false">
            <motion.div class="modal" style="max-width:680px" :initial="{ opacity: 0, y: -20, scale: 0.97 }" :animate="{ opacity: 1, y: 0, scale: 1 }">
                <div class="modal-header">
                    <div class="modal-title">{{ detailName }}</div>
                    <button class="btn btn-ghost btn-sm" style="padding:4px 8px;border-radius:50%" @click="showDetail = false"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <div v-if="detailLoading" style="text-align:center;padding:40px">
                        <div class="spinner" style="border-top-color:#4285F4;border-color:#E8F0FE;width:32px;height:32px;border-width:3px;margin:0 auto 12px"></div>
                        <div class="text-muted">Memuat detail...</div>
                    </div>
                    <div v-else-if="detailError" class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> {{ detailError }}</div>
                    <div v-else-if="detail">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;font-size:14px">
                            <div><div class="text-muted text-xs" style="margin-bottom:2px">Tanggal & Waktu</div><div class="font-medium">{{ detail.posting_date }} {{ (detail.posting_time || '').substring(0, 5) }}</div></div>
                            <div><div class="text-muted text-xs" style="margin-bottom:2px">Customer</div><div class="font-medium">{{ detail.customer_name || detail.customer || 'Walk-in' }}</div></div>
                            <div><div class="text-muted text-xs" style="margin-bottom:2px">POS Profile</div><div class="font-medium">{{ detail.pos_profile || '-' }}</div></div>
                            <div><div class="text-muted text-xs" style="margin-bottom:2px">Status</div><div><span class="badge" :class="detail.status === 'Submitted' ? 'badge-green' : 'badge-red'">{{ (detail.status || '-').toUpperCase() }}</span></div></div>
                        </div>

                        <div style="margin-bottom:16px">
                            <div class="font-medium" style="margin-bottom:8px;font-size:13px;color:#5F6368;text-transform:uppercase;letter-spacing:.5px">Item</div>
                            <div class="table-wrap">
                                <table style="font-size:13px">
                                    <thead><tr><th>Kode</th><th>Nama Item</th><th style="text-align:right">Qty</th><th style="text-align:right">Rate</th><th style="text-align:right">Jumlah</th></tr></thead>
                                    <tbody>
                                        <tr v-for="(it, i) in detail.items || []" :key="'item-' + i">
                                            <td>{{ it.item_code }}</td><td>{{ it.item_name }}</td>
                                            <td style="text-align:right">{{ it.qty }} {{ it.uom || '' }}</td>
                                            <td style="text-align:right" class="money">{{ fmt(it.rate) }}</td>
                                            <td style="text-align:right" class="money">{{ fmt(it.amount) }}</td>
                                        </tr>
                                        <tr v-for="(t, i) in detail.taxes || []" :key="'tax-' + i">
                                            <td colspan="4" style="color:#5F6368">{{ t.description }}</td>
                                            <td style="text-align:right" class="money">{{ fmt(t.tax_amount) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                            <div>
                                <div class="font-medium" style="margin-bottom:8px;font-size:13px;color:#5F6368;text-transform:uppercase;letter-spacing:.5px">Pembayaran</div>
                                <div v-if="!detail.payments || !detail.payments.length" class="text-muted text-sm">Tidak ada data pembayaran</div>
                                <div v-for="(p, i) in detail.payments || []" :key="i" style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #F1F3F4">
                                    <span>{{ p.mode_of_payment }}</span><span class="money font-medium">{{ fmt(p.amount) }}</span>
                                </div>
                            </div>
                            <div style="background:#F8F9FA;border-radius:8px;padding:16px">
                                <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px"><span class="text-muted">Net Total</span><span class="money">{{ fmt(detail.net_total) }}</span></div>
                                <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px"><span class="text-muted">Pajak & Biaya</span><span class="money">{{ fmt(detail.total_taxes_and_charges || 0) }}</span></div>
                                <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:2px solid #DADCE0"><span class="font-medium">Grand Total</span><span class="money font-medium text-blue" style="font-size:16px">{{ fmt(detail.grand_total) }}</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </motion.div>
        </motion.div>
        </AnimatePresence>
    </AppLayout>
</template>

<style scoped>
.report-result { transition: opacity .2s ease; }
.report-result.is-refetching { opacity: .5; pointer-events: none; }
</style>
