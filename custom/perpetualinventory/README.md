# Perpetual Inventory untuk Dolibarr 24

Versi 1.6 menambahkan dokumen **Stock Entry multi-item** bernomor dengan status
Draft dan Posted. Draft dapat disimpan, dibuka kembali, diedit, dan dihapus;
tombol Validate & Post memposting seluruh item secara atomik ke stok native,
buku stok, dan jurnal. Satu dokumen dapat memuat sampai 100 baris untuk Material
Receipt, Material Issue, Material Transfer, Stock Reconciliation/opname,
penyesuaian, retur, atau pembalik. Setiap baris menyimpan tautan ke Stock Ledger
yang dihasilkan dan dapat memakai Difference Account tersendiri.

Posting Date dan Posting Time dipilih pada Draft lalu dipertahankan saat
validasi. Tanggal masa depan, periode terkunci, dan tanggal yang lebih awal dari
ledger terakhir barang/gudang tetap ditolak. Administrator dapat mengatur seri
nomor melalui **Perpetual Inventory → Pengaturan → Format nomor Stock Entry**.
Format bawaan `STE-{YYYY}-{ID:7}`; token yang tersedia adalah `{YYYY}`, `{YY}`,
`{MM}`, `{DD}`, dan `{ID:n}` (panjang 1–12 digit). Nomor memakai ID database,
sehingga tetap unik tetapi dapat memiliki lompatan bila Draft dihapus.

Stock Entry sekarang mempunyai menu utama sendiri, terpisah dari menu
Perpetual Inventory, dengan submenu **Daftar Stock Entry** dan **Stock Entry
Baru**. Material Receipt, Material Issue, Stock Reconciliation, dan Stock
Adjustment memakai Difference Account pada masing-masing item sebagai akun
lawan Persediaan. Material Transfer tidak membuat jurnal GL karena nilai hanya
berpindah antargudang. Voucher buku stok dan buku besar menggunakan nomor
`STE-YYYY-xxxxxxx` agar dokumen, item, stock ledger, dan jurnal dapat ditelusuri.

Versi 1.4 menambahkan revisi terkontrol untuk Accounting Journal manual yang
sudah divalidasi. Journal hanya dapat dibuka kembali jika belum diekspor dan
bukan hasil posting otomatis dokumen sumber. Alasan wajib dan snapshot lengkap
sebelum perubahan disimpan dalam Riwayat Cancel/Revisi. Revisi Payment tetap
memakai alur aman batalkan lalu buat ulang karena Payment native Dolibarr tidak
memiliki status Draft yang dapat diedit.

Modul versi 1.6.0 untuk perpetual inventory dengan Moving Average per barang/gudang. Prinsipnya mengikuti [perpetual inventory ERPNext](https://docs.frappe.io/erpnext/perpetual-inventory): nilai stok dan jurnal persediaan berubah bersama. Modul ini bukan salinan penuh ERPNext.

## Get Items From

Shipment dan Reception dapat dibuat dengan alur ERP-style dari menu Products/Services:

- Tombol native **New shipment** dan **New reception** langsung membuka pemilih order sumber, sehingga pengguna tidak perlu masuk ke kartu order terlebih dahulu.
- **Shipment → Get Items From Sales Order** menampilkan Sales Order tervalidasi yang masih memiliki qty belum dikirim.
- **Reception → Get Items From Purchase Order** menampilkan Purchase Order yang sudah dikirim ke supplier dan masih memiliki qty belum diterima.
- Dari Reception berstatus Draft, **Get Items From Purchase Order** menambahkan item ke draft yang sedang dibuka dan tidak membuat Reception baru. Supplier harus sama, lalu qty, warehouse, lot/batch, dan tanggal kedaluwarsa dapat diperiksa sebelum item diambil.
- Dari Customer Invoice berstatus Draft, **Get Items From Shipment** menambahkan qty Shipment yang belum ditagihkan dari customer yang sama.
- Dari Supplier Invoice berstatus Draft, **Get Items From Reception** menambahkan qty Reception yang belum ditagihkan dari supplier yang sama.
- Relasi dibuat sampai tingkat baris sehingga impor berikutnya hanya menampilkan qty yang masih outstanding dan mencegah penagihan ganda.

Setelah order dipilih dari menu New, sistem menampilkan baris outstanding dan meminta qty/warehouse sebelum meneruskan data lengkap ke form Shipment/Reception native Dolibarr. Pemilihan qty parsial, warehouse, batch/serial, serta hubungan dokumen tetap diproses oleh core Dolibarr. Dokumen draft dan tervalidasi yang belum dibatalkan ikut diperhitungkan sebagai qty yang sudah diproses agar tidak terjadi pengambilan ganda.

## Cakupan yang tersedia

| Transaksi | Buku stok | Buku besar Dolibarr |
| --- | --- | --- |
| Validasi Reception native | Tambah jumlah dan nilai sesuai biaya penerimaan | Debit Persediaan; Kredit Barang Diterima Belum Ditagih; voucher memakai nomor Reception |
| Validasi Shipment native | Kurangi jumlah dengan Moving Average | Debit HPP produk (fallback HPP global); Kredit Persediaan; voucher memakai nomor Shipment |
| Transfer melalui modul | Biaya keluar dibawa ke gudang tujuan | Tidak ada perubahan GL; semua gudang memakai satu akun persediaan |
| Stock opname / penyesuaian | Sesuaikan jumlah fisik / selisih | Persediaan berpasangan dengan Selisih Stok |
| Retur fisik | Mengacu biaya transaksi asal | Membalik akun transaksi asal secara proporsional |
| Pembalik | Seluruh transaksi terakhir untuk barang/gudang tersebut | Jurnal pembalik baru; riwayat dipertahankan |
| Saldo awal | Adopsi jumlah yang sudah ada di Dolibarr | Pilihan adopsi GL yang sudah ada atau jurnal saldo awal baru |
| Vendor Invoice | Tidak mengubah jumlah stok lagi | Debit Barang Diterima Belum Ditagih (+ pajak); Kredit Hutang melalui jurnal pembelian standar Dolibarr |
| Customer Invoice | Tidak mengubah jumlah stok lagi | Debit Piutang; Kredit Penjualan (+ pajak) melalui jurnal penjualan standar Dolibarr |

Jurnal stok langsung dibuat di `accounting_bookkeeping`, lalu divalidasi setelah kedua sisi seimbang. Di General Ledger, jurnal stok baru menggunakan tipe voucher `reception` atau `shipment` dan nomor dokumen Dolibarr sebagai Voucher No; `PI-ID` dipertahankan dalam keterangan sebagai jejak audit. Posting invoice supplier/customer, pajak, piutang, pembayaran, dan utang tetap mengikuti proses akuntansi native Dolibarr. Tagihan tidak otomatis diposting oleh modul ini agar jurnal tidak ganda.

## Laporan keuangan custom

Menu utama **Laporan Keuangan** dipisahkan dari **Perpetual Inventory** dan dilindungi hak akses khusus **Read confidential financial reports**. Hanya pengguna yang diberi hak tersebut yang dapat melihat atau membuka **General Ledger**, **Profit & Loss**, **Balance Sheet**, **Gross Profit**, dan **Hutang & Piutang**; hak baca stok biasa tidak memberikan akses ke laporan keuangan. Hak ini dapat diberikan atau dicabut per pengguna/grup melalui Pengaturan → Pengguna & Grup → Hak Akses. File dan laporan bawaan Dolibarr tidak diubah. General Ledger menampilkan opening balance, setiap posting debit/credit, running balance, voucher, akun pasangan, journal, party, dan remarks opsional. Profit & Loss dan Balance Sheet membaca jurnal double-entry serta hierarki `account_parent` dari bagan akun aktif. Gross Profit menghubungkan Customer Invoice tervalidasi dengan Shipment dan memakai nilai moving-average aktual sebagai Buying Amount/HPP. Laporan Hutang & Piutang menampilkan saldo awal, penambahan, pengurangan, dan saldo akhir per akun pembantu customer/supplier dari buku besar. Semua laporan diekspor sebagai workbook Excel `.xlsx` native. Tampilan default ringkas; tampilan bulanan P&L/Balance Sheet dapat dipilih dan dibatasi maksimal 24 bulan.

## Daily Report

Menu utama **Daily Report** memakai hak akses laporan keuangan yang sama dan berisi **Accounts Receivable Summary**, **Accounts Payable Summary**, **Sales Register**, dan **Purchase Register**. Summary menampilkan invoiced, paid, credit/debit note, outstanding, serta ageing berdasarkan due date dalam bucket Not Due, 0–30, 31–60, 61–90, dan di atas 90 hari. Register menampilkan invoice tervalidasi per periode, nilai net/pajak/grand total, pembayaran dan alokasi credit/debit note sampai akhir periode, serta outstanding. Semua tampilan dapat difilter per customer/supplier dan diekspor ke `.xlsx`.

## Stock Ledger dan Stock Balance

- **Stock Ledger** dan **Stock Balance** berada di menu utama tersendiri bernama **Laporan Stok**, terpisah dari proses dan konfigurasi Perpetual Inventory.
- **Stock Ledger** menampilkan transaksi rinci menurut periode, barang, gudang, dan jenis transaksi: voucher sumber, incoming/outgoing quantity, incoming rate, perubahan nilai, saldo berjalan, valuation rate, saldo nilai, dan status jurnal. Voucher Reception/Shipment dapat dibuka langsung dan hasil diekspor sebagai workbook Excel `.xlsx` native.
- **Stock Balance** menampilkan ringkasan per barang/gudang: opening quantity/value, in quantity/value, out quantity/value, balance quantity, valuation rate, balance value, dan waktu pergerakan terakhir. Klik barang untuk drill-down ke Stock Ledger dengan filter yang sama.
- Opening memakai seluruh ledger sebelum tanggal awal; closing memakai seluruh ledger sampai akhir tanggal akhir. Karena buku stok append-only dan transaksi mundur ditolak, laporan historis tetap konsisten dengan saldo yang tersimpan.

## Peta dokumen ERPNext dan Dolibarr

| Konsep ERPNext | Dokumen Dolibarr | Jurnal utama |
| --- | --- | --- |
| Purchase Receipt / BBM | Reception | Dr Persediaan; Cr Barang Diterima Belum Ditagih (GRNI) |
| Purchase Invoice / PJET | Vendor Invoice | Dr GRNI dan pajak masukan; Cr Hutang Usaha |
| Delivery Note / BBK | Shipment | Dr HPP; Cr Persediaan |
| Sales Invoice / SJ | Customer Invoice | Dr Piutang Usaha; Cr Penjualan dan pajak keluaran |

Akun Hutang, Piutang, Penjualan, dan pajak tetap diatur pada konfigurasi akuntansi native Dolibarr. Modul ini hanya meminta akun yang diperlukan untuk valuasi stok, GRNI, HPP, selisih stok, dan saldo awal.

## Penggunaan pertama

1. Aktifkan modul **Perpetual Inventory** di Pengaturan → Modul/Aplikasi jika belum aktif. Dependensi: Stock, Accounting double-entry, Reception, Expedition. Aktivasi modul saja belum mengaktifkan posting stok.
2. Administrator mendapat hak modul melalui aktivasi native. Berikan hak baca, operasi stok, atau pencocokan kepada pengguna internal sesuai tugasnya. Berikan hak **Read confidential financial reports** hanya kepada pengguna yang boleh melihat laporan keuangan.
3. Pilih bagan akun di Akuntansi Dolibarr, siapkan jurnal yang aktif, penomoran jurnal, dan periode akuntansi yang terbuka.
4. Pada pengaturan stok Dolibarr, pilih perubahan saat **validasi penerimaan** dan **validasi pengiriman**. Nonaktifkan pemicu stok dari invoice, pesanan, penutupan pengiriman/penerimaan, dan supplier dispatch. Opsi kit/subproduk boleh aktif, tetapi transaksi produk yang benar-benar menjadi induk/komponen kit ditolak karena valuasi kit belum didukung.
5. Buka Perpetual Inventory → Pengaturan. Pilih lima akun berbeda dari bagan akun aktif: Persediaan, Barang Diterima Belum Ditagih, HPP fallback, Selisih Stok, Penyesuaian Saldo Awal. Pilih jurnal persediaan. Untuk HPP per produk, isi **Accounting code (purchase)** pada kartu produk; jika kosong, shipment memakai HPP fallback. Modul tidak menebak nomor akun perusahaan.
6. Untuk setiap barang/gudang dengan stok awal positif, masukkan biaya per unit. Jumlah diambil dari stok Dolibarr, sehingga tidak digandakan. Pilih **tanpa jurnal baru** jika nilai sudah ada di GL; pilih **buat jurnal saldo awal** hanya jika belum tercatat. Saldo awal yang disimpan tidak bisa diedit dari layar ini.
7. Selesaikan penerimaan/tagihan lama sebelum migrasi. Versi ini tidak mengimpor rincian GRNI historis. Akun GRNI harus cocok dengan modul sebelum mulai; saldo lama yang belum diselesaikan menghalangi aktivasi posting.
8. Periksa Rekonsiliasi: kuantitas native harus sama dengan buku stok, nilai buku stok harus sama dengan GL persediaan, dan akun GRNI harus cocok. Stok negatif harus diselesaikan sebelum mulai.
9. Klik **Mulai perpetual inventory**. Akun dan jurnal dikunci setelah terdapat buku stok. Saat aktif, penonaktifan modul diblokir untuk mencegah terputusnya pencatatan; migrasi keluar perlu prosedur tersendiri.

Tombol **Mulai perpetual inventory** hanya mengaktifkan trigger setelah seluruh pemeriksaan cocok. Tombol itu tidak membuat jurnal, tidak menambah HPP, Hutang, atau Persediaan. Jika HPP/Hutang berubah sebelum modul aktif, periksa Vendor Invoice pada halaman Rekonsiliasi: baris barang yang belum dicocokkan masih mengikuti akun pembelian produk native Dolibarr.

## Alur pembelian

1. Buat pesanan dan penerimaan lewat Dolibarr. Isi biaya penerimaan yang benar; nilai nol berarti barang gratis, bukan harga yang belum ditentukan.
2. Validasi penerimaan. Buka Buku Stok untuk melihat entri dan jurnalnya.
3. Buat Vendor Invoice dari pesanan/penerimaan seperti biasa, lalu validasi. Sistem otomatis mencocokkan setiap baris barang ke Reception yang sesuai berdasarkan hubungan dokumen, supplier, barang, harga, tanggal dokumen Reception, dan sisa kuantitas. Waktu teknis validasi/posting ledger tidak dipakai sebagai tanggal bisnis pencocokan. Reception yang terhubung diprioritaskan, kemudian FIFO.
4. Jika tidak ada kecocokan penuh (misalnya harga berubah, invoice parsial yang tidak jelas, atau dokumen lama tidak terhubung), validasi dihentikan tanpa menyimpan pencocokan parsial. Gunakan menu Pencocokan Tagihan sebagai fallback manual.
5. Saat cocok penuh, sistem mengikat akun baris invoice ke GRNI secara otomatis.
6. Transfer tagihan melalui Akuntansi → Jurnal Pembelian. Pajak dan utang mengikuti aturan Dolibarr; GRNI diselesaikan melalui debit baris barang. Rekonsiliasi GRNI menampilkan perbedaan selama tagihan tervalidasi belum dijurnal.

## Operasi dan koreksi

### Cancel & Amend lintas dokumen

Workflow audit berlaku pada seluruh dokumen transaksi utama: Sales Order, Shipment, Customer Invoice, Customer Payment, Purchase Order, Reception, Vendor Invoice, Supplier Payment, dan Accounting Journal manual. Setiap aksi membutuhkan alasan minimal lima karakter dan menyimpan snapshot dokumen pada `pi_document_action`. Dokumen harus diproses dari hilir ke hulu: payment, invoice, shipment/reception, lalu order. Dokumen turunan aktif, pembayaran yang belum dibatalkan, periode terkunci, bank line yang sudah direkonsiliasi, journal otomatis, dan journal yang sudah diekspor tetap memblokir perubahan.

Order, Shipment, Reception, dan Invoice menggunakan **Cancel & Amend** untuk kembali ke Draft. Customer Order, Customer Invoice, Purchase Order, dan Vendor Invoice memakai satu halaman konfirmasi yang seragam; snapshot sebelum revisi disimpan dan setiap siklus diberi urutan R1, R2, dan seterusnya pada riwayat. Payment menggunakan **Cancel & Recreate** karena payment Dolibarr tidak memiliki model draft-edit yang setara; alokasi lama dibatalkan secara native, invoice dibuka kembali, audit disimpan, lalu pengguna diarahkan ke formulir payment pengganti. Payment yang sudah direkonsiliasi bank tidak diubah otomatis.

Stock Entry memakai alur **Draft → Posted → Cancelled → Delete**. Cancel membuat gerakan stok lawan, menghapus efek stock ledger dan jurnal GL asal, menghitung ulang moving average transaksi setelahnya, lalu menyimpan alasan dan snapshot pada audit trail. Stock Entry Posted tidak dapat langsung dihapus; dokumen wajib dibatalkan dahulu.

- **Opname:** masukkan jumlah fisik akhir, termasuk nol. Jika lebih tinggi dari stok sistem, isi modal per unit untuk selisih masuk. Jumlah yang sama tidak memerlukan jurnal.
- **Saldo awal dari nol:** sebelum ada transaksi operasional, gunakan Operasi Stok → Saldo awal. Operasi ini menambah stok dan menjurnal Debit Persediaan / Kredit Penyesuaian Saldo Awal. Setelah Reception, Shipment, penyesuaian, transfer, atau retur pertama, input saldo awal ditutup.
- **Penyesuaian:** jumlah positif untuk tambah stok, negatif untuk kurangi. Stok keluar memakai Moving Average.
- **Transfer:** pilih barang, gudang asal/tujuan, dan jumlah positif. Kedua sisi disimpan dalam satu transaksi.
- **Retur:** pilih ID buku stok penerimaan/pengiriman asal dan jumlah positif. Retur supplier dibatasi pada kuantitas penerimaan yang belum dicocokkan ke tagihan. Retur pelanggan hanya memulihkan stok/HPP; credit note penjualan tetap dibuat lewat Dolibarr.
- **Cancel/Delete/Modify Reception atau Shipment:** Dolibarr atau modul membuat gerakan stok lawan, lalu modul menghapus entri buku stok dan jurnal PI asal secara atomik. Aksi Modify mengembalikan dokumen ke draft agar barisnya dapat direvisi. Alasan cancel/revisi wajib diisi dan disimpan pada `pi_document_action`. Jika ada transaksi stok yang lebih baru, moving-average dan jurnal transaksi berikutnya dihitung ulang dalam transaksi database yang sama. Reception yang sudah dicocokkan ke Vendor Invoice atau dokumen yang memiliki retur/pembalik turunan tetap harus menyelesaikan dokumen turunannya lebih dahulu.
- **Cancel/Unvalidate Invoice:** jurnal buku besar invoice dihapus otomatis. Untuk invoice yang sudah masuk bookkeeping dan tombol Modify/Delete native terkunci, gunakan **Revisi + Ledger** untuk kembali ke draft atau **Hapus + Ledger** untuk menghapusnya. Invoice yang memiliki pembayaran harus membatalkan/menghapus pembayarannya lebih dahulu.
- **Pembalik manual:** hanya untuk penerimaan, pengiriman, atau penyesuaian terakhir pada barang/gudang; belum pernah diretur/dicocokkan. Membuat jurnal pembalik baru dan mempertahankan dokumen asal sebagai jejak audit. Transfer dan saldo awal belum menyediakan pembalik otomatis.
- Semua operasi manual menggunakan waktu posting saat ini. Tanggal mundur, tanggal masa depan, periode akuntansi tertutup, dan tanggal kunci ditolak. Draft ulang Shipment yang sudah membentuk ledger otomatis membuat gerakan stok lawan terlebih dahulu, sehingga dokumen dapat diedit dan divalidasi kembali tanpa menggandakan stok/HPP.

## Batas versi ini

- Satu akun persediaan per entitas; pemetaan akun per gudang belum tersedia.
- Moving Average saja. FIFO, landed cost, selisih harga invoice, manufaktur, dan kit belum tersedia. Repost valuasi historis tersedia khusus untuk backfill melalui modul Saldo Awal & Migrasi; proses ini mereplay ledger dan memperbarui jurnal stok yang nilainya berubah.
- Invoice supplier standar dengan mata uang dasar dan harga bersih sesuai penerimaan. Credit note supplier dan pembatalan invoice yang sudah dicocokkan belum tersedia.
- Lot/serial dapat berjalan melalui penerimaan/pengiriman native; operasi manual modul menolak barang lot/serial karena belum ada pemilihan lot pada formulir.
- Produk dan gudang harus milik entitas aktif; berbagi master lintas entitas belum didukung.
- Nilai moneter dibulatkan mengikuti `MAIN_MAX_DECIMALS_TOT`; kuantitas disimpan sampai delapan desimal. Sisa pembulatan dibawa pada pengeluaran/retur terakhir.
- Rekonsiliasi saat ini memakai akumulasi seluruh GL pada akun terpilih; laporan as-of historis dan perlakuan khusus carry-forward tahun buku belum tersedia. Jangan menggunakan akun yang sama untuk aset di luar modul.
- Posting native bersamaan dapat ditolak jika terdeteksi konflik saldo/deadlock; ulangi transaksi yang gagal. Modul tidak otomatis mengulangi transaksi bisnis.
- Integrasi diuji pada Dolibarr 24 / PHP 8.2 / MariaDB atau MySQL. PostgreSQL belum diverifikasi.

## Struktur dan integritas

- `pi_config`: konfigurasi per entitas dan baris pengunci untuk serialisasi writer.
- `pi_balance`: saldo barang/gudang yang dikunci saat valuasi.
- `pi_entry`: ledger append-only, ID stock movement unik, asal transaksi dan pembalik.
- `pi_match`: alokasi penerimaan ke baris tagihan; kuantitas dan nilai tidak boleh melewati sisa.
- Trigger `STOCK_MOVEMENT` berjalan dalam transaksi stock native. Kesalahan valuasi/GL dikembalikan ke caller agar seluruh transaksi dibatalkan.
- Operasi modul membungkus stok native, ledger, dan jurnal dalam transaksi database. Pengiriman ulang formulir memakai kunci permintaan yang sama dan tidak menggandakan stok.
- Tidak ada perubahan file core Dolibarr. Halaman memerlukan login, hak modul, pembatasan entitas, dan token CSRF pada POST.

## Pengujian

```sh
php custom/perpetualinventory/tests/valuation.php
php custom/perpetualinventory/tests/reportformat.php
php custom/perpetualinventory/tests/integration.php
find custom/perpetualinventory -name '*.php' -print0 | xargs -0 -n1 php -l
```

Integration runner khusus CLI memuat runtime Dolibarr, membuat tabel fixture kosong dengan awalan acak `pitest_*`, dan menjalankan kelas stok, trigger, dan akuntansi asli. Driver fixture mengalihkan seluruh referensi prefix ke tabel pengujian. Tidak ada baris bisnis pelanggan yang disalin atau diubah. Tabel fixture dibersihkan di blok `finally`; dibutuhkan izin CREATE/DROP TABLE. Jalankan pada lingkungan uji untuk pengujian berikutnya.

Skenario meliputi valuasi rata-rata, pengiriman pada biaya modal, transfer idempoten, retur/pembalik, stock opname nol, kegagalan jurnal kedua dan rollback, salah entitas/hak akses, pencocokan parsial, perubahan supplier/produk/harga tagihan, konfigurasi stok tidak kompatibel, tanggal terkunci, dan rekonsiliasi.
