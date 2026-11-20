# AGENTS.md: Sistem Pakar Penyakit Pernapasan (CBR)

Website sistem pakar skrining awal gangguan pernapasan dengan Case-Based Reasoning (siklus 4R: Retrieve, Reuse, Revise, Retain). Dua peran: pasien dan pakar/admin.

Sumber kebenaran fitur: `docs/PRD.md`. Struktur database: `database/schema.sql`. Jika keduanya bertentangan dengan berkas ini, tanyakan dulu, jangan memilih sendiri.

## Keputusan teknis (sudah final)

- PHP 8.1+ native, pola MVC sederhana, **tanpa framework** (bukan Laravel).
- MySQL 8 / MariaDB, semua akses lewat **PDO prepared statements**.
- Frontend: HTML, CSS, JavaScript vanilla, Bootstrap 5.3 (ubah baris ini bila memilih Tailwind).
- Pustaka via Composer: PhpSpreadsheet (impor CSV/Excel), PHPUnit (uji). Front-end opsional: DataTables, Chart.js, SweetAlert2.
- Lingkungan lokal: **Laragon** (virtual host `sistem-pakar.test`). Impor skema: `mysql -u root < database/schema.sql`.
- Fitur P2 **tidak dikerjakan di versi 1**: lupa kata sandi, cetak/unduh PDF, simulasi dampak bobot, auto-retain. Jangan dibuat meski terlihat mudah.

## Keputusan terbuka (tanyakan sebelum dikerjakan)

- Bobot spesifik per penyakit (`bobot_gejala_penyakit`): tabelnya ada, tetapi mesin CBR memakai **bobot global** `gejala.bobot` sampai ada keputusan.

## Struktur direktori

```
public/            index.php dan aset (css, js, img): satu-satunya folder yang diakses web
app/controllers/   Auth, Diagnosa, Penyakit, Gejala, Kasus, Validasi
app/models/        User, Penyakit, Gejala, Kasus, Diagnosa
app/services/      CBREngine.php (retrieve, reuse, revise, retain)
app/views/         user/, admin/
config/            database.php, .env (jangan di-commit)
database/          schema.sql, seed.sql
tests/
```

## Aturan keamanan (wajib di setiap berkas)

- Semua query memakai PDO prepared statements. Dilarang menggabungkan input ke string SQL.
- Kata sandi: `password_hash()` / `password_verify()`. Jangan simpan atau catat kata sandi asli.
- Setiap formulir POST memakai token CSRF. Setiap output ke HTML melewati `htmlspecialchars()`.
- Cek peran (pasien, pakar, admin) di sisi server pada setiap halaman admin. Pasien hanya boleh membuka riwayat miliknya.
- Sesi: `HttpOnly`, `SameSite`, regenerasi ID saat login. Batasi percobaan login.
- Kredensial database hanya di `config/.env`, tidak di kode, tidak di Git.

## Aturan mesin CBR (`CBREngine`)

- Similarity terbobot: `Sim(T,S) = Σ(w_i × sim_i) / Σ w_i`, dihitung atas gabungan gejala yang bernilai > 0 pada kasus baru atau kasus lama.
  - Biner: 1 jika sama, selain itu 0. Skala 0-3: `1 - |a-b|/3`.
- Ambil K kasus teratas (`top_k`), skor penyakit = similarity tertinggi kasus penyakit itu.
- Ambang batas dibaca dari tabel `pengaturan`, **jangan di-hardcode**: `>= threshold_reuse` Reuse; antara `threshold_revise` dan `threshold_reuse` Revise (masuk antrean pakar); di bawahnya kasus baru.
- **Retain hanya setelah validasi pakar.** Kasus baru tidak boleh masuk `kasus` berstatus `aktif` tanpa baris di `validasi_kasus`.
- Retain berjalan dalam satu transaksi (commit/rollback) dan memeriksa duplikat (`threshold_duplikat`).
- Logika CBR berada di `app/services/`, bukan di controller atau view, supaya bisa diuji tanpa antarmuka.
- Peringatan darurat (gejala `is_redflag = 1`) harus tampil terlepas dari hasil CBR.

## Aturan data medis

- Jangan mengarang data penyakit, gejala, bobot, atau kasus untuk dipakai sungguhan. Bila `seed.sql` dibuat, semua isinya **data DUMMY**, beri komentar `-- DUMMY: bukan data medis` di awal berkas.
- Setiap halaman hasil menampilkan disclaimer: sistem hanya skrining awal, bukan pengganti diagnosis dokter.

## Cara bekerja

1. Kerjakan **per fase**, jangan sekaligus. Berhenti dan tunjukkan hasil di akhir tiap fase:
   1. Database, autentikasi, CRUD penyakit dan gejala
   2. CRUD kasus, pengaturan bobot, impor kasus awal
   3. `CBREngine` (Retrieve, Reuse), formulir gejala, halaman hasil
   4. Antrean validasi (Revise, Retain), riwayat, dashboard
   5. Pengujian dan perbaikan
2. Tulis uji PHPUnit untuk `CBREngine`. Test pertama: bobot demam 6, batuk 8, sesak 9; pasien (2, 3, 1) dibanding kasus (2, 3, 2) harus menghasilkan similarity 0,870.
3. Perubahan skema database dicatat sebagai berkas migrasi baru di `database/`, jangan mengedit `schema.sql` diam-diam.
4. Bahasa antarmuka: Bahasa Indonesia, istilah medis dijelaskan dengan bahasa awam. Komentar kode boleh Indonesia.
5. Jangan menambah pustaka di luar daftar di atas tanpa bertanya.
