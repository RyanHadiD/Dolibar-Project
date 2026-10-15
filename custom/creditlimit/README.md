# Credit Limit

Kontrol exposure dan umur piutang customer untuk Dolibarr 24. Modul memakai `societe.outstanding_limit` sebagai limit khusus customer, menambahkan default entitas dan kebijakan customer, lalu memeriksa Sales Order, Shipment, dan Customer Invoice saat validasi.

Exposure adalah invoice outstanding ditambah sisa nilai Sales Order tervalidasi yang belum ditagihkan. Invoice yang berasal dari order melepaskan reserve order agar tidak dihitung ganda. Credit note customer tidak diblokir karena menurunkan risiko.

Dokumen yang melanggar kebijakan mode `block` memerlukan approval sekali pakai dari user dengan permission `approve`. Approval terikat pada tipe, ID, customer, dan nominal dokumen serta memiliki tanggal kedaluwarsa. Semua block, warning, dan override yang terpakai dicatat dalam audit.

## Pengaturan user bypass

Admin membuka **Credit Control > User Bypass**, lalu mencentang user yang boleh memberi approval. Admin/superadmin selalu memiliki akses. Hak yang dipakai adalah permission native Dolibarr **Approve credit-limit overrides**, sehingga assignment dari halaman ini tetap sinkron dengan halaman permission user dan grup.

Ketika dokumen diblokir, approver membuka dokumen lalu memilih **Approve Credit Override**, mengisi alasan, dan menyimpan approval. Approval tidak mematikan policy customer: approval hanya berlaku sekali untuk tipe, ID, customer, dan nominal dokumen tersebut hingga tanggal kedaluwarsa. Setelah itu sales dapat memvalidasi dokumen seperti biasa.
