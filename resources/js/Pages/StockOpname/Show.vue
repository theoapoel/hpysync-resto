<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stockOpname: Object,
    items: Object,
    summary: Object,
    updateItemsUrl: String,
    submitUrl: String,
    cancelUrl: String,
    indexUrl: String,
});

const statusColor = { draft: 'var(--yellow)', submitted: 'var(--green)', cancelled: 'var(--red)' };
const statusLabel = { draft: 'Draft', submitted: 'Submitted', cancelled: 'Dibatalkan' };

// Actual-qty per item, editable while draft. Kept separate from the
// server-shipped `items` prop so typing doesn't fight an in-flight save.
const actualQty = reactive(Object.fromEntries(props.items.data.map((i) => [i.id, i.actual_qty])));

function diffOf(item) {
    return actualQty[item.id] - item.system_qty;
}
function diffClass(diff) {
    return diff > 0 ? 'diff-plus' : diff < 0 ? 'diff-minus' : 'diff-zero';
}

// ── Debounced autosave, same 800ms batching the old inline script used ──
const saveState = ref('idle'); // idle | saving | saved | error
let pending = {};
let saveTimer = null;

function onQtyInput(item) {
    const val = parseInt(actualQty[item.id], 10) || 0;
    actualQty[item.id] = val;
    pending[item.id] = { id: item.id, actual_qty: val };

    saveState.value = 'saving';
    clearTimeout(saveTimer);
    saveTimer = setTimeout(flushSave, 800);
}

async function flushSave() {
    const toSave = Object.values(pending);
    if (!toSave.length) return;
    pending = {};

    try {
        await fetch(props.updateItemsUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
            body: JSON.stringify({ items: toSave }),
        });
        saveState.value = 'saved';
        setTimeout(() => { if (saveState.value === 'saved') saveState.value = 'idle'; }, 2000);
    } catch (e) {
        saveState.value = 'error';
    }
}

// ── Submit / cancel ────────────────────────────────────────────────
const cancelForm = useForm({});
function doCancel() {
    if (!confirm('Batalkan opname ini?')) return;
    cancelForm.post(props.cancelUrl);
}

const submitForm = useForm({});
function doSubmit() {
    if (!confirm('Submit opname dan kirim ke ERP HPY? Pastikan semua qty aktual sudah benar.')) return;
    submitForm.post(props.submitUrl);
}
</script>

<template>
    <Head :title="`Stock Opname — ${stockOpname.date}`" />
    <AppLayout>
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-clipboard-list" style="color:var(--blue);margin-right:8px;font-size:22px;vertical-align:-2px"></i>
                    Stock Opname — {{ stockOpname.date }}
                </h1>
                <p class="page-subtitle">
                    {{ stockOpname.warehouse_name }} &nbsp;·&nbsp; Oleh: {{ stockOpname.creator_name }} &nbsp;·&nbsp;
                    <span :style="{ color: statusColor[stockOpname.status], fontWeight: 600 }">{{ statusLabel[stockOpname.status] }}</span>
                </p>
            </div>
            <div v-if="stockOpname.status === 'draft'" style="display:flex;gap:10px">
                <button class="btn btn-ghost" style="color:var(--red)" @click="doCancel"><i class="fas fa-times"></i> Batalkan</button>
                <button class="btn btn-primary" @click="doSubmit"><i class="fas fa-paper-plane"></i> Submit & Sync ke ERP</button>
            </div>
        </div>

        <div v-if="stockOpname.erp_entry_issue || stockOpname.erp_entry_receipt" class="card" style="margin-bottom:20px;padding:16px 20px;display:flex;gap:24px;flex-wrap:wrap">
            <div v-if="stockOpname.erp_entry_issue">
                <div style="font-size:12px;color:var(--text2);margin-bottom:4px">Material Issue (Aktual Kurang)</div>
                <code style="font-size:13px;color:var(--red)">{{ stockOpname.erp_entry_issue }}</code>
            </div>
            <div v-if="stockOpname.erp_entry_receipt">
                <div style="font-size:12px;color:var(--text2);margin-bottom:4px">Material Receipt (Aktual Berlebih)</div>
                <code style="font-size:13px;color:var(--green)">{{ stockOpname.erp_entry_receipt }}</code>
            </div>
        </div>

        <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-boxes"></i></div>
                <div><div class="stat-value">{{ summary.total }}</div><div class="stat-label">Total Item</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-arrow-up"></i></div>
                <div><div class="stat-value">{{ summary.lebih }}</div><div class="stat-label">Berlebih → Receipt</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-arrow-down"></i></div>
                <div><div class="stat-value">{{ summary.kurang }}</div><div class="stat-label">Kurang → Issue</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--surface2)"><i class="fas fa-equals" style="color:var(--text2)"></i></div>
                <div><div class="stat-value">{{ summary.sama }}</div><div class="stat-label">Sama</div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="padding:14px 20px;display:flex;align-items:center;gap:12px">
                <span style="font-weight:600;font-size:14px">Daftar Item</span>
                <span v-if="stockOpname.status === 'draft'" class="save-indicator" :class="saveState">
                    <template v-if="saveState === 'saving'">Menyimpan...</template>
                    <template v-else-if="saveState === 'saved'">Tersimpan ✓</template>
                    <template v-else-if="saveState === 'error'" style="color:var(--red)">Gagal menyimpan!</template>
                    <template v-else>— perubahan disimpan otomatis</template>
                </span>
            </div>
            <div class="table-wrap">
                <table class="opname-table">
                    <thead>
                        <tr>
                            <th>Produk</th><th>SKU</th><th>Kategori</th>
                            <th style="text-align:right">Stok Sistem</th><th style="text-align:right">Aktual</th>
                            <th style="text-align:right">Selisih</th><th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!items.data.length">
                            <td colspan="7" style="text-align:center;padding:40px;color:var(--text2)">Tidak ada item di opname ini</td>
                        </tr>
                        <tr v-for="item in items.data" :key="item.id">
                            <td>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <img v-if="item.product_image" :src="item.product_image" style="width:28px;height:28px;border-radius:5px;object-fit:cover;border:1px solid var(--border);flex-shrink:0">
                                    <div v-else style="width:28px;height:28px;border-radius:5px;background:var(--surface2);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                        <i class="fas fa-box" style="font-size:11px;color:var(--text3)"></i>
                                    </div>
                                    <span style="font-weight:500;font-size:14px">{{ item.product_name }}</span>
                                </div>
                            </td>
                            <td><code style="font-size:11px;color:var(--text2)">{{ item.sku }}</code></td>
                            <td style="font-size:13px;color:var(--text2)">{{ item.category_name ?? '—' }}</td>
                            <td style="text-align:right;font-weight:600">{{ item.system_qty.toLocaleString('id-ID') }}</td>
                            <td style="text-align:right">
                                <input
                                    v-if="stockOpname.status === 'draft'"
                                    v-model="actualQty[item.id]"
                                    type="number" min="0"
                                    @input="onQtyInput(item)"
                                >
                                <span v-else style="font-weight:600">{{ item.actual_qty.toLocaleString('id-ID') }}</span>
                            </td>
                            <td style="text-align:right" :class="diffClass(diffOf(item))">
                                {{ diffOf(item) > 0 ? '+' : '' }}{{ diffOf(item).toLocaleString('id-ID') }}
                            </td>
                            <td style="font-size:13px">
                                <span v-if="diffOf(item) > 0" style="color:var(--green)"><i class="fas fa-arrow-up" style="font-size:10px"></i> Berlebih → Receipt</span>
                                <span v-else-if="diffOf(item) < 0" style="color:var(--red)"><i class="fas fa-arrow-down" style="font-size:10px"></i> Kurang → Issue</span>
                                <span v-else style="color:var(--text3)">Sama</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="items.links.length > 3" style="padding:14px 20px;border-top:1px solid var(--border)">
                <ul class="pagination">
                    <li v-for="(link, i) in items.links" :key="i" :class="{ active: link.active, disabled: !link.url }">
                        <Link v-if="link.url" :href="link.url" v-html="link.label"></Link>
                        <span v-else v-html="link.label"></span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.opname-table input[type=number] { width: 90px; text-align: right; padding: 4px 8px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: var(--surface); color: var(--text); }
.opname-table input[type=number]:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(66,133,244,.15); }
.diff-plus { color: var(--red); font-weight: 700; }
.diff-minus { color: var(--green); font-weight: 700; }
.diff-zero { color: var(--text3); }
.save-indicator { font-size: 12px; color: var(--text3); margin-left: 8px; transition: color .3s; }
.save-indicator.saving { color: var(--blue); }
.save-indicator.saved { color: var(--green); }
.save-indicator.error { color: var(--red); }
</style>
