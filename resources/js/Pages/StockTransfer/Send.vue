<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';
import { toast } from '@/toast';

const props = defineProps({
    warehouses: Array,
    products: Array,
    indexUrl: String,
    storeUrl: String,
    warehousesIndexUrl: String,
});

let nextKey = 0;
const rows = reactive([]);
function addRow(item = null) {
    rows.push({
        key: nextKey++,
        item_code: item?.item_code ?? '',
        item_name: item?.name ?? '',
        quantity: 1,
        unit: item?.unit ?? 'Nos',
    });
}
function removeRow(i) { rows.splice(i, 1); }

const searchQuery = ref('');
const filteredProducts = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    const list = q
        ? props.products.filter((p) =>
            p.name.toLowerCase().includes(q)
            || (p.sku && p.sku.toLowerCase().includes(q))
            || (p.item_code && p.item_code.toLowerCase().includes(q)))
        : props.products;
    return list.slice(0, 30);
});

const form = useForm({
    from_warehouse: '',
    to_warehouse: '',
    notes: '',
});

function submit() {
    if (!rows.length) {
        toast('Tambahkan minimal 1 barang.', 'error');
        return;
    }
    form.transform((data) => ({
        ...data,
        items: rows.map((r) => ({ item_code: r.item_code, item_name: r.item_name, quantity: r.quantity, unit: r.unit })),
    })).post(props.storeUrl);
}
</script>

<template>
    <Head title="Kirim Barang" />
    <AppLayout>
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-5px;margin-right:8px" class="text-blue"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    Kirim Barang
                </h1>
                <p class="page-subtitle">Buat Material Transfer ke gudang in-transit di ERP HPY</p>
            </div>
            <Link :href="indexUrl" class="btn btn-ghost">← Kembali</Link>
        </div>

        <div v-if="!warehouses.length" class="alert alert-warning mb-3">
            <strong>Perhatian:</strong> Belum ada warehouse yang diaktifkan. Silakan pull dan aktifkan warehouse di
            <a :href="warehousesIndexUrl">menu Warehouse</a>, lalu kembali ke halaman ini.
        </div>

        <form @submit.prevent="submit">
            <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">
                <div>
                    <div class="card mb-3">
                        <div class="card-header"><span class="card-title">Informasi Pengiriman</span></div>
                        <div class="card-body">
                            <div class="grid-2 gap-3">
                                <div class="form-group">
                                    <label class="form-label">Gudang Asal <span style="color:var(--red)">*</span></label>
                                    <select v-if="warehouses.length" v-model="form.from_warehouse" class="form-select" required>
                                        <option value="">-- Pilih Gudang Asal --</option>
                                        <option v-for="wh in warehouses" v-show="!wh.is_group" :key="wh.name" :value="wh.name">{{ wh.warehouse_name }}</option>
                                    </select>
                                    <input v-else v-model="form.from_warehouse" type="text" class="form-control" placeholder="Nama gudang asal" required>
                                    <p v-if="form.errors.from_warehouse" class="text-red" style="font-size:12px;margin-top:4px">{{ form.errors.from_warehouse }}</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Gudang Tujuan (In-Transit) <span style="color:var(--red)">*</span></label>
                                    <select v-if="warehouses.length" v-model="form.to_warehouse" class="form-select" required>
                                        <option value="">-- Pilih Gudang Tujuan --</option>
                                        <option v-for="wh in warehouses" v-show="!wh.is_group" :key="wh.name" :value="wh.name">{{ wh.warehouse_name }}</option>
                                    </select>
                                    <input v-else v-model="form.to_warehouse" type="text" class="form-control" placeholder="Nama gudang in-transit" required>
                                    <p v-if="form.errors.to_warehouse" class="text-red" style="font-size:12px;margin-top:4px">{{ form.errors.to_warehouse }}</p>
                                </div>
                            </div>
                            <div class="form-group" style="margin-top:12px">
                                <label class="form-label">Keterangan</label>
                                <textarea v-model="form.notes" class="form-control" rows="2" placeholder="Catatan pengiriman (opsional)"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
                            <span class="card-title">Daftar Barang</span>
                            <button type="button" class="btn btn-sm btn-outline" @click="addRow()">+ Tambah Baris</button>
                        </div>
                        <div class="card-body" style="padding:0">
                            <div style="overflow-x:auto">
                                <table style="width:100%;border-collapse:collapse">
                                    <thead>
                                        <tr style="background:var(--bg);border-bottom:1px solid var(--border)">
                                            <th style="padding:10px 12px;text-align:left;font-size:12px;color:var(--text2);font-weight:600">Kode Item</th>
                                            <th style="padding:10px 12px;text-align:left;font-size:12px;color:var(--text2);font-weight:600">Nama Barang</th>
                                            <th style="padding:10px 12px;text-align:right;font-size:12px;color:var(--text2);font-weight:600;width:100px">Qty</th>
                                            <th style="padding:10px 12px;text-align:left;font-size:12px;color:var(--text2);font-weight:600;width:90px">Satuan</th>
                                            <th style="padding:10px 12px;width:40px"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <motion.tr v-for="(row, i) in rows" :key="row.key" class="item-row" :initial="{ opacity: 0 }" :animate="{ opacity: 1 }">
                                            <td><input v-model="row.item_code" placeholder="ITEM-001" required></td>
                                            <td><input v-model="row.item_name" placeholder="Nama barang" required></td>
                                            <td style="text-align:right"><input v-model.number="row.quantity" type="number" step="0.001" min="0.001" style="text-align:right" required></td>
                                            <td><input v-model="row.unit" placeholder="Nos"></td>
                                            <td style="text-align:center">
                                                <button type="button" style="background:none;border:none;cursor:pointer;color:var(--red);font-size:18px;line-height:1" @click="removeRow(i)">×</button>
                                            </td>
                                        </motion.tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="!rows.length" style="text-align:center;padding:32px;color:var(--text2);font-size:14px">
                                Belum ada barang. Klik "+ Tambah Baris" untuk menambahkan.
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="card" style="position:sticky;top:80px">
                        <div class="card-header"><span class="card-title">Cari Produk Lokal</span></div>
                        <div class="card-body">
                            <input v-model="searchQuery" type="text" class="form-control" placeholder="Nama / SKU / Barcode..." style="margin-bottom:10px">
                            <div style="max-height:400px;overflow-y:auto">
                                <p v-if="!filteredProducts.length" style="color:var(--text2);font-size:13px;text-align:center;padding:16px">Tidak ditemukan</p>
                                <div v-for="p in filteredProducts" :key="p.id" class="product-card" @click="addRow(p)">
                                    <div class="name">{{ p.name }}</div>
                                    <div class="meta">SKU: {{ p.sku ?? '—' }} · {{ p.unit ?? 'Nos' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:20px;display:flex;gap:10px">
                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:4px"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                    {{ form.processing ? 'Mengirim...' : 'Kirim ke ERP HPY' }}
                </button>
                <Link :href="indexUrl" class="btn btn-ghost">Batal</Link>
            </div>
        </form>
    </AppLayout>
</template>

<style scoped>
.item-row td { padding: 8px 12px; border-bottom: 1px solid var(--border); }
.item-row input { background: transparent; border: none; outline: none; width: 100%; font-size: 14px; color: var(--text); }
.item-row input:focus { background: var(--bg); border-radius: 4px; padding: 2px 4px; }
.product-card { padding: 10px; border: 1px solid var(--border); border-radius: 6px; margin-bottom: 6px; cursor: pointer; transition: .15s; }
.product-card:hover { border-color: var(--blue); background: var(--bg); }
.product-card .name { font-size: 13px; font-weight: 600; color: var(--text); }
.product-card .meta { font-size: 11px; color: var(--text2); margin-top: 2px; }
</style>
