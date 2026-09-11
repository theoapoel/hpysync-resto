<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    slice: Object,
    indexUrl: String,
    submitUrl: String,
    cancelUrl: String,
    syncUrl: String,
});

const statusBadge = { draft: 'badge-gray', submitted: 'badge-blue', cancelled: 'badge-red' };

const submitForm = useForm({});
function doSubmit() {
    if (!confirm('Proses konversi ini ke ERP HPY (Repack)? Stok sumber akan keluar dan hasil masuk.')) return;
    submitForm.post(props.submitUrl);
}

const cancelForm = useForm({});
function doCancel() {
    const msg = props.slice.status === 'submitted'
        ? 'Batalkan slice ini? Stock Entry (Repack) di ERP akan dibatalkan dan pergerakan stok dibalik.'
        : 'Batalkan repack ini?';
    if (!confirm(msg)) return;
    cancelForm.post(props.cancelUrl);
}

const syncing = ref(false);
const syncResult = ref(null); // {success, message}
async function syncErp() {
    syncing.value = true;
    syncResult.value = null;
    try {
        const resp = await fetch(props.syncUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        });
        const data = await resp.json();
        if (data.success) {
            syncResult.value = { success: true, message: `Berhasil! Stock Entry: ${data.docname}` };
            setTimeout(() => router.reload(), 2000);
        } else {
            syncResult.value = { success: false, message: data.error };
        }
    } catch (e) {
        syncResult.value = { success: false, message: e.message };
    } finally {
        syncing.value = false;
    }
}
</script>

<template>
    <Head :title="`Detail Repack ${slice.slice_no}`" />
    <AppLayout>
        <div class="page-header">
            <div>
                <div class="page-title"><i class="fas fa-scissors text-blue"></i> {{ slice.slice_no }}</div>
                <div class="page-subtitle">Dibuat oleh {{ slice.creator_name }} pada {{ slice.created_at }}</div>
            </div>
            <div style="display:flex;gap:8px">
                <template v-if="slice.status === 'draft'">
                    <button class="btn btn-primary" :disabled="submitForm.processing" @click="doSubmit"><i class="fas fa-paper-plane"></i> Submit ke ERP</button>
                    <button class="btn btn-ghost" style="color:var(--red)" :disabled="cancelForm.processing" @click="doCancel"><i class="fas fa-times"></i> Batalkan</button>
                </template>
                <template v-else-if="slice.status === 'submitted'">
                    <button v-if="slice.erp_sync_status !== 'synced'" class="btn btn-outline" :disabled="syncing" @click="syncErp">
                        <i class="fas fa-sync-alt" :class="{ 'fa-spin': syncing }"></i> Sync ERP
                    </button>
                    <button class="btn btn-ghost" style="color:var(--red)" :disabled="cancelForm.processing" @click="doCancel"><i class="fas fa-times"></i> Batalkan</button>
                </template>
                <Link :href="indexUrl" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Kembali</Link>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">
            <div>
                <div class="card" style="margin-bottom:16px">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-info-circle text-blue"></i> Detail</div>
                        <span class="badge" :class="statusBadge[slice.status]">{{ slice.status_label }}</span>
                    </div>
                    <div v-if="slice.notes" class="card-body">
                        <div class="text-muted" style="font-size:12px;margin-bottom:2px">Catatan</div>
                        <div>{{ slice.notes }}</div>
                    </div>
                </div>

                <div class="card" style="margin-bottom:16px">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-arrow-up-from-bracket text-red"></i> Item Dibuang (Issue)</div>
                        <span class="badge badge-red">{{ slice.issues.length }} item</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>#</th><th>Item</th><th style="text-align:right">Qty</th><th>Gudang Asal</th><th>Catatan</th></tr></thead>
                            <tbody>
                                <tr v-if="!slice.issues.length"><td colspan="5" class="text-muted" style="text-align:center;padding:16px">Tidak ada item.</td></tr>
                                <tr v-for="(line, i) in slice.issues" :key="i">
                                    <td class="text-muted">{{ i + 1 }}</td>
                                    <td>
                                        <div class="font-medium">{{ line.item_name }}</div>
                                        <div class="text-muted" style="font-size:11px;font-family:monospace">{{ line.item_code || '—' }}</div>
                                    </td>
                                    <td style="text-align:right;font-weight:700">{{ Number(line.qty).toFixed(2) }} <span class="text-muted" style="font-weight:400;font-size:11px">{{ line.uom }}</span></td>
                                    <td style="font-size:12px">{{ line.warehouse || 'Gudang default' }}</td>
                                    <td class="text-muted" style="font-size:12px">{{ line.notes || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-arrow-down-to-bracket text-green"></i> Jadi Item (Receipt)</div>
                        <span class="badge badge-green">{{ slice.receipts.length }} item</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>#</th><th>Item</th><th style="text-align:right">Qty</th><th>Catatan</th></tr></thead>
                            <tbody>
                                <tr v-if="!slice.receipts.length"><td colspan="4" class="text-muted" style="text-align:center;padding:16px">Tidak ada item.</td></tr>
                                <tr v-for="(line, i) in slice.receipts" :key="i">
                                    <td class="text-muted">{{ i + 1 }}</td>
                                    <td>
                                        <div class="font-medium">{{ line.item_name }}</div>
                                        <div class="text-muted" style="font-size:11px;font-family:monospace">{{ line.item_code || '—' }}</div>
                                    </td>
                                    <td style="text-align:right;font-weight:700">{{ Number(line.qty).toFixed(2) }} <span class="text-muted" style="font-weight:400;font-size:11px">{{ line.uom }}</span></td>
                                    <td class="text-muted" style="font-size:12px">{{ line.notes || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="card-header"><div class="card-title"><i class="fas fa-cloud"></i> Status ERP</div></div>
                    <div class="card-body">
                        <div v-if="slice.erp_stock_entry" class="alert alert-success" style="margin-bottom:0">
                            <i class="fas fa-check-circle"></i> Stock Entry (Repack): <strong>{{ slice.erp_stock_entry }}</strong>
                        </div>
                        <div v-else-if="slice.erp_sync_status === 'failed'" class="alert alert-danger" style="margin-bottom:0">
                            <i class="fas fa-exclamation-circle"></i> Sync gagal: {{ slice.erp_sync_error }}
                        </div>
                        <div v-else class="alert" style="background:var(--surface2);margin-bottom:0">
                            <i class="fas fa-clock"></i> Belum diproses ke ERP.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="syncResult" style="margin-top:16px">
            <div class="alert" :class="syncResult.success ? 'alert-success' : 'alert-danger'">
                <i :class="syncResult.success ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'"></i> {{ syncResult.message }}
            </div>
        </div>
    </AppLayout>
</template>
