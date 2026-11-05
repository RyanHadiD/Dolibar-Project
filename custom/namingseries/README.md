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
- halaman **Urutkan Nomor Dokumen** untuk preview dan renumber mandiri seluruh document type berdasarkan scope series, lengkap dengan backup database/folder, audit, deteksi konflik, dan pemeliharaan relasi berbasis ID.

## Aktivasi

1. Aktifkan modul **Naming Series**.
2. Buka menu Naming Series dan periksa seri default.
3. Di konfigurasi masing-masing document type, pilih numbering model **NamingSeries**.

Modul tidak mengubah nomor dokumen lama dan tidak otomatis mengganti numbering model aktif.
