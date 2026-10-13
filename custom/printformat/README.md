# Print Format

Versi 0.5 menambahkan export Excel langsung pada tabel data di seluruh halaman
private Dolibarr. Tombol mengikuti tabel, filter, dan kolom yang sedang terlihat;
laporan yang sudah mempunyai tombol XLSX native tidak diberi tombol duplikat.

Modul juga mengaktifkan unit produk dengan `piece (p)` sebagai fallback aman.
Dokumen produk memakai unit pada baris dokumen, lalu unit default produk, dan
terakhir fallback `p` untuk data lama yang belum menyimpan unit.

Custom Dolibarr 24 module for rendering native Dolibarr objects from managed HTML and CSS templates.

Version 0.4 supports 33 native object doctypes through one shared registry and generator: sales and purchasing documents, logistics, stock, projects, manufacturing, CRM, HR, recruitment, members, tickets, assets, users and master data. Reports, accounting exports and label sheets are not doctypes attached to a CommonObject record, so they continue to use their native report generators.

Enable Print Format from Dolibarr module setup. On a supported native document, select Print Format (HTML/CSS), choose a format, and generate the document.

Ukuran halaman dapat dipilih dari A4, Letter, atau Custom. Untuk Custom, masukkan lebar dan tinggi dalam milimeter (20–2000 mm); orientasi Portrait/Landscape dan validasi margin tetap diterapkan oleh generator PDF.

Sebelum dikirim ke TCPDF, selector CSS yang umum (elemen, class, ID, descendant/child, atribut sederhana, serta `nth-child`, `first-child`, dan `last-child`) disalin menjadi inline style. Ini mempertahankan border, padding, alignment, warna, dan tipografi tabel yang sebelumnya hanya terlihat di preview browser. TCPDF tetap hanya mendukung subset HTML/CSS; fitur layout browser seperti flex, grid, positioning kompleks, dan selector pseudo tingkat lanjut dapat dirender berbeda di PDF.
