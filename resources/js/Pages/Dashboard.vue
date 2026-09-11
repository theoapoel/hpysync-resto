<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stats: Object,
    recentTx: Array,
    topProducts: Array,
    salesChart: Array,
    posUrl: String,
    transactionsUrl: String,
});

const today = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
}).format(new Date());

function rupiah(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

const paymentBadge = { cash: 'badge-green', card: 'badge-blue', transfer: 'badge-yellow', qris: 'badge-blue' };
const syncBadge = { pending: 'badge-yellow', synced: 'badge-green', failed: 'badge-red' };
const topColors = ['#4285F4', '#34A853', '#FBBC05', '#EA4335', '#9C27B0'];

const maxChartTotal = Math.max(1, ...(props.salesChart || []).map((d) => Number(d.total)));

function chartLabel(date) {
    return new Intl.DateTimeFormat('id-ID', { weekday: 'short', day: 'numeric', month: 'short' }).format(new Date(date));
}

const stats = [
    { key: 'today_sales', label: 'Penjualan Hari Ini', icon: 'fa-money-bill-wave', color: 'blue', money: true },
    { key: 'today_count', label: 'Transaksi Hari Ini', icon: 'fa-receipt', color: 'green' },
    { key: 'total_products', label: 'Total Produk Aktif', icon: 'fa-box', color: 'yellow' },
    { key: 'low_stock', label: 'Stok Menipis', icon: 'fa-exclamation-triangle', color: 'red' },
    { key: 'total_customers', label: 'Total Customer', icon: 'fa-users', color: 'blue' },
];

function statValue(key) {
    return props.stats[key];
}
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <div class="page-header">
            <div>
                <div class="page-title">Dashboard</div>
                <div class="page-subtitle">{{ today }}</div>
            </div>
            <Link :href="posUrl" class="btn btn-primary btn-lg">
                <i class="fas fa-cash-register"></i> Buka Kasir
            </Link>
        </div>

        <div class="stat-grid">
            <motion.div
                v-for="(s, i) in stats"
                :key="s.key"
                class="stat-card"
                :initial="{ opacity: 0, y: 12 }"
                :animate="{ opacity: 1, y: 0 }"
                :transition="{ delay: i * 0.05, duration: 0.35 }"
            >
                <div class="stat-icon" :class="s.color"><i class="fas" :class="s.icon"></i></div>
                <div>
                    <div class="stat-value" :class="'text-' + s.color" :style="s.color === 'yellow' ? 'color:#E37400' : undefined">
                        <span v-if="s.money" class="money">{{ rupiah(statValue(s.key)) }}</span>
                        <span v-else>{{ statValue(s.key) }}</span>
                    </div>
                    <div class="stat-label">{{ s.label }}</div>
                </div>
            </motion.div>

            <motion.div
                class="stat-card"
                :initial="{ opacity: 0, y: 12 }"
                :animate="{ opacity: 1, y: 0 }"
                :transition="{ delay: stats.length * 0.05, duration: 0.35 }"
            >
                <div class="stat-icon" :class="props.stats.pending_sync > 0 ? 'yellow' : 'green'">
                    <i class="fas fa-sync-alt"></i>
                </div>
                <div>
                    <div class="stat-value" :style="{ color: props.stats.pending_sync > 0 ? '#E37400' : '#34A853' }">
                        {{ props.stats.pending_sync }}
                    </div>
                    <div class="stat-label">Pending Sync HPY</div>
                </div>
            </motion.div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="grid-2">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-chart-bar text-blue"></i> Penjualan 7 Hari</div>
                </div>
                <div class="card-body">
                    <div v-if="!salesChart.length" style="text-align:center;color:#80868B;padding:20px">Belum ada data</div>
                    <div v-else style="display:flex;align-items:flex-end;gap:10px;height:200px">
                        <div v-for="d in salesChart" :key="d.date" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;justify-content:flex-end">
                            <span class="text-xs text-muted">{{ rupiah(d.total) }}</span>
                            <motion.div
                                class="rounded"
                                style="width:100%;background:var(--blue)"
                                :initial="{ height: 0 }"
                                :animate="{ height: (Number(d.total) / maxChartTotal) * 140 + 'px' }"
                                :transition="{ duration: 0.5, ease: 'easeOut' }"
                            ></motion.div>
                            <span class="text-xs text-muted">{{ chartLabel(d.date) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-star" style="color:#FBBC05"></i> Produk Terlaris</div>
                    <span class="text-sm text-muted">30 hari terakhir</span>
                </div>
                <div class="card-body" style="padding:12px 0">
                    <div v-if="!topProducts.length" style="padding:20px;text-align:center;color:#80868B;font-size:14px">Belum ada data penjualan</div>
                    <div v-for="(p, i) in topProducts" :key="p.product_name" style="display:flex;align-items:center;padding:10px 20px;gap:12px">
                        <div style="width:28px;height:28px;border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0"
                             :style="{ background: topColors[i] }">{{ i + 1 }}</div>
                        <div style="flex:1">
                            <div style="font-size:14px;font-weight:600">{{ p.product_name }}</div>
                            <div style="font-size:12px;color:#80868B">{{ Number(p.total_qty).toLocaleString('id-ID') }} terjual</div>
                        </div>
                        <div class="money" style="font-size:14px;color:#4285F4">{{ rupiah(p.total_revenue) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-clock text-blue"></i> Transaksi Terbaru</div>
                <Link :href="transactionsUrl" class="btn btn-ghost btn-sm">Lihat Semua <i class="fas fa-arrow-right"></i></Link>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice</th><th>Kasir</th><th>Customer</th>
                            <th>Total</th><th>Pembayaran</th><th>Status Sync</th><th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!recentTx.length">
                            <td colspan="7" style="text-align:center;padding:40px;color:#80868B">Belum ada transaksi</td>
                        </tr>
                        <tr v-for="tx in recentTx" :key="tx.id">
                            <td><Link :href="tx.show_url" class="text-blue font-medium">{{ tx.invoice_no }}</Link></td>
                            <td>{{ tx.user_name ?? '-' }}</td>
                            <td>
                                <span v-if="tx.customer_name">{{ tx.customer_name }}</span>
                                <span v-else class="text-muted">Walk-in</span>
                            </td>
                            <td class="money">{{ rupiah(tx.total) }}</td>
                            <td><span class="badge" :class="paymentBadge[tx.payment_method] ?? 'badge-gray'">{{ tx.payment_method?.toUpperCase() }}</span></td>
                            <td><span class="badge" :class="syncBadge[tx.erp_sync_status] ?? 'badge-gray'">{{ tx.erp_sync_status?.toUpperCase() }}</span></td>
                            <td class="text-muted text-sm">{{ tx.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
