<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    products: Object,
    categories: Array,
    itemCategories: Array,
    filters: Object,
    indexUrl: String,
    createUrl: String,
});

const form = reactive({
    search: props.filters.search ?? '',
    category_id: props.filters.category_id ?? '',
    item_category_id: props.filters.item_category_id ?? '',
});

function applyFilters() {
    router.get(props.indexUrl, form, { preserveState: true, preserveScroll: true, replace: true });
}

function rupiah(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}
</script>

<template>
    <Head title="Produk" />
    <AppLayout>
        <div class="page-header">
            <div class="page-title"><i class="fas fa-box text-blue"></i> Manajemen Produk</div>
            <Link :href="createUrl" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Produk</Link>
        </div>

        <div class="card">
            <div class="card-header">
                <form style="display:flex;gap:10px;flex:1;flex-wrap:wrap" @submit.prevent="applyFilters">
                    <input v-model="form.search" type="text" class="form-control" placeholder="Cari produk..." style="max-width:280px">
                    <select v-model="form.category_id" class="form-control form-select" style="max-width:160px">
                        <option value="">Semua Item Group</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <select v-model="form.item_category_id" class="form-control form-select" style="max-width:160px">
                        <option value="">Semua Kategori</option>
                        <option v-for="c in itemCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i> Cari</button>
                </form>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Produk</th><th>SKU</th><th>Item Group</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Status Sync</th><th>Aktif</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <tr v-if="!products.data.length">
                            <td colspan="9" style="text-align:center;padding:40px;color:var(--text3)">Belum ada produk</td>
                        </tr>
                        <tr v-for="p in products.data" :key="p.id">
                            <td class="font-medium">{{ p.name }}</td>
                            <td class="text-sm text-muted font-medium">{{ p.sku }}</td>
                            <td>{{ p.category_name ?? '-' }}</td>
                            <td>{{ p.item_category_name ?? '-' }}</td>
                            <td class="money text-blue">{{ rupiah(p.price) }}</td>
                            <td><span :class="p.is_low_stock ? 'text-red font-bold' : ''">{{ p.track_stock ? `${p.stock} ${p.unit}` : '∞' }}</span></td>
                            <td><span class="badge" :class="p.erp_synced ? 'badge-green' : 'badge-gray'">{{ p.erp_synced ? '✓ Synced' : 'Local' }}</span></td>
                            <td><span class="badge" :class="p.is_active ? 'badge-green' : 'badge-red'">{{ p.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td style="white-space:nowrap">
                                <Link :href="p.edit_url" class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i></Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="products.links.length > 3" class="pagination-wrap">
                <div class="pagination-info">
                    Menampilkan {{ products.from ?? 0 }}–{{ products.to ?? 0 }}
                    dari <strong>{{ products.total }}</strong> produk
                </div>
                <ul class="pagination">
                    <li v-for="(link, i) in products.links" :key="i" :class="{ active: link.active, disabled: !link.url }">
                        <Link v-if="link.url" :href="link.url" v-html="link.label"></Link>
                        <span v-else v-html="link.label"></span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
