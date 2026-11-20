# PRD – Website Sistem Pakar Pendeteksi Penyakit Pernapasan dengan Case-Based Reasoning (CBR)

**Versi:** 1.0 (Draft)\
**Tanggal:** 7 November 2026\
**Jenis produk:** Aplikasi web sistem pakar\
**Teknologi:** HTML, CSS, JavaScript, PHP, MySQL\
**Metode:** Case-Based Reasoning – siklus 4R (Retrieve, Reuse, Revise, Retain)

## 1. Product Overview

### 1.1 Latar Belakang Masalah

Penyakit sistem pernapasan seperti ISPA, common cold, influenza, faringitis, bronkitis, asma, pneumonia, TBC paru, dan PPOK termasuk keluhan kesehatan yang paling sering dialami masyarakat. Gejalanya saling tumpang tindih (batuk, demam, sesak napas, nyeri dada, pilek), sehingga masyarakat awam sulit membedakan tingkat keparahannya dan sering menunda pemeriksaan atau melakukan pengobatan sendiri yang kurang tepat.

Masalah yang ingin diselesaikan:

- Akses ke dokter spesialis paru/THT terbatas, terutama di daerah dengan fasilitas kesehatan minim, dan antrean pemeriksaan awal sering panjang.
- Pengetahuan pakar tersebar pada pengalaman individu dan tidak terdokumentasi secara sistematis.
- Sistem pakar berbasis aturan (rule-based) kaku: setiap gejala baru atau variasi kasus menuntut penulisan aturan baru. Pendekatan CBR lebih cocok karena menalar dari **pengalaman kasus nyata** dan dapat belajar dari kasus baru.

CBR dipilih karena meniru cara dokter bernalar: "pasien ini mirip dengan kasus yang pernah ditangani sebelumnya". Pengetahuan bertambah seiring kasus baru divalidasi pakar (tahap Retain), tanpa perlu menulis ulang aturan.

### 1.2 Visi dan Tujuan Utama

**Visi:** Menjadi alat bantu skrining awal gangguan pernapasan yang mudah diakses, transparan, dan terus belajar dari validasi pakar.

**Tujuan:**

1. Menyediakan diagnosis awal gangguan respiratori berdasarkan gejala yang dimasukkan pengguna, lengkap dengan persentase kemiripan dan solusi/saran.
2. Mengimplementasikan siklus 4R CBR secara penuh dan dapat diaudit.
3. Menyediakan panel pakar untuk mengelola penyakit, gejala, bobot, dan basis kasus tanpa menyentuh kode.
4. Memperkaya basis kasus melalui validasi pakar atas kasus baru hasil diagnosa pasien.
5. Mengedukasi pengguna bahwa hasil sistem hanyalah **skrining awal, bukan pengganti diagnosis dokter**.

**Di luar cakupan (out of scope) versi 1.0:** konsultasi video/chat dengan dokter, resep obat otomatis, integrasi rekam medis rumah sakit, aplikasi mobile native, pembayaran. Fitur berprioritas P2 (lupa kata sandi, cetak/unduh PDF, simulasi dampak bobot, dan auto-retain) juga tidak dikerjakan di versi 1.

### 1.3 Metrik Kesuksesan (Success Metrics)

| Kategori | Metrik | Target |
| --- | --- | --- |
| Akurasi | Kecocokan diagnosa teratas (top-1) dengan penilaian pakar pada data uji | ≥ 85% |
| Akurasi | Penyakit benar masuk 3 hasil teratas (top-3) | ≥ 95% |
| Basis pengetahuan | Jumlah kasus awal tervalidasi saat peluncuran | ≥ 100 kasus, minimal 8 jenis penyakit |
| Pembelajaran | Persentase kasus baru ter-review pakar dalam 7 hari | ≥ 80% |
| Pembelajaran | Pertumbuhan basis kasus per bulan (kasus baru tervalidasi) | ≥ 10 kasus |
| Performa | Waktu proses diagnosa (Retrieve–Reuse) dengan 1.000 kasus | < 2 detik |
| Kegunaan | Tingkat penyelesaian formulir diagnosa | ≥ 75% |
| Kegunaan | Skor System Usability Scale (SUS) | ≥ 70 |
| Kepuasan | Pengguna menilai hasil "membantu" (rating ≥ 4/5) | ≥ 80% |
| Keandalan | Ketersediaan sistem | ≥ 99% |

Evaluasi akurasi dilakukan dengan data uji terpisah (misalnya pembagian 80% data latih : 20% data uji, atau *leave-one-out*) dan dihitung accuracy, precision, recall, serta confusion matrix per penyakit.

## 2. Target Audience (User Personas)

### 2.1 Persona 1 – Pengguna Umum / Pasien

| Atribut | Deskripsi |
| --- | --- |
| Nama persona | Rina, 27 tahun, karyawan swasta |
| Latar belakang | Literasi kesehatan menengah, memakai ponsel untuk hampir semua kebutuhan |
| Kondisi | Batuk lebih dari seminggu disertai demam ringan dan sesak saat naik tangga |
| Kebutuhan | Mengetahui kemungkinan penyakit dan apakah harus segera ke dokter |
| Pain point | Takut salah pengobatan, bingung istilah medis, tidak sempat antre di klinik |
| Ekspektasi | Formulir singkat dengan bahasa awam, hasil jelas, saran tindakan, riwayat tersimpan |
| Perangkat | Dominan ponsel, koneksi bervariasi |

**Kebutuhan utama:** input gejala mudah, hasil mudah dipahami, saran tindakan dan peringatan darurat, riwayat diagnosa, privasi data.

### 2.2 Persona 2 – Pakar / Admin / Dokter

| Atribut | Deskripsi |
| --- | --- |
| Nama persona | dr. Andi, Sp.P, dokter spesialis paru |
| Latar belakang | Waktu terbatas, memahami domain, kurang mahir teknis |
| Kebutuhan | Mengelola pengetahuan (penyakit, gejala, bobot), memvalidasi kasus baru, memantau kualitas sistem |
| Pain point | Antarmuka rumit, tidak tahu alasan sistem memberi suatu hasil, pekerjaan validasi menumpuk |
| Ekspektasi | Panel admin sederhana, antrean validasi jelas, transparansi perhitungan kemiripan |

**Peran sistem:**

- **Pakar:** mengelola data penyakit/gejala/bobot/kasus dan memvalidasi kasus baru.
- **Admin:** mengelola akun pengguna dan pakar, pengaturan sistem, serta log. Pada implementasi sederhana, peran pakar dan admin dapat digabung dengan pembeda hak akses (`role`).

## 3. Functional Requirements

Prioritas: **P0** wajib, **P1** penting, **P2** tidak dikerjakan di versi 1.

### 3.1 Sisi Pengguna (Pasien)

#### FR-U1 Registrasi, Login, dan Mode Tamu (P1)

- Pengguna dapat memakai sistem sebagai **tamu** (diagnosa tanpa akun, riwayat tidak tersimpan permanen) atau **terdaftar** (riwayat tersimpan).
- Registrasi: nama, email, kata sandi, tanggal lahir, jenis kelamin. Validasi email unik dan kekuatan kata sandi.
- Login/logout dengan sesi aman, serta fitur lupa kata sandi (P2, tidak dikerjakan di versi 1).

#### FR-U2 Formulir Input Gejala (P0)

- Daftar gejala dikelompokkan per kategori (mis. Batuk, Demam, Sesak Napas, Nyeri, Hidung/Tenggorokan, Gejala Umum) dalam bentuk *accordion* atau langkah bertahap (*wizard*).
- Setiap gejala memiliki **tipe input** yang ditentukan pakar:
  - **Checkbox/Ya-Tidak** untuk gejala biner (mis. pilek, suara serak).
  - **Skala intensitas** untuk gejala bergradasi: 0 = tidak ada, 1 = ringan, 2 = sedang, 3 = berat (mis. sesak napas, nyeri dada, demam).
  - **Durasi** (opsional): < 3 hari, 3–7 hari, 1–2 minggu, > 2 minggu (mis. batuk).
- Setiap gejala dilengkapi deskripsi singkat berbahasa awam dan tooltip.
- Fitur pencarian gejala (*live search* JavaScript) dan penghitung gejala terpilih.
- Validasi: minimal 3 gejala bernilai > 0 sebelum diproses; peringatan jika terlalu sedikit.
- Data pendukung (opsional): usia, jenis kelamin, status merokok, riwayat penyakit paru.
- Persetujuan (*consent*) dan **disclaimer medis** wajib dicentang sebelum diagnosa.
- **Red-flag check:** jika pengguna memilih gejala darurat (mis. sesak berat saat istirahat, bibir kebiruan, nyeri dada hebat, batuk darah), sistem langsung menampilkan peringatan untuk segera ke IGD, terlepas dari hasil CBR.

#### FR-U3 Halaman Hasil Diagnosa (P0)

Menampilkan:

1. **Hasil utama:** nama penyakit dengan persentase kemiripan tertinggi (mis. "Bronkitis – 87,4%") disertai label keyakinan (Tinggi ≥ 80%, Sedang 60–79%, Rendah < 60%).
2. **Top-K kandidat** (default K = 3) berupa daftar peringkat dengan *progress bar* persentase.
3. **Kasus paling mirip:** ID kasus, nilai similarity, serta gejala yang cocok dan yang berbeda.
4. **Deskripsi penyakit, penyebab umum, dan solusi/saran** (perawatan mandiri, kapan ke dokter, pencegahan).
5. **Tingkat urgensi** (ringan / waspada / segera ke dokter / darurat).
6. **Transparansi:** tombol "Lihat detail perhitungan" yang menampilkan kontribusi tiap gejala terhadap skor.
7. Status kasus: "Kasus ini ditandai baru dan menunggu validasi pakar" bila similarity di bawah ambang batas.
8. Disclaimer medis, tombol cetak/unduh PDF (P2, tidak dikerjakan di versi 1), serta umpan balik pengguna ("Apakah hasil ini membantu?" rating 1–5).

#### FR-U4 Riwayat Diagnosa (P1)

- Pengguna terdaftar melihat daftar riwayat: tanggal, hasil teratas, persentase, status validasi.
- Detail riwayat membuka kembali gejala yang dimasukkan dan hasil lengkap.
- Filter berdasarkan tanggal/penyakit, dan hapus riwayat milik sendiri.
- Riwayat menampilkan **status validasi pakar** (belum divalidasi / terkonfirmasi / direvisi pakar), bila pakar mengubah diagnosa, pengguna melihat catatan pakar.

### 3.2 Sisi Pakar / Admin

#### FR-A1 Autentikasi dan Dashboard (P0)

- Login khusus pakar/admin dengan kontrol akses berbasis peran (RBAC).
- Dashboard berisi: jumlah diagnosa hari ini/bulan ini, jumlah kasus menunggu validasi, distribusi penyakit (grafik), rata-rata similarity, tingkat akurasi (diagnosa sistem vs hasil validasi pakar), serta pintasan ke antrean validasi.

#### FR-A2 CRUD Master Data Penyakit (P0)

- Atribut: kode (mis. P01), nama penyakit, deskripsi, penyebab, **solusi/penanganan**, pencegahan, tingkat urgensi, status aktif.
- Operasi: tambah, ubah, lihat, hapus, dengan pencarian, pengurutan, dan paginasi.
- Aturan integritas: penyakit yang masih dipakai kasus tidak dapat dihapus permanen (hanya dinonaktifkan / *soft delete*).

#### FR-A3 CRUD Master Data Gejala (P0)

- Atribut: kode (mis. G01), nama gejala, kategori, deskripsi awam, tipe input (biner/skala/durasi), flag *red-flag* (gejala darurat), status aktif.
- Menonaktifkan gejala menyembunyikannya dari formulir tanpa merusak kasus lama.
- Peringatan dampak sebelum ubah/hapus: menampilkan jumlah kasus yang memakai gejala tersebut.

#### FR-A4 Pengaturan Bobot / Nilai Penting Gejala (P0)

- Bobot (*weight*) skala **1–10** (atau 0,1–1,0 yang dinormalisasi) untuk setiap gejala.
- Dua tingkat bobot:
  - **Bobot global gejala** (`gejala.bobot`) sebagai bawaan.
  - **Bobot spesifik penyakit** (opsional, `bobot_gejala_penyakit`): gejala tertentu sangat menentukan satu penyakit (mis. "batuk berdahak > 2 minggu" untuk TBC) tetapi tidak untuk yang lain.
- Antarmuka tabel inline-edit atau slider, dengan tombol simpan massal.
- Pencatatan riwayat perubahan bobot (siapa, kapan, nilai lama → baru).
- Tombol **"Simulasi dampak"**: menjalankan ulang pengujian pada data uji dengan bobot baru dan menampilkan perubahan akurasi sebelum bobot diterapkan (P2, tidak dikerjakan di versi 1).

#### FR-A5 Manajemen Basis Kasus (P0)

- Daftar kasus dengan filter (penyakit, status, sumber: pakar / hasil retain), pencarian, dan paginasi.
- Tambah kasus manual: pilih penyakit (solusi), centang gejala beserta nilai intensitas.
- Ubah, hapus (soft delete), nonaktifkan kasus, dan lihat detail.
- **Impor/ekspor CSV/Excel** untuk memasukkan kasus awal dalam jumlah besar (P1).
- Setiap kasus memiliki status: `aktif`, `nonaktif`, `menunggu_validasi`, `ditolak`.
- Deteksi **duplikat**: peringatan jika kasus yang akan disimpan memiliki similarity ≥ 98% dengan kasus yang sudah ada.

#### FR-A6 Antrean Validasi Kasus Baru (P0)

Lihat bagian 3.3 (Retain) dan alur pakar di bagian 4.2.

#### FR-A7 Manajemen Pengguna dan Pengaturan Sistem (P1)

- Kelola akun pasien dan pakar (aktif/nonaktif, reset kata sandi).
- Pengaturan parameter CBR: nilai **ambang batas** (threshold) Reuse/Revise/Retain, nilai K (jumlah kandidat), serta teks disclaimer.
- Log aktivitas (audit trail) untuk perubahan data master, bobot, dan validasi.

### 3.3 Modul Mesin Inferensi CBR (Backend)

Mesin inferensi diimplementasikan sebagai kelas PHP terpisah (mis. `CBREngine`) sehingga dapat diuji tanpa antarmuka.

#### 3.3.1 Representasi Kasus

Setiap kasus = (**Problem**: himpunan gejala beserta nilainya) + (**Solution**: penyakit dan penanganannya) + (**Outcome**: status validasi pakar).

Contoh vektor kasus dengan 6 gejala: `{G01: 3, G02: 2, G03: 0, G04: 1, G05: 0, G06: 1}`.

#### 3.3.2 Retrieve – Pencarian Kasus Terdekat

1. Ambil seluruh kasus berstatus `aktif` beserta detail gejalanya (satu *query* dengan JOIN, di-cache di memori selama proses).
2. Normalisasi input pengguna menjadi vektor gejala (nilai 0–3).
3. Hitung **kemiripan (similarity)** antara kasus baru *T* dan setiap kasus lama *S* memakai **Weighted Similarity (Nearest Neighbor)**:

```
Sim(T, S) = Σ ( w_i × sim_i(T_i, S_i) ) / Σ w_i
```

dengan *w\_i* bobot gejala ke-*i* dan *sim\_i* kemiripan lokal:

- **Gejala biner:** `sim_i = 1` jika nilai sama, selain itu `0`.
- **Gejala skala (0–3):** `sim_i = 1 − |T_i − S_i| / 3`.
- **Gejala durasi:** `sim_i = 1 − |idx_T − idx_S| / (n−1)` berdasarkan urutan kategori.

  Penjumlahan dilakukan atas gejala yang dipertimbangkan, yakni gabungan (*union*) gejala yang bernilai > 0 pada kasus baru atau kasus lama, sehingga gejala yang sama-sama tidak ada tidak menggelembungkan skor.

4. **Alternatif/pelengkap: Weighted Jaccard** untuk gejala biner:

```
Jaccard_w(T, S) = Σ w_i × min(T_i, S_i) / Σ w_i × max(T_i, S_i)
```

Metode aktif dapat dipilih pakar di pengaturan (`nearest_neighbor` atau `jaccard`) sehingga perbandingan akurasi dapat diuji.

5. Urutkan hasil menurun berdasarkan similarity dan ambil **K kasus teratas** (K-NN, default K = 3 hingga 5).
6. **Agregasi per penyakit:** jika K kasus teratas berasal dari beberapa penyakit, skor penyakit = similarity tertinggi (atau rata-rata terbobot) kasus-kasus penyakit tersebut. Hasil akhir adalah daftar penyakit terurut dengan persentase = skor × 100.

**Contoh singkat.** Bobot: demam = 6, batuk = 8, sesak = 9. Pasien: demam 2, batuk 3, sesak 1. Kasus lama: demam 2, batuk 3, sesak 2.

- sim demam = 1, sim batuk = 1, sim sesak = 1 − 1/3 = 0,667
- Sim = (6×1 + 8×1 + 9×0,667) / (6 + 8 + 9) = 20 / 23 = **0,870 (87,0%)**

#### 3.3.3 Reuse – Penggunaan Solusi

- Jika similarity tertinggi ≥ **ambang Reuse (default 0,80)**, solusi (penyakit dan penanganan) kasus terdekat digunakan langsung.
- Sistem menyusun tampilan hasil: penyakit, persentase, solusi, dan kasus rujukan.
- Kasus baru disimpan sebagai **riwayat diagnosa** (tabel `diagnosa`) beserta semua kandidat (`hasil_diagnosa`).

#### 3.3.4 Revise – Revisi Solusi oleh Pakar

- Jika similarity berada pada **zona abu-abu (default 0,50 ≤ sim < 0,80)** atau pengguna memberi penilaian rendah, solusi sistem masih ditampilkan dengan label "perlu dikonfirmasi pakar".
- Kasus masuk antrean validasi dengan status `menunggu_validasi`. Pakar dapat:
  - **Menerima** diagnosa sistem apa adanya.
  - **Merevisi**: mengganti penyakit, menyesuaikan solusi, atau mengoreksi gejala, disertai catatan pakar.
  - **Menolak** kasus (data tidak valid/tidak masuk akal).
- Jika similarity < ambang bawah (default 0,50): sistem menyatakan "belum ditemukan kasus yang cukup mirip", tetap menampilkan kandidat terdekat dengan peringatan, dan menandai kasus sebagai **kasus baru** untuk pakar.

#### 3.3.5 Retain – Penyimpanan Kasus Baru (Pembelajaran)

- Kasus yang telah divalidasi (diterima atau direvisi) pakar disimpan ke **basis kasus** (`kasus` dan `detail_kasus`) berstatus `aktif`, dengan penanda sumber `retain`, ID diagnosa asal, ID pakar validator, dan waktu validasi.
- Kasus baru **tidak** otomatis masuk basis kasus aktif tanpa validasi (mencegah polusi basis pengetahuan). Opsi *auto-retain* dapat diaktifkan pakar hanya untuk kasus dengan similarity ≥ 0,95 dan umpan balik positif (P2, tidak dikerjakan di versi 1).
- **Pencegahan redundansi:** sebelum menyimpan, sistem memeriksa duplikat (similarity ≥ 0,98 dengan penyakit sama). Jika duplikat, kasus tidak ditambahkan; hanya penghitung `frekuensi_digunakan` kasus lama yang ditambah.
- **Pengelolaan basis kasus:** kasus yang ditolak disimpan sebagai arsip (bukan dihapus) untuk audit.
- Setiap kali basis kasus berubah, sistem mencatat versi basis pengetahuan dan membersihkan cache.

#### 3.3.6 Ringkasan Ambang Batas (dapat dikonfigurasi pakar)

| Similarity tertinggi | Status | Tindakan sistem |
| --- | --- | --- |
| ≥ 0,80 | Reuse | Solusi dipakai langsung, kasus tercatat di riwayat |
| 0,50 – 0,79 | Revise | Solusi ditampilkan dengan catatan, masuk antrean validasi pakar |
| < 0,50 | Kasus baru | Peringatan "belum ada kasus mirip", wajib validasi pakar sebelum Retain |

## 4. User Flow

### 4.1 Alur Pasien

1. Pengguna membuka beranda dan membaca penjelasan singkat serta disclaimer medis.
2. Memilih **Mulai Diagnosa** (tamu) atau **Login/Daftar** (riwayat tersimpan).
3. Mengisi data dasar opsional (usia, jenis kelamin, status merokok).
4. Mengisi formulir gejala per kategori (centang atau pilih skala intensitas).
5. Sistem memvalidasi input (minimal 3 gejala, persetujuan dicentang). Jika ada gejala *red-flag*, peringatan darurat langsung muncul.
6. Pengguna menekan **Proses Diagnosa**; tampil indikator *loading*.
7. Backend menjalankan **Retrieve → Reuse** dan menghitung similarity.
8. Sistem menentukan jalur berdasarkan ambang batas:
   - **≥ 80%:** hasil dan solusi ditampilkan.
   - **50–79%:** hasil ditampilkan dengan label "menunggu konfirmasi pakar".
   - **< 50%:** pesan "belum ada kasus mirip" dan kandidat terdekat ditampilkan, kasus dikirim ke pakar.
9. Pengguna melihat halaman hasil: persentase, top-K penyakit, solusi, urgensi, detail perhitungan.
10. Pengguna dapat memberi rating, mencetak hasil, atau melihat riwayat.
11. Setelah pakar memvalidasi, status di riwayat berubah dan catatan pakar tampil.

```
[Beranda] → [Mulai/Login] → [Isi gejala] → [Validasi input]
    → [Retrieve & Reuse] → {Similarity?}
        ≥80%  → [Hasil + Solusi]
        50-79 → [Hasil + "menunggu pakar"] → [Antrean validasi]
        <50   → ["Kasus baru"] → [Antrean validasi]
    → [Rating / Riwayat]
```

### 4.2 Alur Pakar

1. Pakar login melalui halaman admin dan masuk ke dashboard (notifikasi jumlah kasus menunggu validasi).
2. Membuka menu **Validasi Kasus** dan melihat antrean (urut berdasarkan tanggal atau similarity terendah).
3. Memilih satu kasus: melihat gejala dan nilai yang diisi pasien, hasil dan kandidat sistem, serta kasus paling mirip berdampingan (perbandingan gejala).
4. Pakar memutuskan:
   - **Terima** diagnosa sistem.
   - **Revisi**: memilih penyakit yang benar (atau membuat penyakit baru), mengedit solusi, mengoreksi gejala, menulis catatan.
   - **Tolak**: kasus tidak valid, alasan wajib diisi.
5. Pada Terima/Revisi, sistem menjalankan **Retain**: cek duplikat, simpan ke `kasus` dan `detail_kasus` dengan status `aktif`.
6. Status diagnosa pasien diperbarui (`terkonfirmasi` / `direvisi` / `ditolak`) dan basis kasus bertambah.
7. Jika perlu, pakar menyesuaikan bobot gejala, mengelola master data, atau melihat laporan akurasi.
8. Log aktivitas mencatat semua tindakan validasi.

```
[Login pakar] → [Dashboard] → [Antrean validasi] → [Detail kasus]
    → {Keputusan}
        Terima → [Retain] → [Kasus aktif baru]
        Revisi → [Edit penyakit/solusi] → [Retain]
        Tolak  → [Arsip + alasan]
    → [Status diagnosa pasien diperbarui]
```

## 5. Non-Functional Requirements

### 5.1 Performa

| ID | Kebutuhan | Target |
| --- | --- | --- |
| NFR-P1 | Waktu muat halaman utama (koneksi 4G) | < 3 detik |
| NFR-P2 | Waktu proses diagnosa dengan ≤ 1.000 kasus dan ≤ 60 gejala | < 2 detik |
| NFR-P3 | Waktu proses dengan ≤ 10.000 kasus | < 5 detik |
| NFR-P4 | Pengguna serentak | ≥ 100 tanpa penurunan berarti |
| NFR-P5 | Optimasi | Indeks database pada kolom kunci, *prepared statement*, kasus aktif di-cache, minifikasi CSS/JS, kompresi gambar |

### 5.2 Keamanan dan Privasi Data

Data gejala kesehatan tergolong data sensitif sehingga perlindungannya diprioritaskan.

- **Autentikasi:** kata sandi di-hash dengan `password_hash()` (bcrypt/argon2); kebijakan kata sandi minimal 8 karakter; pembatasan percobaan login (*rate limiting*); sesi aman (`HttpOnly`, `SameSite`, regenerasi ID sesi).
- **Otorisasi:** RBAC (tamu, pasien, pakar, admin); halaman admin tidak dapat diakses peran lain; pasien hanya melihat riwayat miliknya (cegah IDOR).
- **Injeksi dan serangan web:** seluruh query memakai **PDO prepared statements** (anti SQL Injection); *output escaping* dengan `htmlspecialchars()` (anti XSS); **token CSRF** pada semua formulir; validasi dan sanitasi input di sisi klien dan server.
- **Transport dan konfigurasi:** wajib HTTPS di produksi; *security headers* (CSP, X-Frame-Options, X-Content-Type-Options); pesan galat tidak membocorkan detail sistem; kredensial database di berkas konfigurasi di luar *web root* atau `.env`.
- **Privasi:** persetujuan pengguna (*consent*) sebelum menyimpan data; minimisasi data pribadi; pengguna dapat menghapus riwayat; data pada kasus yang di-*retain* dianonimkan (tanpa identitas pasien); selaras prinsip pelindungan data pribadi (UU PDP No. 27 Tahun 2022).
- **Cadangan dan audit:** pencadangan database otomatis harian (`mysqldump`); log aktivitas untuk perubahan data master dan validasi.
- **Unggahan berkas (impor CSV):** validasi tipe dan ukuran berkas, dibatasi untuk pakar/admin.

### 5.3 Responsivitas dan Antarmuka (UI/UX)

- Desain **mobile-first** dan responsif untuk lebar layar 360 px hingga desktop (breakpoint 576/768/992/1200 px).
- Kompatibel dengan peramban modern: Chrome, Firefox, Edge, Safari (dua versi terakhir).
- Formulir gejala nyaman di layar sentuh: target sentuh ≥ 44 px, tampilan bertahap (*wizard*) di ponsel.
- Aksesibilitas dasar (WCAG 2.1 AA): kontras warna memadai, label formulir, navigasi keyboard, teks alternatif.
- Bahasa antarmuka Bahasa Indonesia dengan istilah medis yang dijelaskan secara awam.
- Umpan balik yang jelas: indikator *loading*, pesan galat/sukses, konfirmasi sebelum hapus.

### 5.4 Kebutuhan Lainnya

- **Keandalan:** ketersediaan ≥ 99%, penanganan galat yang rapi, transaksi database (*commit/rollback*) saat proses Retain.
- **Maintainability:** kode terstruktur (pola MVC sederhana), komentar dan dokumentasi, pemisahan logika CBR dari tampilan.
- **Skalabilitas:** skema mendukung penambahan penyakit dan gejala tanpa mengubah kode.
- **Kepatuhan etika:** disclaimer pada setiap hasil bahwa sistem hanya alat bantu skrining dan tidak menggantikan diagnosis tenaga medis.

## 6. Database Architecture

DBMS: **MySQL / MariaDB**, mesin penyimpanan **InnoDB** (mendukung foreign key dan transaksi), *charset* `utf8mb4`.

### 6.1 Daftar Tabel

| Tabel | Fungsi |
| --- | --- |
| `users` | Akun tamu terdaftar, pakar, dan admin |
| `penyakit` | Master penyakit sistem pernapasan beserta solusi |
| `gejala` | Master gejala beserta tipe input dan bobot global |
| `bobot_gejala_penyakit` | Bobot gejala spesifik per penyakit (opsional) |
| `kasus` | Basis kasus (case base) |
| `detail_kasus` | Gejala dan nilai milik setiap kasus |
| `diagnosa` | Sesi diagnosa pasien (kasus baru) = riwayat |
| `detail_diagnosa` | Gejala dan nilai yang diinput pasien per sesi |
| `hasil_diagnosa` | Kandidat penyakit/kasus hasil Retrieve beserta similarity |
| `validasi_kasus` | Keputusan pakar atas diagnosa (Revise/Retain) |
| `pengaturan` | Parameter CBR (ambang batas, K, metode) |
| `log_aktivitas` | Audit trail |

### 6.2 Struktur Tabel Krusial

**users**

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| id\_user | INT UNSIGNED PK AI |  |
| nama | VARCHAR(100) |  |
| email | VARCHAR(120) UNIQUE |  |
| password\_hash | VARCHAR(255) | hasil `password_hash()` |
| role | ENUM('pasien','pakar','admin') |  |
| tanggal\_lahir, jenis\_kelamin | DATE, ENUM | opsional |
| is\_active | TINYINT(1) |  |
| created\_at | TIMESTAMP |  |

**penyakit**

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| id\_penyakit | INT UNSIGNED PK AI |  |
| kode\_penyakit | VARCHAR(10) UNIQUE | mis. P01 |
| nama\_penyakit | VARCHAR(120) |  |
| deskripsi, penyebab | TEXT |  |
| solusi | TEXT | penanganan/saran |
| pencegahan | TEXT |  |
| tingkat\_urgensi | ENUM('ringan','waspada','segera','darurat') |  |
| is\_active | TINYINT(1) | soft delete |

**gejala**

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| id\_gejala | INT UNSIGNED PK AI |  |
| kode\_gejala | VARCHAR(10) UNIQUE | mis. G01 |
| nama\_gejala | VARCHAR(150) |  |
| kategori | VARCHAR(50) | untuk pengelompokan formulir |
| tipe\_input | ENUM('biner','skala','durasi') |  |
| bobot | DECIMAL(4,2) | bobot global 1–10 |
| is\_redflag | TINYINT(1) | gejala darurat |
| is\_active | TINYINT(1) |  |

**bobot\_gejala\_penyakit** (opsional)

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| id\_penyakit | FK → penyakit | PK komposit |
| id\_gejala | FK → gejala | PK komposit |
| bobot | DECIMAL(4,2) | menimpa bobot global bila ada |

**kasus**

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| id\_kasus | INT UNSIGNED PK AI |  |
| kode\_kasus | VARCHAR(15) UNIQUE | mis. K001 |
| id\_penyakit | FK → penyakit | solusi/label kasus |
| sumber | ENUM('pakar','retain','import') | asal kasus |
| id\_diagnosa\_asal | FK → diagnosa NULL | untuk kasus hasil Retain |
| status | ENUM('aktif','nonaktif','arsip') |  |
| frekuensi\_digunakan | INT | berapa kali menjadi rujukan |
| id\_validator | FK → users NULL |  |
| created\_at, validated\_at | TIMESTAMP |  |

**detail\_kasus**

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| id\_detail | INT UNSIGNED PK AI |  |
| id\_kasus | FK → kasus (ON DELETE CASCADE) |  |
| id\_gejala | FK → gejala |  |
| nilai | TINYINT | 0–3 (biner: 0/1) |
| UNIQUE (id\_kasus, id\_gejala) |  | cegah duplikasi |

**diagnosa**

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| id\_diagnosa | INT UNSIGNED PK AI |  |
| id\_user | FK → users NULL | NULL untuk tamu |
| session\_token | VARCHAR(64) | identifikasi tamu |
| usia, jenis\_kelamin, status\_merokok |  | data pendukung opsional |
| id\_penyakit\_hasil | FK → penyakit | hasil teratas sistem |
| similarity\_tertinggi | DECIMAL(5,4) | 0–1 |
| status\_cbr | ENUM('reuse','revise','baru') | jalur ambang batas |
| status\_validasi | ENUM('tidak\_perlu','menunggu','terkonfirmasi','direvisi','ditolak') |  |
| rating\_pengguna | TINYINT NULL | 1–5 |
| created\_at | TIMESTAMP |  |

**detail\_diagnosa**: `id_diagnosa` (FK), `id_gejala` (FK), `nilai` TINYINT, UNIQUE (id\_diagnosa, id\_gejala).

**hasil\_diagnosa**: `id_hasil`, `id_diagnosa` (FK), `id_kasus` (FK), `id_penyakit` (FK), `similarity` DECIMAL(5,4), `peringkat` TINYINT.

**validasi\_kasus**: `id_validasi`, `id_diagnosa` (FK, UNIQUE), `id_pakar` (FK → users), `keputusan` ENUM('terima','revisi','tolak'), `id_penyakit_final` (FK), `solusi_revisi` TEXT NULL, `catatan` TEXT, `id_kasus_baru` (FK → kasus NULL), `created_at`.

**pengaturan**: `kunci` VARCHAR(50) PK, `nilai` VARCHAR(255). Contoh kunci: `threshold_reuse` = 0.80, `threshold_revise` = 0.50, `top_k` = 3, `metode_similarity` = nearest\_neighbor, `threshold_duplikat` = 0.98.

**log\_aktivitas**: `id_log`, `id_user`, `aksi`, `tabel`, `id_rekaman`, `data_lama` JSON, `data_baru` JSON, `ip_address`, `created_at`.

### 6.3 Relasi Antar Tabel

| Relasi | Kardinalitas | Keterangan |
| --- | --- | --- |
| penyakit → kasus | 1 : N | satu penyakit memiliki banyak kasus |
| kasus → detail\_kasus | 1 : N | satu kasus memiliki banyak gejala |
| gejala → detail\_kasus | 1 : N | satu gejala dipakai banyak kasus |
| kasus ↔ gejala | M : N | diselesaikan oleh `detail_kasus` |
| penyakit ↔ gejala | M : N | diselesaikan oleh `bobot_gejala_penyakit` |
| users → diagnosa | 1 : N | satu pengguna banyak diagnosa (tamu: NULL) |
| diagnosa → detail\_diagnosa | 1 : N | gejala input pasien |
| diagnosa → hasil\_diagnosa | 1 : N | kandidat hasil Retrieve |
| kasus → hasil\_diagnosa | 1 : N | kasus rujukan |
| diagnosa → validasi\_kasus | 1 : 0..1 | validasi pakar |
| validasi\_kasus → kasus | N : 0..1 | kasus baru hasil Retain |
| users (pakar) → validasi\_kasus | 1 : N | pakar yang memvalidasi |

```
users 1──N diagnosa 1──N detail_diagnosa N──1 gejala
                 │ 1──N hasil_diagnosa N──1 kasus N──1 penyakit
                 └ 1──0..1 validasi_kasus ──(Retain)──> kasus
kasus 1──N detail_kasus N──1 gejala
penyakit M──N gejala (via bobot_gejala_penyakit)
```

**Indeks yang disarankan:** `kasus(id_penyakit, status)`, `detail_kasus(id_kasus)`, `detail_kasus(id_gejala)`, `diagnosa(id_user, created_at)`, `diagnosa(status_validasi)`, `hasil_diagnosa(id_diagnosa, peringkat)`.

## 7. Tech Stack & Deployment

### 7.1 Rekomendasi Teknologi

| Lapisan | Pilihan | Alasan |
| --- | --- | --- |
| Backend | **PHP 8.1+** native dengan pola MVC sederhana (tanpa framework) | Sesuai spesifikasi proyek; lebih mudah dijelaskan dan dipertanggungjawabkan saat sidang. Keamanan (CSRF, hashing, validasi) diterapkan manual. |
| Akses database | **PDO** (prepared statements) | Anti SQL Injection |
| Database | **MySQL 8 / MariaDB 10.x** | Sesuai spesifikasi |
| Frontend CSS | **Bootstrap 5.3** (dipilih untuk kemudahan mobile-first) atau Tailwind CSS | Komponen formulir, grid, modal siap pakai |
| JavaScript | JavaScript vanilla ES6+, dengan **jQuery** (opsional) | Validasi formulir, pencarian gejala, AJAX |
| Tabel data | **DataTables** | Pencarian, pengurutan, paginasi di panel pakar |
| Grafik | **Chart.js** | Grafik dashboard dan batang persentase hasil |
| Notifikasi/dialog | **SweetAlert2** | Konfirmasi hapus dan pesan sukses/galat |
| Ikon | Bootstrap Icons / Font Awesome |  |
| Impor/ekspor | **PhpSpreadsheet** | Impor basis kasus awal dari Excel/CSV |
| Cetak PDF | **Dompdf** atau mPDF (tidak dikerjakan di versi 1) | Cetak hasil diagnosa |
| Dependency manager | **Composer** | Mengelola pustaka PHP |
| Pengujian | **PHPUnit** | Uji unit mesin CBR (hitung similarity, threshold) |

### 7.2 Struktur Direktori yang Disarankan

```
/sistem-pakar-cbr
├── /public          (index.php, aset css/js/img)
├── /app
│   ├── /controllers (Auth, Diagnosa, Penyakit, Gejala, Kasus, Validasi)
│   ├── /models      (User, Penyakit, Gejala, Kasus, Diagnosa)
│   ├── /services    (CBREngine.php: retrieve, reuse, revise, retain)
│   └── /views       (user/, admin/)
├── /config          (database.php, .env)
├── /database        (schema.sql, seed.sql)
├── /tests
└── composer.json
```

### 7.3 Lingkungan Pengembangan Lokal

- **XAMPP** (Apache + MySQL/MariaDB + PHP) atau **Laragon** (lebih ringan, mendukung *virtual host* otomatis seperti `sistem-pakar.test`).
- Editor: Visual Studio Code (ekstensi PHP Intelephense); manajemen database: phpMyAdmin atau DBeaver/HeidiSQL.
- Kontrol versi: **Git** dan repositori GitHub/GitLab.
- Data awal: berkas `schema.sql` (struktur) dan `seed.sql` (penyakit, gejala, bobot, dan kasus awal dari pakar).

### 7.4 Deployment

| Lingkungan | Spesifikasi |
| --- | --- |
| Development | Laragon di komputer lokal |
| Staging/Produksi | Shared hosting atau VPS (Apache/Nginx, PHP 8.1+, MySQL), HTTPS dengan Let's Encrypt |
| Konfigurasi produksi | `display_errors=Off`, kredensial di `.env`, pembatasan akses folder, pencadangan database harian dengan cron |

### 7.5 Rencana Rilis (Roadmap)

| Fase | Cakupan | Estimasi |
| --- | --- | --- |
| 1 – Fondasi | Skema database, autentikasi, CRUD penyakit dan gejala | 2 minggu |
| 2 – Basis kasus | CRUD kasus, bobot, impor kasus awal | 2 minggu |
| 3 – Mesin CBR | Retrieve, Reuse, formulir gejala, halaman hasil | 3 minggu |
| 4 – Revise & Retain | Antrean validasi, Retain, riwayat, dashboard | 2 minggu |
| 5 – Pengujian | Uji unit, uji akurasi, uji keamanan, UAT dengan pakar, SUS | 2 minggu |
| 6 – Peluncuran | Deployment, dokumentasi, pelatihan pakar | 1 minggu |

### 7.6 Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| Basis kasus awal terlalu sedikit/tidak seimbang | Akurasi rendah | Kolaborasi pakar untuk minimal 100 kasus, seimbang antarpenyakit; gunakan data rekam medis yang dianonimkan |
| Pengguna menganggap hasil sebagai diagnosis pasti | Risiko kesehatan dan hukum | Disclaimer jelas, urgensi dan saran ke dokter, peringatan *red-flag* |
| Pakar tidak sempat memvalidasi | Pembelajaran macet | Prioritas antrean, notifikasi, opsi auto-retain terbatas |
| Pencemaran basis kasus oleh data keliru | Penurunan akurasi | Retain hanya setelah validasi pakar, cek duplikat, arsip kasus ditolak |
| Kebocoran data kesehatan | Pelanggaran privasi | Hashing, HTTPS, RBAC, anonimisasi kasus, audit log |

---

*Catatan: bobot, ambang batas, dan nilai K pada dokumen ini adalah nilai awal yang disarankan; ditetapkan final bersama pakar medis dan disesuaikan melalui pengujian akurasi.*
