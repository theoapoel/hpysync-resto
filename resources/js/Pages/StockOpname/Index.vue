<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    opnames: Object,
    createUrl: String,
});

const statusBadge = { draft: 'badge-yellow', submitted: 'badge-green', cancelled: 'badge-red' };
const statusLabel = { draft: 'Draft', submitted: 'Submitted', cancelled: 'Dibatalkan' };
</script>

<template>
    <Head title="Stock Opname" />
    <AppLayout>
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-clipboard-list" style="color:var(--blue);margin-right:8px;font-size:22px;vertical-align:-2px"></i>
                    Stock Opname
                </h1>
                <p class="page-subtitle">Hitung dan sesuaikan stok fisik dengan sistem</p>
            </div>
            <Link :href="createUrl" class="btn btn-primary"><i class="fas fa-plus"></i> Buat Opname Baru</Link>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th><th>Gudang</th><th>Dibuat Oleh</th><th>Status</th>
                            <th>Sync ERP</th><th>Stock Entry</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!opnames.data.length">
                            <td colspan="7" style="text-align:center;padding:48px;color:var(--text2)">
                                Belum ada stock opname. <Link :href="createUrl">Buat sekarang</Link>.
                            </td>
                        </tr>
                        <tr v-for="op in opnames.data" :key="op.id">
                            <td style="font-weight:500">{{ op.date }}</td>
                            <td>{{ op.warehouse_name }}</td>
                            <td style="font-size:13px;color:var(--text2)">{{ op.creator_name }}</td>
                            <td><span class="badge" :class="statusBadge[op.status]">{{ statusLabel[op.status] }}</span></td>
                            <td>
                                <span v-if="op.erp_sync_status === 'synced'" class="badge badge-green">Synced</span>
                                <span v-else-if="op.erp_sync_status === 'failed'" class="badge badge-red" :title="op.erp_sync_error">Gagal</span>
                                <span v-else style="color:var(--text3);font-size:13px">—</span>
                            </td>
                            <td style="font-size:12px;color:var(--text2)">
                                <div v-if="op.erp_entry_issue"><i class="fas fa-arrow-up" style="color:var(--red);font-size:10px"></i> {{ op.erp_entry_issue }}</div>
                                <div v-if="op.erp_entry_receipt"><i class="fas fa-arrow-down" style="color:var(--green);font-size:10px"></i> {{ op.erp_entry_receipt }}</div>
                                <span v-if="!op.erp_entry_issue && !op.erp_entry_receipt" style="color:var(--text3)">—</span>
                            </td>
                            <td>
                                <Link :href="op.show_url" class="btn btn-ghost" style="font-size:13px"><i class="fas fa-eye"></i> Detail</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="opnames.links.length > 3" style="padding:16px 20px;border-top:1px solid var(--border)">
                <ul class="pagination">
                    <li v-for="(link, i) in opnames.links" :key="i" :class="{ active: link.active, disabled: !link.url }">
                        <Link v-if="link.url" :href="link.url" v-html="link.label"></Link>
                        <span v-else v-html="link.label"></span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
