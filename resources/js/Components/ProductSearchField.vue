<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    products: { type: Array, required: true },
    invalid: { type: Boolean, default: false },
});

// Two-way bound from the parent row object — keeps the searched-for text
// and the resolved product id in sync without a separate hidden input,
// unlike the old vanilla-JS version (visible <input> + hidden <input>).
const productId = defineModel('productId');
const productName = defineModel('productName', { default: '' });

const query = ref(productName.value);
const open = ref(false);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    const list = q
        ? props.products.filter((p) => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q)))
        : props.products;
    return list.slice(0, 30);
});

function onInput() {
    productId.value = null;
    open.value = true;
}

function select(product) {
    productId.value = product.id;
    productName.value = product.name;
    query.value = product.name;
    open.value = false;
}

function onBlur() {
    setTimeout(() => { open.value = false; }, 150);
}
</script>

<template>
    <div class="product-search-wrap">
        <input
            v-model="query"
            type="text"
            class="form-control product-search"
            :class="{ invalid }"
            style="font-size:13px"
            placeholder="Ketik nama / SKU..."
            autocomplete="off"
            @input="onInput"
            @focus="open = true"
            @blur="onBlur"
        >
        <div class="product-dropdown" :class="{ open }">
            <div
                v-for="p in filtered" :key="p.id"
                class="product-dropdown-item"
                @mousedown.prevent="select(p)"
            >
                <div class="pname">{{ p.name }}</div>
                <div class="pmeta">{{ p.sku ? 'SKU: ' + p.sku + ' · ' : '' }}{{ p.unit || 'Nos' }}</div>
            </div>
            <div v-if="!filtered.length" class="product-dropdown-item" style="color:var(--text3);text-align:center">
                Produk tidak ditemukan
            </div>
        </div>
    </div>
</template>

<style scoped>
.product-search-wrap { position: relative; }
.product-dropdown {
    display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 20;
    background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
    max-height: 260px; overflow-y: auto; box-shadow: 0 8px 24px rgba(0,0,0,.12);
}
.product-dropdown.open { display: block; }
.product-dropdown-item { padding: 8px 12px; cursor: pointer; font-size: 13px; }
.product-dropdown-item:hover { background: var(--surface2); }
.product-dropdown-item .pname { font-weight: 500; color: var(--text); }
.product-dropdown-item .pmeta { font-size: 11px; color: var(--text3); margin-top: 1px; }
.product-search.invalid { border-color: var(--red); }
</style>
