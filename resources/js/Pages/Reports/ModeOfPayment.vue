<script setup>
import { reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    posProfile: String,
    scopedToUser: Boolean,
    scopedUserName: String,
    dateLocked: Boolean,
    fetchUrl: String,
});

const PAY_COLORS = ['#4285F4', '#34A853', '#FBBC04', '#EA4335', '#A142F4', '#00ACC1', '#FF7043', '#9E9E9E'];

function fmt(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 0 });
}
function fmtDate(d) {
    return new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
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
const result = ref(null); // { modes, rows, cashier_rows, totals, truncated }

async function fetchReport() {
    if (!filters.dateFrom || !filters.dateTo) {
        alert('Pilih rentang tanggal terlebih dahulu.');
        return;
    }
    loading.value = true;
    error.value = '';
    result.value = null;

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
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

function modeSummary(mode, i) {
    const m = result.value.totals.per_mode[mode] || { count: 0, total: 0 };
    const grand = result.value.totals.grand_total || 0;
    const pct = grand > 0 ? (m.total / grand * 100) : 0;
    return { color: PAY_COLORS[i % PAY_COLORS.length], count: m.count, total: m.total, pct };
}

function cell(cells, mode) {
    return cells[mode] || { total: 0, count: 0 };
}

function exportCsv() {
    if (!result.value) return;
    const modes = result.value.modes;
    const sep = ';';
    const esc = (v) => `"${String(v).replace(/"/g, '""')}"`;

    const lines = [];
    lines.push(['Tanggal', 'Transaksi', ...modes, 'Total Hari'].map(esc).join(sep));
    result.value.rows.forEach((r) => {
        const cells = modes.map((m) => (r.cells[m] || {}).total || 0);
        lines.push([r.date, r.tx_count, ...cells, r.total].map(esc).join(sep));
    });
    const per = result.value.totals.per_mode;
    lines.push(['Total', result.value.totals.grand_count, ...modes.map((m) => (per[m] || {}).total || 0), result.value.totals.grand_total].map(esc).join(sep));

    const cashierRows = result.value.cashier_rows || [];
    if (cashierRows.length) {
        lines.push('');
        lines.push(['Kasir', 'Transaksi', ...modes, 'Total'].map(esc).join(sep));
        cashierRows.forEach((r) => {
            const cells = modes.map((m) => (r.cells[m] || {}).total || 0);
            lines.push([r.cashier, r.count, ...cells, r.total].map(esc).join(sep));
        });
    }

    const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `mode-of-payment_${filters.dateFrom}_${filters.dateTo}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <Head title="Laporan Mode of Payment" />
    <AppLayout>
        <div class="page-header">
            <div>
                <div class="page-title"><i class="fas fa-wallet text-blue"></i> Laporan Mode of Payment</div>
                <div class="page-subtitle">Rekap pembayaran per hari &times; metode, langsung dari ERP HPY</div>
            </div>
            <div v-if="scopedToUser" class="badge" style="align-self:center;background:#e0e7ff;color:#4f46e5;padding:6px 12px;border-radius:20px;font-weight:700;font-size:12px">
                <i class="fas fa-user"></i> Hanya transaksi Anda ({{ scopedUserName }})
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
                <p v-if="dateLocked" style="margin:12px 0 0;font-size:12px;color:var(--text3)"><i class="fas fa-lock"></i> Laporan dikunci ke tanggal hari ini.</p>
                <div v-else style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
                    <button class="btn btn-ghost btn-sm" @click="setRange('today')">Hari Ini</button>
                    <button class="btn btn-ghost btn-sm" @click="setRange('yesterday')">Kemarin</button>
                    <button class="btn btn-ghost btn-sm" @click="setRange('week')">7 Hari Terakhir</button>
                    <button class="btn btn-ghost btn-sm" @click="setRange('month')">Bulan Ini</button>
                    <button class="btn btn-ghost btn-sm" @click="setRange('last_month')">Bulan Lalu</button>
                </div>
            </div>
        </div>

        <div v-if="error" class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> {{ error }}</div>

        <template v-if="result">
            <div v-if="result.truncated" class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Data terlalu banyak — hanya 1.000 transaksi pertama yang ditampilkan. Perkecil rentang tanggal untuk melihat data lengkap.
            </div>

            <div v-if="!result.rows.length || !result.modes.length" class="card">
                <div class="card-body" style="text-align:center;padding:48px 0;color:#80868B">
                    <i class="fas fa-inbox" style="font-size:32px;margin-bottom:12px;display:block"></i>
                    Tidak ada data pembayaran pada rentang tanggal ini.
                </div>
            </div>

            <template v-else>
                <div class="stat-grid" style="margin-bottom:20px">
                    <div v-for="(mode, i) in result.modes" :key="mode" class="stat-card">
                        <div class="stat-icon" :style="{ background: modeSummary(mode, i).color + '1A', color: modeSummary(mode, i).color }"><i class="fas fa-money-check-alt"></i></div>
                        <div>
                            <div class="stat-value money" :style="{ color: modeSummary(mode, i).color }">{{ fmt(modeSummary(mode, i).total) }}</div>
                            <div class="stat-label">{{ mode }} · {{ Number(modeSummary(mode, i).count).toLocaleString('id-ID') }} pmt · {{ modeSummary(mode, i).pct.toFixed(1) }}%</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-table text-blue"></i> Rekap Harian per Metode</div>
                        <div style="display:flex;gap:8px;align-items:center">
                            <span class="text-sm text-muted">{{ filters.dateFrom }} — {{ filters.dateTo }}</span>
                            <button class="btn btn-ghost btn-sm" @click="exportCsv"><i class="fas fa-file-csv"></i> Export CSV</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th style="position:sticky;left:0;background:inherit">Tanggal</th>
                                    <th style="text-align:right">Trx</th>
                                    <th v-for="(m, i) in result.modes" :key="m" style="text-align:right">
                                        <span style="display:inline-block;width:9px;height:9px;border-radius:2px;margin-right:5px" :style="{ background: PAY_COLORS[i % PAY_COLORS.length] }"></span>{{ m }}
                                    </th>
                                    <th style="text-align:right">Total Hari</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in result.rows" :key="r.date">
                                    <td style="position:sticky;left:0;background:#fff"><span class="font-medium">{{ fmtDate(r.date) }}</span></td>
                                    <td style="text-align:right" class="text-muted">{{ Number(r.tx_count).toLocaleString('id-ID') }}</td>
                                    <td v-for="m in result.modes" :key="m" style="text-align:right" class="money">
                                        <span v-if="cell(r.cells, m).total">{{ fmt(cell(r.cells, m).total) }}</span>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td style="text-align:right" class="money font-medium">{{ fmt(r.total) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr style="border-top:2px solid #DADCE0;font-weight:600">
                                    <td style="position:sticky;left:0;background:#F8F9FA">Total</td>
                                    <td style="text-align:right">{{ Number(result.totals.grand_count).toLocaleString('id-ID') }}</td>
                                    <td v-for="m in result.modes" :key="m" style="text-align:right" class="money">{{ fmt((result.totals.per_mode[m] || {}).total) }}</td>
                                    <td style="text-align:right" class="money text-blue">{{ fmt(result.totals.grand_total) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="card" style="margin-top:20px">
                    <div class="card-header"><div class="card-title"><i class="fas fa-user-tie text-blue"></i> Rekap per Kasir</div></div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th style="position:sticky;left:0;background:inherit">Kasir</th>
                                    <th style="text-align:right">Trx</th>
                                    <th v-for="(m, i) in result.modes" :key="m" style="text-align:right">
                                        <span style="display:inline-block;width:9px;height:9px;border-radius:2px;margin-right:5px" :style="{ background: PAY_COLORS[i % PAY_COLORS.length] }"></span>{{ m }}
                                    </th>
                                    <th style="text-align:right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!result.cashier_rows || !result.cashier_rows.length">
                                    <td :colspan="result.modes.length + 3" style="text-align:center;padding:16px" class="text-muted">Tidak ada data kasir</td>
                                </tr>
                                <tr v-for="r in result.cashier_rows" :key="r.email">
                                    <td style="position:sticky;left:0;background:#fff"><span class="font-medium">{{ r.cashier }}</span></td>
                                    <td style="text-align:right" class="text-muted">{{ Number(r.count).toLocaleString('id-ID') }}</td>
                                    <td v-for="m in result.modes" :key="m" style="text-align:right" class="money">
                                        <span v-if="cell(r.cells, m).total">{{ fmt(cell(r.cells, m).total) }}</span>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td style="text-align:right" class="money font-medium">{{ fmt(r.total) }}</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="result.cashier_rows && result.cashier_rows.length">
                                <tr style="border-top:2px solid #DADCE0;font-weight:600">
                                    <td style="position:sticky;left:0;background:#F8F9FA">Total</td>
                                    <td style="text-align:right">{{ Number(result.totals.grand_count).toLocaleString('id-ID') }}</td>
                                    <td v-for="m in result.modes" :key="m" style="text-align:right" class="money">{{ fmt((result.totals.per_mode[m] || {}).total) }}</td>
                                    <td style="text-align:right" class="money text-blue">{{ fmt(result.totals.grand_total) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </template>
        </template>
    </AppLayout>
</template>
