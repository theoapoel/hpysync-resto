<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    slices: Object,
    filters: Object,
    indexUrl: String,
    createUrl: String,
});

const form = reactive({
    status: props.filters.status ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    search: props.filters.search ?? '',
});

const hasActiveFilters = Object.values(props.filters).some((v) => !!v);

function applyFilters() {
    router.get(props.indexUrl, form, { preserveState: true, preserveScroll: true, replace: true });
}

const statusBadge = { draft: 'badge-gray', submitted: 'badge-blue', cancelled: 'badge-red' };
</script>

<template>
    <Head title="Repack — Konversi Item" />
    <AppLayout>
        <div class="page-header">
            <div>
                <div class="page-title"><i class="fas fa-scissors text-blue"></i> Repack</div>
                <div class="page-subtitle">Konversi qty item — mis. 1 Bolu dipotong menjadi 8 potong</div>
            </div>
            <Link :href="createUrl" class="btn btn-primary"><i class="fas fa-plus"></i> Buat Repack</Link>
        </div>

        <div class="card" style="margin-bottom:16px">
            <div class="card-body" style="padding:14px 20px">
                <form style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap" @submit.prevent="applyFilters">
                    <div class="form-group" style="margin-bottom:0;min-width:140px">
                        <label class="form-label" style="font-size:11px">Status</label>
                        <select v-model="form.status" class="form-control form-select" style="font-size:13px">
                            <option value="">Semua Status</option>
                            <option value="draft">Draft</option>
                            <option value="submitted">Disubmit</option>
                            <option value="cancelled">Dibatalkan</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label" style="font-size:11px">Dari</label>
                        <input v-model="form.date_from" type="date" class="form-control" style="font-size:13px">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label" style="font-size:11px">Sampai</label>
                        <input v-model="form.date_to" type="date" class="form-control" style="font-size:13px">
                    </div>
                    <div class="form-group" style="margin-bottom:0;flex:1;min-width:160px">
                        <label class="form-label" style="font-size:11px">Cari</label>
                        <input v-model="form.search" type="text" class="form-control" placeholder="No. slice…" style="font-size:13px">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
                    <Link v-if="hasActiveFilters" :href="indexUrl" class="btn btn-ghost btn-sm">Reset</Link>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No. Repack</th><th>Dibuat</th><th>Konversi</th><th>Status</th>
                            <th>ERP Stock Entry</th><th style="width:80px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!slices.data.length">
                            <td colspan="6" style="text-align:center;color:var(--text3);padding:40px">
                                <i class="fas fa-scissors" style="font-size:32px;display:block;margin-bottom:8px;opacity:.4"></i>
                                Belum ada slice.
                            </td>
                        </tr>
                        <tr v-for="s in slices.data" :key="s.id">
                            <td><Link :href="s.show_url" class="font-medium text-blue">{{ s.slice_no }}</Link></td>
                            <td>
                                <div style="font-size:13px">{{ s.creator_name }}</div>
                                <div class="text-muted" style="font-size:11px">{{ s.created_at }}</div>
                            </td>
                            <td>
                                <span class="badge badge-red">{{ s.issues_count }} keluar</span>
                                <i class="fas fa-arrow-right text-muted" style="font-size:10px;margin:0 2px"></i>
                                <span class="badge badge-green">{{ s.receipts_count }} masuk</span>
                            </td>
                            <td><span class="badge" :class="statusBadge[s.status]">{{ s.status_label }}</span></td>
                            <td>
                                <span v-if="s.erp_stock_entry" class="badge badge-green" style="font-size:11px">{{ s.erp_stock_entry }}</span>
                                <span v-else-if="s.erp_sync_status === 'failed'" class="badge badge-red" style="font-size:11px">Gagal</span>
                                <span v-else class="text-muted" style="font-size:11px">—</span>
                            </td>
                            <td><Link :href="s.show_url" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i></Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="slices.links.length > 3" style="padding:16px 20px;border-top:1px solid var(--border)">
                <ul class="pagination">
                    <li v-for="(link, i) in slices.links" :key="i" :class="{ active: link.active, disabled: !link.url }">
                        <Link v-if="link.url" :href="link.url" v-html="link.label"></Link>
                        <span v-else v-html="link.label"></span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
