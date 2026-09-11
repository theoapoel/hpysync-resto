<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AnimatePresence, motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';
import { toast } from '@/toast';

const props = defineProps({
    customers: Object,
    filters: Object,
    indexUrl: String,
    storeUrl: String,
});

const search = reactive({ search: props.filters.search ?? '' });
function applySearch() {
    router.get(props.indexUrl, search, { preserveState: true, preserveScroll: true, replace: true });
}

function rupiah(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

// ── Add customer modal ────────────────────────────────────────────
const showAddModal = ref(false);
const form = useForm({ name: '', phone: '', email: '', address: '' });

function submitAdd() {
    form.post(props.storeUrl, {
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
        },
    });
}

// ── Push to ERP HPY ────────────────────────────────────────────────
const pushingId = ref(null);
async function pushCustomer(customer) {
    pushingId.value = customer.id;
    try {
        const resp = await fetch(customer.push_url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        });
        const data = await resp.json();
        toast(data.success ? 'Berhasil push ke HPY: ' + data.docname : 'Gagal: ' + data.error, data.success ? 'success' : 'error');
        if (data.success) router.reload();
    } finally {
        pushingId.value = null;
    }
}
</script>

<template>
    <Head title="Customer" />
    <AppLayout>
        <div class="page-header">
            <div class="page-title"><i class="fas fa-users text-blue"></i> Manajemen Customer</div>
            <button class="btn btn-primary" @click="showAddModal = true"><i class="fas fa-user-plus"></i> Tambah Customer</button>
        </div>

        <div class="card">
            <div class="card-header">
                <form style="display:flex;gap:10px" @submit.prevent="applySearch">
                    <input v-model="search.search" type="text" class="form-control" placeholder="Cari customer..." style="max-width:280px">
                    <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i> Cari</button>
                </form>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Kode</th><th>Nama</th><th>Telepon</th><th>Email</th><th>Total Pembelian</th><th>HPY</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <tr v-if="!customers.data.length">
                            <td colspan="7" style="text-align:center;padding:40px;color:var(--text3)">Belum ada customer</td>
                        </tr>
                        <tr v-for="c in customers.data" :key="c.id">
                            <td class="badge badge-blue">{{ c.code }}</td>
                            <td class="font-medium">{{ c.name }}</td>
                            <td>{{ c.phone ?? '-' }}</td>
                            <td>{{ c.email ?? '-' }}</td>
                            <td class="money text-blue">{{ rupiah(c.total_purchase) }}</td>
                            <td><span class="badge" :class="c.erp_synced ? 'badge-green' : 'badge-gray'">{{ c.erp_synced ? '✓ Synced' : 'Local' }}</span></td>
                            <td>
                                <button v-if="!c.erp_synced" class="btn btn-ghost btn-sm" :disabled="pushingId === c.id" @click="pushCustomer(c)">
                                    <span v-if="pushingId === c.id" class="spinner" style="border-color:rgba(0,0,0,.2);border-top-color:var(--blue)"></span>
                                    <i v-else class="fas fa-upload"></i> Push ERP
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="customers.links.length > 3" class="pagination-wrap">
                <div class="pagination-info">
                    Menampilkan {{ customers.from ?? 0 }}–{{ customers.to ?? 0 }}
                    dari <strong>{{ customers.total }}</strong> customer
                </div>
                <ul class="pagination">
                    <li v-for="(link, i) in customers.links" :key="i" :class="{ active: link.active, disabled: !link.url }">
                        <Link v-if="link.url" :href="link.url" v-html="link.label"></Link>
                        <span v-else v-html="link.label"></span>
                    </li>
                </ul>
            </div>
        </div>

        <AnimatePresence>
            <motion.div
                v-if="showAddModal"
                class="modal-overlay show"
                :initial="{ opacity: 0 }" :animate="{ opacity: 1 }" :exit="{ opacity: 0 }"
                @click.self="showAddModal = false"
            >
                <motion.div class="modal" :initial="{ opacity: 0, y: -20, scale: 0.97 }" :animate="{ opacity: 1, y: 0, scale: 1 }">
                    <div class="modal-header">
                        <div class="modal-title">Tambah Customer</div>
                        <button style="background:none;border:none;cursor:pointer;font-size:20px" @click="showAddModal = false">&times;</button>
                    </div>
                    <form @submit.prevent="submitAdd">
                        <div class="modal-body">
                            <div class="form-group">
                                <label class="form-label">Nama *</label>
                                <input v-model="form.name" type="text" class="form-control" required>
                                <div v-if="form.errors.name" class="text-red text-sm mt-1">{{ form.errors.name }}</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Telepon</label>
                                <input v-model="form.phone" type="text" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input v-model="form.email" type="email" class="form-control">
                                <div v-if="form.errors.email" class="text-red text-sm mt-1">{{ form.errors.email }}</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Alamat</label>
                                <textarea v-model="form.address" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" @click="showAddModal = false">Batal</button>
                            <button type="submit" class="btn btn-primary" :disabled="form.processing"><i class="fas fa-save"></i> Simpan</button>
                        </div>
                    </form>
                </motion.div>
            </motion.div>
        </AnimatePresence>
    </AppLayout>
</template>
