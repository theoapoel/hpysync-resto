<script setup>
import { useForm } from '@inertiajs/vue3';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    transfer: Object,
    canRetry: Boolean,
    retryUrl: String,
    suratJalanUrl: String,
    indexUrl: String,
});

function rupiah3(n) {
    return Number(n ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
}

const retryForm = useForm({});
function doRetry() {
    retryForm.post(props.retryUrl);
}
</script>

<template>
    <Head :title="transfer.transfer_no" />
    <AppLayout>
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <svg v-if="transfer.type === 'outgoing'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-5px;margin-right:8px" class="text-blue"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-5px;margin-right:8px" class="text-blue"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                    {{ transfer.transfer_no }}
                </h1>
                <p class="page-subtitle">
                    {{ transfer.type === 'outgoing' ? 'Pengiriman Barang' : 'Penerimaan Barang' }} ·
                    dibuat oleh {{ transfer.user_name }} · {{ transfer.created_at }}
                </p>
            </div>
            <div style="display:flex;gap:8px">
                <button v-if="canRetry" class="btn btn-outline" :disabled="retryForm.processing" @click="doRetry">Retry Sync ERP HPY</button>
                <a :href="suratJalanUrl" target="_blank" class="btn btn-primary btn-sm">
                    <i class="fas fa-print"></i> {{ transfer.type === 'incoming' ? 'Cetak Bukti Terima' : 'Cetak Surat Jalan' }}
                </a>
                <Link :href="indexUrl" class="btn btn-ghost">← Kembali</Link>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">
            <div>
                <div class="card">
                    <div class="card-header"><span class="card-title">Daftar Barang</span></div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th><th>Kode Item</th><th>Nama Barang</th>
                                    <th style="text-align:right">Qty {{ transfer.is_incoming ? 'Kirim' : '' }}</th>
                                    <template v-if="transfer.is_incoming">
                                        <th style="text-align:right">Qty Terima</th>
                                        <th style="text-align:right">Selisih</th>
                                    </template>
                                    <th>Satuan</th><th>Produk Lokal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, i) in transfer.items" :key="i">
                                    <td style="color:var(--text2)">{{ i + 1 }}</td>
                                    <td><code style="font-size:12px">{{ item.item_code }}</code></td>
                                    <td>{{ item.item_name }}</td>
                                    <td style="text-align:right">{{ rupiah3(item.quantity) }}</td>
                                    <template v-if="transfer.is_incoming">
                                        <td style="text-align:right;font-weight:600">{{ rupiah3(item.actual_quantity ?? item.quantity) }}</td>
                                        <td style="text-align:right">
                                            <span v-if="(item.actual_quantity ?? item.quantity) - item.quantity !== 0"
                                                  :style="{ color: (item.actual_quantity ?? item.quantity) - item.quantity > 0 ? 'var(--green)' : 'var(--red)' }">
                                                {{ (item.actual_quantity ?? item.quantity) - item.quantity > 0 ? '+' : '' }}{{ rupiah3((item.actual_quantity ?? item.quantity) - item.quantity) }}
                                            </span>
                                            <span v-else style="color:var(--text2)">—</span>
                                        </td>
                                    </template>
                                    <td>{{ item.unit }}</td>
                                    <td>
                                        <span v-if="item.product_name" style="font-size:13px;color:var(--blue)">{{ item.product_name }}</span>
                                        <span v-else style="font-size:13px;color:var(--text2)">Tidak terlink</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div>
                <div class="card mb-3">
                    <div class="card-header"><span class="card-title">Ringkasan</span></div>
                    <div class="card-body">
                        <table style="width:100%;border-collapse:collapse">
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:8px 0;font-size:13px;color:var(--text2)">Tipe</td>
                                <td style="padding:8px 0;text-align:right">
                                    <span v-if="transfer.type === 'outgoing'" class="badge badge-blue">Kirim</span>
                                    <span v-else class="badge badge-green">Terima</span>
                                </td>
                            </tr>
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:8px 0;font-size:13px;color:var(--text2)">Status Lokal</td>
                                <td style="padding:8px 0;text-align:right">
                                    <span v-if="transfer.local_status === 'received'" class="badge badge-green">Diterima</span>
                                    <span v-else-if="transfer.local_status === 'sent'" class="badge badge-blue">Dikirim</span>
                                    <span v-else class="badge badge-gray">Draft</span>
                                </td>
                            </tr>
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:8px 0;font-size:13px;color:var(--text2)">Sync ERP HPY</td>
                                <td style="padding:8px 0;text-align:right">
                                    <span v-if="transfer.erp_sync_status === 'synced'" class="badge badge-green">Synced</span>
                                    <span v-else-if="transfer.erp_sync_status === 'failed'" class="badge badge-red">Failed</span>
                                    <span v-else class="badge badge-yellow">Pending</span>
                                </td>
                            </tr>
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:8px 0;font-size:13px;color:var(--text2)">Dari Gudang</td>
                                <td style="padding:8px 0;text-align:right;font-size:13px;max-width:160px;word-break:break-word">{{ transfer.from_warehouse }}</td>
                            </tr>
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:8px 0;font-size:13px;color:var(--text2)">Ke Gudang</td>
                                <td style="padding:8px 0;text-align:right;font-size:13px;max-width:160px;word-break:break-word">{{ transfer.to_warehouse }}</td>
                            </tr>
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:8px 0;font-size:13px;color:var(--text2)">Total Item</td>
                                <td style="padding:8px 0;text-align:right;font-weight:600">{{ transfer.items.length }} jenis</td>
                            </tr>
                            <tr v-if="transfer.submitted_at" style="border-bottom:1px solid var(--border)">
                                <td style="padding:8px 0;font-size:13px;color:var(--text2)">Submitted</td>
                                <td style="padding:8px 0;text-align:right;font-size:13px">{{ transfer.submitted_at }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div v-if="transfer.erp_stock_entry || transfer.erp_source_entry || transfer.erp_sync_error" class="card mb-3">
                    <div class="card-header"><span class="card-title">ERP HPY</span></div>
                    <div class="card-body">
                        <div v-if="transfer.erp_stock_entry" style="margin-bottom:8px">
                            <div style="font-size:12px;color:var(--text2);margin-bottom:2px">Stock Entry</div>
                            <code style="font-size:13px;color:var(--blue)">{{ transfer.erp_stock_entry }}</code>
                        </div>
                        <div v-if="transfer.erp_source_entry" style="margin-bottom:8px">
                            <div style="font-size:12px;color:var(--text2);margin-bottom:2px">Source Entry</div>
                            <code style="font-size:13px;color:var(--text)">{{ transfer.erp_source_entry }}</code>
                        </div>
                        <div v-if="transfer.erp_sync_error" style="background:var(--bg);border-radius:6px;padding:10px;margin-top:8px;border:1px solid #FECACA">
                            <div style="font-size:12px;color:var(--red);font-weight:600;margin-bottom:4px">Error</div>
                            <div style="font-size:12px;color:var(--text);word-break:break-all;max-height:120px;overflow:auto">{{ transfer.erp_sync_error }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="transfer.notes" class="card">
                    <div class="card-header"><span class="card-title">Keterangan</span></div>
                    <div class="card-body"><p style="font-size:14px;color:var(--text2);margin:0">{{ transfer.notes }}</p></div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
