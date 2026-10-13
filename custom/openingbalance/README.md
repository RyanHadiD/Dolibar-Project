# Saldo Awal & Migrasi

Modul draft-first untuk menyiapkan saldo awal persediaan, piutang, hutang, dan akun buku besar pada satu tanggal cutover.

## Status versi 0.3.0

- Membuat batch per entitas dengan status Draft, Validated, dan Posted.
- Form multi-baris untuk persediaan, invoice piutang, invoice hutang, dan saldo akun lainnya.
- Import CSV massal per bagian dengan template siap unduh, pencocokan kode master, dan validasi atomik maksimal 5.000 baris/2 MB.
- Pencarian AJAX untuk produk, gudang, customer, supplier, akun, dan jurnal; daftar besar tidak dimuat sekaligus.
- Preview debit, kredit, dan selisih batch.
- Validasi master data, tanggal, duplikasi, akun kontrol, jurnal, dan keseimbangan.
- Finalisasi membuat invoice customer/supplier native yang tetap dapat dilunasi melalui pembayaran Dolibarr.
- Finalisasi membentuk jurnal tervalidasi dan menyimpan tautan audit setiap dokumen hasil.
- Invoice opening masuk ke aging piutang/hutang, tetapi dikecualikan dari register omzet/pembelian periode.

Finalisasi bersifat permanen dan hanya tersedia bagi pengguna dengan hak **Post opening balance batches**. Modul menolak finalisasi jika periode fiskal tertutup, akun tidak cocok, batch tidak seimbang, atau dokumen hasil sudah pernah dibuat.

Untuk persediaan, finalisasi hanya boleh dilakukan admin. Batch dapat diposting sebelum ataupun sesudah Perpetual Inventory berjalan. Pada sistem yang sudah berjalan, kuantitas saldo awal diperlakukan sebagai saldo historis tambahan pada tanggal cutover; sistem menambahkannya ke stok native lalu mereplay moving average dan jurnal stok berikutnya secara atomik. Batch posted menyediakan tombol **Hitung Ulang Valuasi Stok** yang idempoten dan tidak menggandakan saldo awal. Stok lot/serial belum didukung.

Pengujian integrasi lengkap dapat dijalankan dengan:

```sh
php custom/openingbalance/tests/integration.php
```

Runner membuat tabel fixture kosong dengan awalan acak, menguji Draft sampai Posted serta pelunasan native customer/supplier, lalu menghapus fixture pada blok `finally`; tidak ada data bisnis yang disalin atau diubah.

## Import CSV massal

Pada tab Persediaan, Piutang, Hutang, atau Saldo Akun, buka **Import Massal dari CSV** lalu unduh template untuk bagian tersebut. Setiap template menyertakan baris contoh pengisian yang harus diganti atau dihapus sebelum import. Gunakan kode produk, gudang, customer/supplier, dan akun yang benar-benar terdaftar di sistem. Pemisah koma, titik koma, dan tab didukung. Header wajib dan urutannya boleh diubah; kolom tambahan akan diabaikan. Angka desimal harus memakai titik dan tanggal memakai format `YYYY-MM-DD`.

| Bagian | Header wajib |
| --- | --- |
| Persediaan | `product_ref,warehouse_ref,qty,unit_cost,note` |
| Piutang/Hutang | `party_code,source_ref,invoice_date,due_date,original_amount,prior_paid,outstanding,currency,note` |
| Saldo Akun | `account_number,debit,credit,note` |

Import yang berhasil mengganti seluruh baris pada bagian yang sedang dibuka. Produk, gudang aktif, customer/supplier, dan akun dicocokkan terhadap kode master. Seluruh file divalidasi lebih dahulu; jika satu baris gagal, data lama tetap utuh.

## Prinsip jurnal

| Sumber | Debit | Kredit |
| --- | --- | --- |
| Persediaan | Persediaan | Opening Balance Clearing |
| Piutang | Piutang Usaha | Opening Balance Clearing |
| Hutang | Opening Balance Clearing | Hutang Usaha |
| Saldo akun debit | Akun tujuan | Opening Balance Clearing |
| Saldo akun kredit | Opening Balance Clearing | Akun tujuan |

Ketika seluruh saldo sudah lengkap, Opening Balance Clearing harus nol.
