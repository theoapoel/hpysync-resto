<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';
import { toast } from '@/toast';
import ProductSearchField from '@/Components/ProductSearchField.vue';

const props = defineProps({
    products: Array,
    warehouses: Array,
    defaultWarehouse: String,
    indexUrl: String,
    storeUrl: String,
});

let nextId = 0;
function newIssue() {
    return { key: nextId++, productId: null, productName: '', qty: 1, warehouse: '', notes: '', invalid: false, whInvalid: false };
}
function newReceipt() {
    return { key: nextId++, productId: null, productName: '', qty: 1, notes: '', invalid: false };
}

const issues = reactive([newIssue()]);
const receipts = reactive([newReceipt()]);

function addIssue() { issues.push(newIssue()); }
function addReceipt() { receipts.push(newReceipt()); }
function removeIssue(i) { issues.splice(i, 1); }
function removeReceipt(i) { receipts.splice(i, 1); }

const validIssues = computed(() => issues.filter((r) => r.productId));
const validReceipts = computed(() => receipts.filter((r) => r.productId));

const form = useForm({ notes: '' });

function submit() {
    let firstInvalid = null;
    issues.forEach((row) => {
        row.invalid = !row.productId;
        row.whInvalid = !!row.productId && !row.warehouse;
        if ((row.invalid || row.whInvalid) && !firstInvalid) firstInvalid = row;
    });
    receipts.forEach((row) => {
        row.invalid = !row.productId;
        if (row.invalid && !firstInvalid) firstInvalid = row;
    });

    if (!validIssues.value.length || !validReceipts.value.length) {
        toast('Minimal 1 item issue dan 1 item receipt harus valid.', 'error');
        return;
    }
    if (firstInvalid) {
        toast('Lengkapi item yang valid dan pilih gudang untuk setiap baris issue.', 'error');
        return;
    }

    form.transform((data) => ({
        ...data,
        issues: validIssues.value.map((r) => ({ product_id: r.productId, qty: r.qty, warehouse: r.warehouse, notes: r.notes })),
        receipts: validReceipts.value.map((r) => ({ product_id: r.productId, qty: r.qty, notes: r.notes })),
    })).post(props.storeUrl);
}
</script>

<template>
    <Head title="Buat Repack" />
    <AppLayout>
        <div class="page-header">
            <div>
                <div class="page-title"><i class="fas fa-scissors text-blue"></i> Buat Repack</div>
                <div class="page-subtitle">Tabel 1: item yang dibuang (issue) · Tabel 2: item hasil (receipt) — diproses sebagai Repack di ERP HPY</div>
            </div>
            <Link :href="indexUrl" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Kembali</Link>
        </div>

        <form @submit.prevent="submit">
            <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">
                <div>
                    <div class="card" style="margin-bottom:16px">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-arrow-up-from-bracket text-red"></i> Item Dibuang (Issue)</div>
                            <button type="button" class="btn btn-outline btn-sm" @click="addIssue"><i class="fas fa-plus"></i> Tambah Baris</button>
                        </div>
                        <div class="card-body" style="padding:0">
                            <div class="issue-grid grid-head" style="padding:10px 16px;background:var(--surface2)">
                                <span>Item</span><span>Qty</span><span>Gudang Asal</span><span>Catatan</span><span></span>
                            </div>
                            <div style="padding:12px 16px">
                                <motion.div
                                    v-for="(row, i) in issues" :key="row.key"
                                    class="issue-grid item-row"
                                    :initial="{ opacity: 0, y: -6 }" :animate="{ opacity: 1, y: 0 }"
                                >
                                    <ProductSearchField v-model:product-id="row.productId" v-model:product-name="row.productName" :products="products" :invalid="row.invalid" />
                                    <input v-model.number="row.qty" type="number" class="form-control" min="0.01" step="0.01" style="text-align:right;font-size:13px" required>
                                    <select v-model="row.warehouse" class="form-control" :class="{ invalid: row.whInvalid }" style="font-size:12px" required>
                                        <option value="" disabled>— Pilih gudang —</option>
                                        <option v-for="wh in warehouses" :key="wh.name" :value="wh.name">{{ wh.label || wh.name }}</option>
                                    </select>
                                    <input v-model="row.notes" type="text" class="form-control" placeholder="Catatan..." style="font-size:13px">
                                    <button type="button" style="border:none;background:none;cursor:pointer;color:var(--red);font-size:16px" @click="removeIssue(i)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </motion.div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-arrow-down-to-bracket text-green"></i> Jadi Item (Receipt)</div>
                            <button type="button" class="btn btn-outline btn-sm" @click="addReceipt"><i class="fas fa-plus"></i> Tambah Baris</button>
                        </div>
                        <div class="card-body" style="padding:0">
                            <div class="receipt-grid grid-head" style="padding:10px 16px;background:var(--surface2)">
                                <span>Item</span><span>Qty</span><span>Catatan</span><span></span>
                            </div>
                            <div style="padding:12px 16px">
                                <motion.div
                                    v-for="(row, i) in receipts" :key="row.key"
                                    class="receipt-grid item-row"
                                    :initial="{ opacity: 0, y: -6 }" :animate="{ opacity: 1, y: 0 }"
                                >
                                    <ProductSearchField v-model:product-id="row.productId" v-model:product-name="row.productName" :products="products" :invalid="row.invalid" />
                                    <input v-model.number="row.qty" type="number" class="form-control" min="0.01" step="0.01" style="text-align:right;font-size:13px" required>
                                    <input v-model="row.notes" type="text" class="form-control" placeholder="Catatan..." style="font-size:13px">
                                    <button type="button" style="border:none;background:none;cursor:pointer;color:var(--red);font-size:16px" @click="removeReceipt(i)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </motion.div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="position:sticky;top:80px">
                    <div class="card" style="margin-bottom:16px">
                        <div class="card-header"><div class="card-title"><i class="fas fa-info-circle text-blue"></i> Ringkasan</div></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Catatan</label>
                                <input v-model="form.notes" type="text" class="form-control" placeholder="Keterangan (opsional)">
                            </div>
                            <div v-if="!validIssues.length && !validReceipts.length" style="font-size:13px;color:var(--text3)">
                                Lengkapi item issue &amp; receipt.
                            </div>
                            <template v-else>
                                <div style="font-size:11px;font-weight:700;color:var(--red);text-transform:uppercase;letter-spacing:.5px;margin:6px 0 4px">Dibuang (Issue)</div>
                                <div v-if="!validIssues.length" style="font-size:12px;color:var(--text3)">—</div>
                                <div v-for="r in validIssues" :key="r.key" style="font-size:13px;padding:3px 0">{{ Number(r.qty).toLocaleString('id-ID') }} {{ r.productName }}</div>
                                <div style="text-align:center;color:var(--text3);margin:6px 0"><i class="fas fa-arrow-down"></i></div>
                                <div style="font-size:11px;font-weight:700;color:var(--green);text-transform:uppercase;letter-spacing:.5px;margin:6px 0 4px">Jadi (Receipt)</div>
                                <div v-if="!validReceipts.length" style="font-size:12px;color:var(--text3)">—</div>
                                <div v-for="r in validReceipts" :key="r.key" style="font-size:13px;padding:3px 0">{{ Number(r.qty).toLocaleString('id-ID') }} {{ r.productName }}</div>
                            </template>
                        </div>
                    </div>
                    <div class="alert alert-info" style="margin-bottom:12px;font-size:13px">
                        <i class="fas fa-info-circle"></i>
                        Semua item di tabel <strong>Issue</strong> keluar stok; semua item di tabel <strong>Receipt</strong> masuk stok. Setelah disimpan, klik <strong>Submit ke ERP</strong>.
                    </div>
                    <button type="submit" class="btn btn-primary w-full btn-lg" style="border-radius:10px" :disabled="form.processing">
                        <i class="fas fa-save"></i> Simpan sebagai Draft
                    </button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>

<style scoped>
.item-row { margin-bottom: 8px; }
.issue-grid { display: grid; grid-template-columns: 1.8fr 90px 1.3fr 1fr 28px; gap: 8px; align-items: center; }
.receipt-grid { display: grid; grid-template-columns: 1.8fr 90px 1fr 28px; gap: 8px; align-items: center; }
.grid-head { font-size: 11px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 0; }
select.invalid { border-color: var(--red); }
</style>
