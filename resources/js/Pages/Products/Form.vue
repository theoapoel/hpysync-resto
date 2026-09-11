<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    product: Object,
    categories: Array,
    itemCategories: Array,
    indexUrl: String,
    submitUrl: String,
});

const isEdit = !!props.product;

// useForm keeps input values across a validation-error round trip and
// exposes per-field errors — same guarantee old() + @error gave in Blade.
const form = useForm({
    name: props.product?.name ?? '',
    sku: props.product?.sku ?? '',
    barcode: props.product?.barcode ?? '',
    category_id: props.product?.category_id ?? '',
    item_category_id: props.product?.item_category_id ?? '',
    price: props.product?.price ?? '',
    cost_price: props.product?.cost_price ?? '',
    stock: props.product?.stock ?? 0,
    min_stock: props.product?.min_stock ?? 0,
    unit: props.product?.unit ?? 'pcs',
    tax_rate: props.product?.tax_rate ?? 0,
    description: props.product?.description ?? '',
    is_active: props.product?.is_active ?? true,
    track_stock: props.product?.track_stock ?? true,
});

function submit() {
    if (isEdit) {
        form.put(props.submitUrl);
    } else {
        form.post(props.submitUrl);
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Produk' : 'Tambah Produk'" />
    <AppLayout>
        <div class="page-header">
            <div class="page-title">{{ isEdit ? 'Edit Produk' : 'Tambah Produk' }}</div>
            <Link :href="indexUrl" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Kembali</Link>
        </div>

        <div class="card" style="max-width:760px">
            <div class="card-body">
                <form @submit.prevent="submit">
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Nama Produk *</label>
                            <input v-model="form.name" type="text" class="form-control" required>
                            <div v-if="form.errors.name" class="text-red text-sm mt-1">{{ form.errors.name }}</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">SKU *</label>
                            <input v-model="form.sku" type="text" class="form-control" required>
                            <div v-if="form.errors.sku" class="text-red text-sm mt-1">{{ form.errors.sku }}</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Barcode</label>
                            <input v-model="form.barcode" type="text" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Item Group</label>
                            <select v-model="form.category_id" class="form-control form-select">
                                <option value="">-- Tanpa Item Group --</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Kategori</label>
                            <select v-model="form.item_category_id" class="form-control form-select">
                                <option value="">-- Tanpa Kategori --</option>
                                <option v-for="c in itemCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Harga Jual *</label>
                            <input v-model="form.price" type="number" class="form-control" min="0" required>
                            <div v-if="form.errors.price" class="text-red text-sm mt-1">{{ form.errors.price }}</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Harga Pokok</label>
                            <input v-model="form.cost_price" type="number" class="form-control" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stok *</label>
                            <input v-model="form.stock" type="number" class="form-control" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stok Minimum</label>
                            <input v-model="form.min_stock" type="number" class="form-control" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Satuan</label>
                            <input v-model="form.unit" type="text" class="form-control" placeholder="pcs, kg, liter...">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Pajak (%)</label>
                            <input v-model="form.tax_rate" type="number" class="form-control" min="0" max="100" step="0.01">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deskripsi</label>
                        <textarea v-model="form.description" class="form-control" rows="3"></textarea>
                    </div>
                    <div style="display:flex;gap:20px;margin-bottom:16px">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
                            <input v-model="form.is_active" type="checkbox"> Produk Aktif
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
                            <input v-model="form.track_stock" type="checkbox"> Pantau Stok
                        </label>
                    </div>
                    <div style="display:flex;gap:10px">
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            <i class="fas fa-save"></i> {{ isEdit ? 'Perbarui' : 'Simpan' }}
                        </button>
                        <Link :href="indexUrl" class="btn btn-ghost">Batal</Link>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
