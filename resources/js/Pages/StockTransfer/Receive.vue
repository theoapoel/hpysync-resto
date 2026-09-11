<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';
import { toast } from '@/toast';

const props = defineProps({
    warehouses: Array,
    pendingEntries: Array,
    products: Array,
    indexUrl: String,
    storeUrl: String,
    loadItemsUrl: String,
    warehousesIndexUrl: String,
});

const form = useForm({
    from_warehouse: '',
    to_warehouse: '',
    notes: '',
    erp_source_entry: '',
});

let nextKey = 0;
const rows = reactive([]);
function addRow(item = null) {
    rows.push({
        key: nextKey++,
        item_code: item?.item_code ?? '',
        item_name: item?.name ?? '',
        quantity: item?.quantity ?? 1,
        actual_quantity: item?.quantity ?? 1,
        unit: item?.unit ?? 'Nos',
    });
}
function removeRow(i) { rows.splice(i, 1); }
function diffOf(row) {
    return (Number(row.actual_quantity) || 0) - (Number(row.quantity) || 0);
}

// ── Scan / load flow ──────────────────────────────────────────────
const scanInput = ref(null);
const scanValue = ref('');
const scanState = ref(''); // '' | scanning | loaded | error
const scanFeedback = reactive({ type: '', message: '' });
const dropdownEntry = ref('');
const formVisible = ref(false);

const scanBadge = {
    '': { label: 'Siap Scan', style: 'background:var(--surface2);color:var(--text2)' },
    scanning: { label: 'Memuat…', style: 'background:var(--blue-light);color:var(--blue)' },
    loaded: { label: 'Dimuat', style: 'background:#E6F4EA;color:var(--green)' },
    error: { label: 'Error', style: 'background:#FCE8E6;color:var(--red)' },
};
const feedbackColor = { success: 'var(--green)', error: 'var(--red)', info: 'var(--blue-dark)', '': 'var(--text2)' };

async function triggerLoad() {
    const val = scanValue.value.trim();
    if (!val) return;
    await doLoad(val);
}

async function loadFromDropdown() {
    if (!dropdownEntry.value) { toast('Pilih Stock Entry terlebih dahulu.', 'error'); return; }
    scanValue.value = dropdownEntry.value;
    await doLoad(dropdownEntry.value);
}

async function doLoad(entryName) {
    scanState.value = 'scanning';
    scanFeedback.type = 'info';
    scanFeedback.message = `Memuat dokumen ${entryName}…`;

    try {
        const resp = await fetch(props.loadItemsUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
            body: JSON.stringify({ entry_name: entryName }),
        });
        const res = await resp.json();

        if (!res.success) {
            scanState.value = 'error';
            scanFeedback.type = 'error';
            scanFeedback.message = res.error ?? 'Dokumen tidak ditemukan.';
            nextTick(() => scanInput.value?.select());
            return;
        }

        if (res.from_warehouse) form.from_warehouse = res.from_warehouse;
        if (res.to_warehouse) form.to_warehouse = res.to_warehouse;
        form.erp_source_entry = entryName;

        rows.splice(0, rows.length);
        res.items.forEach((i) => addRow({ item_code: i.item_code, name: i.item_name, quantity: i.quantity, unit: i.unit }));

        formVisible.value = true;
        scanState.value = 'loaded';
        scanFeedback.type = 'success';
        scanFeedback.message = `${entryName} — ${res.items.length} item dimuat.`;
    } catch (e) {
        scanState.value = 'error';
        scanFeedback.type = 'error';
        scanFeedback.message = 'Gagal: ' + e.message;
        nextTick(() => scanInput.value?.select());
    }
}

function resetScan() {
    scanValue.value = '';
    scanState.value = '';
    scanFeedback.type = '';
    scanFeedback.message = '';
    formVisible.value = false;
    rows.splice(0, rows.length);
    nextTick(() => scanInput.value?.focus());
}

// ── Product search sidebar ─────────────────────────────────────────
const searchQuery = ref('');
const filteredProducts = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    const list = q
        ? props.products.filter((p) => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q)))
        : props.products;
    return list.slice(0, 30);
});

onMounted(() => scanInput.value?.focus());

function submit() {
    if (!rows.length) {
        toast('Tambahkan minimal 1 barang.', 'error');
        return;
    }
    form.transform((data) => ({
        ...data,
        items: rows.map((r) => ({
            item_code: r.item_code, item_name: r.item_name,
            quantity: r.quantity, actual_quantity: r.actual_quantity, unit: r.unit,
        })),
    })).post(props.storeUrl);
}
</script>

<template>
    <Head title="Terima Barang" />
    <AppLayout>
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-5px;margin-right:8px" class="text-blue"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                    Terima Barang
                </h1>
                <p class="page-subtitle">Penerimaan barang — scan nomor dokumen pengiriman</p>
            </div>
            <Link :href="indexUrl" class="btn btn-ghost">← Kembali</Link>
        </div>

        <div v-if="!warehouses.length" class="alert alert-warning mb-3">
            <strong>Perhatian:</strong> Belum ada warehouse yang diaktifkan. Silakan pull dan aktifkan warehouse di
            <a :href="warehousesIndexUrl">menu Warehouse</a>, lalu kembali ke halaman ini.
        </div>

        <div class="card mb-3 scan-card" :class="scanState">
            <div class="card-body" style="padding:20px 24px">
                <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                    <div style="flex-shrink:0;width:48px;height:48px;border-radius:12px;background:var(--blue-light);display:flex;align-items:center;justify-content:center">
                        <i class="fas fa-barcode" style="font-size:22px;color:var(--blue)"></i>
                    </div>
                    <div style="flex:1;min-width:260px">
                        <div style="font-size:13px;font-weight:600;color:var(--text2);margin-bottom:6px">
                            Scan Nomor Dokumen
                            <span style="margin-left:8px;font-size:11px;padding:2px 8px;border-radius:10px;font-weight:500" :style="scanBadge[scanState].style">{{ scanBadge[scanState].label }}</span>
                        </div>
                        <div style="display:flex;gap:8px">
                            <input
                                ref="scanInput" v-model="scanValue" type="text" class="form-control"
                                placeholder="Arahkan barcode scanner ke sini, atau ketik nomor dokumen…" autocomplete="off"
                                :readonly="scanState === 'loaded'"
                                style="font-family:'Google Sans',sans-serif;font-size:15px;letter-spacing:.5px;font-weight:600"
                                @keydown.enter.prevent="triggerLoad"
                            >
                            <button type="button" class="btn btn-primary" :disabled="scanState === 'scanning' || scanState === 'loaded'" @click="triggerLoad">
                                <i class="fas fa-download"></i> Muat
                            </button>
                            <button v-if="scanState === 'loaded'" type="button" class="btn btn-ghost" @click="resetScan"><i class="fas fa-times"></i></button>
                        </div>
                        <div style="margin-top:6px;font-size:13px;min-height:18px" :style="{ color: feedbackColor[scanFeedback.type] }">
                            <template v-if="scanFeedback.message">
                                <i v-if="scanState === 'error'" class="fas fa-exclamation-circle"></i>
                                <i v-else-if="scanState === 'loaded'" class="fas fa-check-circle"></i>
                                {{ scanFeedback.message }}
                                <a v-if="scanState === 'loaded'" href="#" style="color:var(--blue);text-decoration:underline;margin-left:8px" @click.prevent="resetScan">Scan dokumen lain</a>
                            </template>
                        </div>
                    </div>
                </div>

                <div v-if="pendingEntries.length && scanState !== 'loaded'" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                    <div style="font-size:12px;color:var(--text3);margin-bottom:6px;font-weight:500"><i class="fas fa-list"></i> Atau pilih dari daftar:</div>
                    <div style="display:flex;gap:8px">
                        <select v-model="dropdownEntry" class="form-select" style="flex:1">
                            <option value="">-- Pilih Stock Entry --</option>
                            <option v-for="entry in pendingEntries" :key="entry.name" :value="entry.name">
                                {{ entry.name }} · {{ entry.posting_date }}<template v-if="entry.from_warehouse"> · {{ entry.from_warehouse }}</template>
                            </option>
                        </select>
                        <button type="button" class="btn btn-outline" @click="loadFromDropdown">Muat Item</button>
                    </div>
                </div>
            </div>
        </div>

        <form v-if="formVisible" @submit.prevent="submit">
            <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">
                <div>
                    <div class="card mb-3">
                        <div class="card-header"><span class="card-title">Informasi Penerimaan</span></div>
                        <div class="card-body">
                            <div class="grid-2 gap-3">
                                <div class="form-group">
                                    <label class="form-label">Gudang Asal (In-Transit) <span style="color:var(--red)">*</span></label>
                                    <select v-if="warehouses.length" v-model="form.from_warehouse" class="form-select" required>
                                        <option value="">-- Pilih Gudang Asal --</option>
                                        <option v-for="wh in warehouses" v-show="!wh.is_group" :key="wh.name" :value="wh.name">{{ wh.warehouse_name }}</option>
                                    </select>
                                    <input v-else v-model="form.from_warehouse" type="text" class="form-control" placeholder="Gudang in-transit" required>
                                    <p v-if="form.errors.from_warehouse" class="text-red" style="font-size:12px;margin-top:4px">{{ form.errors.from_warehouse }}</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Gudang Tujuan <span style="color:var(--red)">*</span></label>
                                    <select v-if="warehouses.length" v-model="form.to_warehouse" class="form-select" required>
                                        <option value="">-- Pilih Gudang Tujuan --</option>
                                        <option v-for="wh in warehouses" v-show="!wh.is_group" :key="wh.name" :value="wh.name">{{ wh.warehouse_name }}</option>
                                    </select>
                                    <input v-else v-model="form.to_warehouse" type="text" class="form-control" placeholder="Gudang tujuan" required>
                                    <p v-if="form.errors.to_warehouse" class="text-red" style="font-size:12px;margin-top:4px">{{ form.errors.to_warehouse }}</p>
                                </div>
                            </div>
                            <div class="form-group" style="margin-top:12px">
                                <label class="form-label">Keterangan</label>
                                <textarea v-model="form.notes" class="form-control" rows="2" placeholder="Catatan penerimaan (opsional)"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
                            <span class="card-title">Daftar Barang Diterima</span>
                            <button type="button" class="btn btn-sm btn-outline" @click="addRow()">+ Tambah Baris</button>
                        </div>
                        <div class="card-body" style="padding:0">
                            <div style="overflow-x:auto">
                                <table style="width:100%;border-collapse:collapse">
                                    <thead>
                                        <tr style="background:var(--bg);border-bottom:1px solid var(--border)">
                                            <th style="padding:10px 12px;font-size:12px;color:var(--text2);font-weight:600;text-align:left">Kode Item</th>
                                            <th style="padding:10px 12px;font-size:12px;color:var(--text2);font-weight:600;text-align:left">Nama Barang</th>
                                            <th style="padding:10px 12px;font-size:12px;color:var(--text2);font-weight:600;text-align:right;width:95px">Qty Kirim</th>
                                            <th style="padding:10px 12px;font-size:12px;color:var(--text2);font-weight:600;text-align:right;width:110px">Qty Terima</th>
                                            <th style="padding:10px 12px;font-size:12px;color:var(--text2);font-weight:600;text-align:left;width:90px">Satuan</th>
                                            <th style="padding:10px 12px;width:40px"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <motion.tr v-for="(row, i) in rows" :key="row.key" class="item-row" :initial="{ opacity: 0 }" :animate="{ opacity: 1 }">
                                            <td><input v-model="row.item_code" placeholder="ITEM-001" required></td>
                                            <td><input v-model="row.item_name" placeholder="Nama barang" required></td>
                                            <td style="text-align:right"><input :value="row.quantity" type="number" step="0.001" min="0.001" style="text-align:right" readonly title="Qty sesuai dokumen pengiriman"></td>
                                            <td style="text-align:right">
                                                <input v-model.number="row.actual_quantity" type="number" step="0.001" min="0.001" style="text-align:right" required title="Qty aktual yang diterima">
                                                <span v-if="diffOf(row) !== 0" class="qty-diff">Selisih: {{ diffOf(row).toFixed(3) }}</span>
                                            </td>
                                            <td><input v-model="row.unit" placeholder="Nos"></td>
                                            <td style="text-align:center">
                                                <button type="button" style="background:none;border:none;cursor:pointer;color:var(--red);font-size:18px;line-height:1" @click="removeRow(i)">×</button>
                                            </td>
                                        </motion.tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="!rows.length" style="text-align:center;padding:32px;color:var(--text2);font-size:14px">Belum ada barang.</div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="card" style="position:sticky;top:80px">
                        <div class="card-header"><span class="card-title">Cari Produk Lokal</span></div>
                        <div class="card-body">
                            <input v-model="searchQuery" type="text" class="form-control" placeholder="Nama / SKU…" style="margin-bottom:10px">
                            <div style="max-height:400px;overflow-y:auto">
                                <p v-if="!filteredProducts.length" style="color:var(--text2);font-size:13px;text-align:center;padding:16px">Tidak ditemukan</p>
                                <div v-for="p in filteredProducts" :key="p.id" class="product-card" @click="addRow({ ...p, quantity: 1 })">
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
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:4px"><path d="M20 6L9 17l-5-5"/></svg>
                    {{ form.processing ? 'Menyimpan…' : 'Konfirmasi Penerimaan' }}
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
.item-row .qty-diff { font-size: 11px; color: var(--red); display: block; margin-top: 2px; }
.product-card { padding: 10px; border: 1px solid var(--border); border-radius: 6px; margin-bottom: 6px; cursor: pointer; transition: .15s; }
.product-card:hover { border-color: var(--blue); background: var(--bg); }
.product-card .name { font-size: 13px; font-weight: 600; color: var(--text); }
.product-card .meta { font-size: 11px; color: var(--text2); margin-top: 2px; }
.scan-card { transition: border-color .2s, box-shadow .2s; }
.scan-card.scanning { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(66,133,244,.15); }
.scan-card.loaded { border-color: var(--green); box-shadow: 0 0 0 3px rgba(52,168,83,.12); }
.scan-card.error { border-color: var(--red); box-shadow: 0 0 0 3px rgba(234,67,53,.12); }
</style>
