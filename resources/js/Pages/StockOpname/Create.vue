<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    warehouses: Array,
    indexUrl: String,
    storeUrl: String,
});

function today() {
    return new Date().toISOString().slice(0, 10);
}

const form = useForm({
    warehouse_id: '',
    opname_date: today(),
    notes: '',
});

function submit() {
    form.post(props.storeUrl);
}
</script>

<template>
    <Head title="Buat Stock Opname" />
    <AppLayout>
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-clipboard-list" style="color:var(--blue);margin-right:8px;font-size:22px;vertical-align:-2px"></i>
                    Buat Stock Opname
                </h1>
                <p class="page-subtitle">Snapshot stok sistem akan diambil saat ini</p>
            </div>
        </div>

        <div class="card" style="max-width:560px">
            <div class="card-header" style="padding:20px 24px">
                <h3 style="margin:0;font-size:15px;font-weight:600">Informasi Opname</h3>
            </div>
            <form style="padding:24px;display:flex;flex-direction:column;gap:20px" @submit.prevent="submit">
                <div>
                    <label class="form-label">Gudang <span style="color:var(--red)">*</span></label>
                    <select v-model="form.warehouse_id" class="form-control form-select" required>
                        <option value="">-- Pilih Gudang --</option>
                        <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                            {{ wh.label }}<template v-if="wh.is_default"> (default)</template>
                        </option>
                    </select>
                    <div v-if="form.errors.warehouse_id" style="color:var(--red);font-size:13px;margin-top:4px">{{ form.errors.warehouse_id }}</div>
                </div>
                <div>
                    <label class="form-label">Tanggal Opname <span style="color:var(--red)">*</span></label>
                    <input v-model="form.opname_date" type="date" class="form-control" required>
                    <div v-if="form.errors.opname_date" style="color:var(--red);font-size:13px;margin-top:4px">{{ form.errors.opname_date }}</div>
                </div>
                <div>
                    <label class="form-label">Catatan</label>
                    <textarea v-model="form.notes" class="form-control" rows="3" placeholder="Opsional..."></textarea>
                </div>
                <div style="display:flex;gap:12px">
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                        <i class="fas fa-play"></i> Mulai Opname
                    </button>
                    <Link :href="indexUrl" class="btn btn-ghost">Batal</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
