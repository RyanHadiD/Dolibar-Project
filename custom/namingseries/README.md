# Naming Series

Modul Dolibarr 24 untuk penomoran dokumen fleksibel dengan counter transaksional per periode.

Versi 0.2 mendukung:

- customer proposal, sales order, dan customer invoice;
- supplier proposal, supplier order, dan supplier invoice;
- shipment dan reception;
- customer payment dan supplier payment;
- contract, intervention, project, dan project task;
- expense report, manufacturing order, bill of materials, dan stock transfer;
- bookkeeping entry, asset, cheque receipt, delivery, dan leave request;
- HR evaluation, product lot, product serial number, TakePOS, ticket, dan workstation;

- pola seperti `SO-{YYYY}-{MM}-{####}`;
- reset `monthly`, `yearly`, atau `never`;
- nomor awal dan current counter yang dapat diatur;
- counter terpisah per entity dan periode;
- audit alokasi nomor serta perubahan counter;
- halaman Current Counter untuk melihat current value, preview nomor berikutnya, dan melakukan koreksi manual;
- row locking saat alokasi untuk mencegah nomor ganda.
- pilihan series pada form pembuatan dokumen ketika satu document type memiliki beberapa series aktif;
- pilihan disimpan per dokumen, sehingga tetap digunakan saat draft divalidasi dan nomor final dialokasikan;
- tombol **Rename Document** pada kartu dokumen untuk mengganti satu nomor dokumen beserta referensi teks, jurnal, audit, folder, dan nama file terkait tanpa mengubah ID internal; rename tunggal memakai transaksi dan rollback tanpa membuat salinan backup terpisah;
- halaman **Urutkan Nomor Dokumen** untuk preview dan renumber mandiri seluruh document type berdasarkan scope series, lengkap dengan backup database/folder, audit, deteksi konflik, dan pemeliharaan relasi berbasis ID.

## Aktivasi

1. Aktifkan modul **Naming Series**. Setelah upgrade dari versi lama, nonaktifkan lalu aktifkan kembali modul satu kali agar hook, trigger, dan tabel pilihan series terpasang.
2. Buka menu Naming Series dan periksa seri default.
3. Di konfigurasi masing-masing document type, pilih numbering model **NamingSeries**.

Modul tidak mengubah nomor dokumen lama dan tidak otomatis mengganti numbering model aktif.
