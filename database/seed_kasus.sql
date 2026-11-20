-- DUMMY: bukan data medis
-- ============================================================
-- seed_kasus.sql — kasus awal DUMMY untuk Fase 2 (CRUD Basis Kasus)
--
-- SELURUH ISI BERKAS INI ADALAH DATA DUMMY untuk pengembangan dan
-- pengujian. Pola "penyakit → gejala" di bawah tidak boleh dipakai
-- sebagai pengetahuan medis sungguhan (aturan data medis, AGENTS.md).
--
-- Berkas ini TERPISAH dari seed.sql (master data). Cara memasukkan:
--   php database/setup_init.php --with-cases
-- (dijalankan hanya bila tabel kasus masih kosong → idempoten).
--
-- Isi: 20 kasus K001-K020 tersebar pada penyakit P01-P08:
--   K006 berstatus 'nonaktif' untuk menguji filter status;
--   K019 bersumber 'import' untuk menguji filter sumber;
--   K012 hanya G14=2 dan G16=1 — dipakai menguji deteksi duplikat
--        (input yang sama harus menghasilkan similarity 1,0).
-- Setiap kasus memuat vektor LENGKAP 18 gejala (nilai 0 ikut
-- disimpan), sama seperti perilaku formulir kasus manual.
-- ============================================================

USE sistem_pakar_cbr;

-- DUMMY: bukan data medis — kepala kasus (20 baris)
INSERT INTO kasus (kode_kasus, id_penyakit, sumber, status) VALUES
  ('K001', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P01'), 'pakar',   'aktif'),
  ('K002', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P01'), 'pakar',   'aktif'),
  ('K003', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P02'), 'pakar',   'aktif'),
  ('K004', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P02'), 'pakar',   'aktif'),
  ('K005', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P03'), 'pakar',   'aktif'),
  ('K006', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P03'), 'pakar',   'nonaktif'),
  ('K007', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P04'), 'pakar',   'aktif'),
  ('K008', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P04'), 'pakar',   'aktif'),
  ('K009', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P05'), 'pakar',   'aktif'),
  ('K010', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P05'), 'pakar',   'aktif'),
  ('K011', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P05'), 'pakar',   'aktif'),
  ('K012', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P05'), 'pakar',   'aktif'),
  ('K013', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P06'), 'pakar',   'aktif'),
  ('K014', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P06'), 'pakar',   'aktif'),
  ('K015', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P07'), 'pakar',   'aktif'),
  ('K016', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P07'), 'pakar',   'aktif'),
  ('K017', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P08'), 'pakar',   'aktif'),
  ('K018', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P08'), 'pakar',   'aktif'),
  ('K019', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P01'), 'import',  'aktif'),
  ('K020', (SELECT id_penyakit FROM penyakit WHERE kode_penyakit = 'P02'), 'pakar',   'aktif');

-- DUMMY: bukan data medis — detail gejala bernilai > 0
-- (nilai selain daftar ini diisi 0 oleh pernyataan berikutnya)
INSERT INTO detail_kasus (id_kasus, id_gejala, nilai)
SELECT k.id_kasus, g.id_gejala, d.nilai
FROM (
    SELECT 'K001' AS kode_kasus, 'G01' AS kode_gejala, 1 AS nilai
    UNION ALL SELECT 'K001', 'G05', 1
    UNION ALL SELECT 'K001', 'G06', 1
    UNION ALL SELECT 'K001', 'G07', 1
    UNION ALL SELECT 'K001', 'G08', 1

    UNION ALL SELECT 'K002', 'G05', 1
    UNION ALL SELECT 'K002', 'G06', 1
    UNION ALL SELECT 'K002', 'G07', 1
    UNION ALL SELECT 'K002', 'G09', 1

    UNION ALL SELECT 'K003', 'G01', 3
    UNION ALL SELECT 'K003', 'G02', 1
    UNION ALL SELECT 'K003', 'G03', 1
    UNION ALL SELECT 'K003', 'G05', 1
    UNION ALL SELECT 'K003', 'G07', 1
    UNION ALL SELECT 'K003', 'G18', 2

    UNION ALL SELECT 'K004', 'G01', 2
    UNION ALL SELECT 'K004', 'G02', 1
    UNION ALL SELECT 'K004', 'G03', 1
    UNION ALL SELECT 'K004', 'G08', 1
    UNION ALL SELECT 'K004', 'G10', 2

    UNION ALL SELECT 'K005', 'G01', 1
    UNION ALL SELECT 'K005', 'G08', 2
    UNION ALL SELECT 'K005', 'G09', 1

    UNION ALL SELECT 'K006', 'G03', 1
    UNION ALL SELECT 'K006', 'G08', 3

    UNION ALL SELECT 'K007', 'G01', 1
    UNION ALL SELECT 'K007', 'G03', 1
    UNION ALL SELECT 'K007', 'G10', 3
    UNION ALL SELECT 'K007', 'G11', 1

    UNION ALL SELECT 'K008', 'G10', 2
    UNION ALL SELECT 'K008', 'G11', 1
    UNION ALL SELECT 'K008', 'G14', 1

    UNION ALL SELECT 'K009', 'G10', 2
    UNION ALL SELECT 'K009', 'G13', 1
    UNION ALL SELECT 'K009', 'G14', 2
    UNION ALL SELECT 'K009', 'G16', 1

    UNION ALL SELECT 'K010', 'G14', 3
    UNION ALL SELECT 'K010', 'G15', 1
    UNION ALL SELECT 'K010', 'G16', 1

    UNION ALL SELECT 'K011', 'G07', 1
    UNION ALL SELECT 'K011', 'G14', 1
    UNION ALL SELECT 'K011', 'G16', 1

    -- K012: kasus uji duplikat — hanya dua gejala di atas 0
    UNION ALL SELECT 'K012', 'G14', 2
    UNION ALL SELECT 'K012', 'G16', 1

    UNION ALL SELECT 'K013', 'G01', 3
    UNION ALL SELECT 'K013', 'G02', 1
    UNION ALL SELECT 'K013', 'G10', 3
    UNION ALL SELECT 'K013', 'G11', 1
    UNION ALL SELECT 'K013', 'G14', 2
    UNION ALL SELECT 'K013', 'G18', 2

    UNION ALL SELECT 'K014', 'G01', 2
    UNION ALL SELECT 'K014', 'G10', 2
    UNION ALL SELECT 'K014', 'G11', 1
    UNION ALL SELECT 'K014', 'G14', 1
    UNION ALL SELECT 'K014', 'G15', 1

    UNION ALL SELECT 'K015', 'G01', 1
    UNION ALL SELECT 'K015', 'G03', 1
    UNION ALL SELECT 'K015', 'G04', 1
    UNION ALL SELECT 'K015', 'G10', 3

    UNION ALL SELECT 'K016', 'G03', 1
    UNION ALL SELECT 'K016', 'G04', 1
    UNION ALL SELECT 'K016', 'G10', 3
    UNION ALL SELECT 'K016', 'G12', 1

    UNION ALL SELECT 'K017', 'G10', 3
    UNION ALL SELECT 'K017', 'G14', 2
    UNION ALL SELECT 'K017', 'G16', 1
    UNION ALL SELECT 'K017', 'G18', 1

    UNION ALL SELECT 'K018', 'G03', 1
    UNION ALL SELECT 'K018', 'G10', 3
    UNION ALL SELECT 'K018', 'G14', 3
    UNION ALL SELECT 'K018', 'G15', 2

    UNION ALL SELECT 'K019', 'G01', 1
    UNION ALL SELECT 'K019', 'G05', 1
    UNION ALL SELECT 'K019', 'G06', 1
    UNION ALL SELECT 'K019', 'G07', 1
    UNION ALL SELECT 'K019', 'G08', 1

    UNION ALL SELECT 'K020', 'G01', 2
    UNION ALL SELECT 'K020', 'G03', 1
    UNION ALL SELECT 'K020', 'G08', 1
    UNION ALL SELECT 'K020', 'G14', 1
) AS d
JOIN kasus  k ON k.kode_kasus  = d.kode_kasus
JOIN gejala g ON g.kode_gejala = d.kode_gejala;

-- DUMMY: bukan data medis — lengkapi vektor: semua gejala aktif yang
-- belum punya baris detail untuk kasus tertentu diisi nilai 0.
INSERT INTO detail_kasus (id_kasus, id_gejala, nilai)
SELECT k.id_kasus, g.id_gejala, 0
FROM kasus k
JOIN gejala g ON g.is_active = 1
LEFT JOIN detail_kasus dk
       ON dk.id_kasus = k.id_kasus AND dk.id_gejala = g.id_gejala
WHERE dk.id_kasus IS NULL;

-- DUMMY: bukan data medis — selesai seed kasus Fase 2
