# Migrasi Inertia + Vue + motion-v

Checklist konversi halaman dari Blade ke Inertia.js + Vue 3 + motion-v, dari yang paling sederhana ke paling kompleks. Setiap baris dicentang setelah controller-nya `Inertia::render()`, halaman Vue-nya ada di `resources/js/Pages/`, dan route-nya ditandai `inertia` di `HandleInertiaRequests::INERTIA_ROUTES`.

## Selesai

- [x] Dashboard
- [x] Transaksi — index
- [x] Transaksi — detail
- [x] Produk — index
- [x] Produk — form (create/edit)
- [x] Customer — index
- [x] Stok Barang
- [x] Stock Opname — index, create, detail

## Manajemen

- [ ] Repack (Konversi)
- [ ] Transfer Barang — index
- [ ] Transfer Barang — detail

## Delivery & Dapur

- [ ] Delivery Order — index
- [ ] Delivery Order — detail
- [ ] Delivery Notes
- [ ] Permintaan FG (Stock Request) — index
- [ ] Permintaan FG — detail
- [ ] Pulling Order
- [ ] Rekap Order
- [ ] Kitchen Monitor

## Integrasi

- [ ] Sync HPY
- [ ] Laporan Online
- [ ] Laporan Pembayaran
- [ ] Laporan DO

## Sistem

- [ ] Kupon
- [ ] Manajemen User
- [ ] Manajemen Role
- [ ] Hak Akses (permission matrix)
- [ ] Warehouse
- [ ] Pengaturan Toko
- [ ] Backup / Restore
- [ ] Update Sistem
- [ ] Factory Reset

## Paling akhir (paling kompleks)

- [ ] Kasir (POS) — kasir, quick, express, checkout, cetak struk/kitchen

---

**Catatan:**
- Skill `ui-ux-pro-max` sudah terinstal permanen di `~/.claude/skills/ui-ux-pro-max/` (bukan cuma clone scratchpad lagi).
- Halaman yang belum dikonversi tetap pakai `<a href>` biasa di nav/link (bukan `<Link>` Inertia) — lihat komentar di `HandleInertiaRequests.php` dan `AppLayout.vue`.
