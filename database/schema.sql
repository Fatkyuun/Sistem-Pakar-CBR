-- ============================================================
-- schema.sql : Sistem Pakar Penyakit Pernapasan (CBR 4R)
-- Mengacu pada PRD bagian 6. Target: MySQL 8.0.16+ / MariaDB 10.5+ (InnoDB).
-- Impor: mysql -u root < database/schema.sql
-- Skema ini TIDAK berisi data medis. Data penyakit, gejala, bobot, dan kasus
-- harus berasal dari pakar (lihat seed.sql terpisah, bila ada, dan beri label DUMMY).
-- ============================================================

CREATE DATABASE IF NOT EXISTS sistem_pakar_cbr
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistem_pakar_cbr;

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1. users : pasien, pakar, admin
-- ------------------------------------------------------------
CREATE TABLE users (
  id_user        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama           VARCHAR(100) NOT NULL,
  email          VARCHAR(120) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,              -- hasil password_hash(), jangan simpan teks asli
  role           ENUM('pasien','pakar','admin') NOT NULL DEFAULT 'pasien',
  tanggal_lahir  DATE NULL,
  jenis_kelamin  ENUM('L','P') NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_user),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. penyakit : master penyakit + solusi
-- ------------------------------------------------------------
CREATE TABLE penyakit (
  id_penyakit      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode_penyakit    VARCHAR(10) NOT NULL,               -- mis. P01
  nama_penyakit    VARCHAR(120) NOT NULL,
  deskripsi        TEXT NULL,
  penyebab         TEXT NULL,
  solusi           TEXT NULL,                          -- penanganan / saran
  pencegahan       TEXT NULL,
  tingkat_urgensi  ENUM('ringan','waspada','segera','darurat') NOT NULL DEFAULT 'ringan',
  is_active        TINYINT(1) NOT NULL DEFAULT 1,      -- soft delete
  PRIMARY KEY (id_penyakit),
  UNIQUE KEY uq_penyakit_kode (kode_penyakit)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. gejala : master gejala + tipe input + bobot global
-- ------------------------------------------------------------
CREATE TABLE gejala (
  id_gejala    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode_gejala  VARCHAR(10) NOT NULL,                   -- mis. G01
  nama_gejala  VARCHAR(150) NOT NULL,
  deskripsi    VARCHAR(255) NULL,                      -- penjelasan bahasa awam
  kategori     VARCHAR(50) NOT NULL,                   -- pengelompokan formulir
  tipe_input   ENUM('biner','skala','durasi') NOT NULL DEFAULT 'biner',
  bobot        DECIMAL(4,2) NOT NULL DEFAULT 1.00,     -- bobot global 1-10
  is_redflag   TINYINT(1) NOT NULL DEFAULT 0,          -- gejala darurat
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_gejala),
  UNIQUE KEY uq_gejala_kode (kode_gejala),
  CONSTRAINT chk_gejala_bobot CHECK (bobot BETWEEN 1 AND 10)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. bobot_gejala_penyakit : bobot spesifik per penyakit (OPSIONAL)
--    Menimpa gejala.bobot bila ada barisnya. Belum dipakai mesin CBR
--    sebelum diputuskan (lihat AGENTS.md, bagian Keputusan terbuka).
-- ------------------------------------------------------------
CREATE TABLE bobot_gejala_penyakit (
  id_penyakit  INT UNSIGNED NOT NULL,
  id_gejala    INT UNSIGNED NOT NULL,
  bobot        DECIMAL(4,2) NOT NULL,
  PRIMARY KEY (id_penyakit, id_gejala),
  CONSTRAINT fk_bgp_penyakit FOREIGN KEY (id_penyakit) REFERENCES penyakit (id_penyakit),
  CONSTRAINT fk_bgp_gejala   FOREIGN KEY (id_gejala)   REFERENCES gejala (id_gejala),
  CONSTRAINT chk_bgp_bobot CHECK (bobot BETWEEN 1 AND 10)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. diagnosa : sesi diagnosa pasien (kasus baru) = riwayat
-- ------------------------------------------------------------
CREATE TABLE diagnosa (
  id_diagnosa           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_user               INT UNSIGNED NULL,             -- NULL untuk tamu
  session_token         VARCHAR(64) NULL,              -- identifikasi tamu
  usia                  TINYINT UNSIGNED NULL,
  jenis_kelamin         ENUM('L','P') NULL,
  status_merokok        ENUM('tidak','mantan','aktif') NULL,
  id_penyakit_hasil     INT UNSIGNED NULL,             -- hasil teratas sistem
  similarity_tertinggi  DECIMAL(5,4) NULL,             -- 0.0000 - 1.0000
  status_cbr            ENUM('reuse','revise','baru') NULL,
  status_validasi       ENUM('tidak_perlu','menunggu','terkonfirmasi','direvisi','ditolak')
                        NOT NULL DEFAULT 'tidak_perlu',
  rating_pengguna       TINYINT UNSIGNED NULL,         -- 1-5
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_diagnosa),
  KEY idx_diagnosa_user_waktu (id_user, created_at),
  KEY idx_diagnosa_validasi (status_validasi),
  CONSTRAINT fk_diagnosa_user FOREIGN KEY (id_user) REFERENCES users (id_user) ON DELETE SET NULL,
  CONSTRAINT fk_diagnosa_penyakit FOREIGN KEY (id_penyakit_hasil) REFERENCES penyakit (id_penyakit),
  CONSTRAINT chk_diagnosa_sim CHECK (similarity_tertinggi IS NULL OR similarity_tertinggi BETWEEN 0 AND 1),
  CONSTRAINT chk_diagnosa_rating CHECK (rating_pengguna IS NULL OR rating_pengguna BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. kasus : basis kasus (case base)
-- ------------------------------------------------------------
CREATE TABLE kasus (
  id_kasus             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode_kasus           VARCHAR(15) NOT NULL,           -- mis. K001
  id_penyakit          INT UNSIGNED NOT NULL,          -- solusi / label kasus
  sumber               ENUM('pakar','retain','import') NOT NULL DEFAULT 'pakar',
  id_diagnosa_asal     INT UNSIGNED NULL,              -- terisi untuk kasus hasil Retain
  status               ENUM('aktif','nonaktif','arsip') NOT NULL DEFAULT 'aktif',
  frekuensi_digunakan  INT UNSIGNED NOT NULL DEFAULT 0,
  id_validator         INT UNSIGNED NULL,
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  validated_at         TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id_kasus),
  UNIQUE KEY uq_kasus_kode (kode_kasus),
  KEY idx_kasus_penyakit_status (id_penyakit, status),
  CONSTRAINT fk_kasus_penyakit  FOREIGN KEY (id_penyakit)      REFERENCES penyakit (id_penyakit),
  CONSTRAINT fk_kasus_diagnosa  FOREIGN KEY (id_diagnosa_asal) REFERENCES diagnosa (id_diagnosa) ON DELETE SET NULL,
  CONSTRAINT fk_kasus_validator FOREIGN KEY (id_validator)     REFERENCES users (id_user) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. detail_kasus : gejala dan nilai milik tiap kasus
--    nilai: 0-3 (biner dipakai 0/1)
-- ------------------------------------------------------------
CREATE TABLE detail_kasus (
  id_detail  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_kasus   INT UNSIGNED NOT NULL,
  id_gejala  INT UNSIGNED NOT NULL,
  nilai      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id_detail),
  UNIQUE KEY uq_detail_kasus (id_kasus, id_gejala),
  KEY idx_detail_kasus_gejala (id_gejala),
  CONSTRAINT fk_dk_kasus  FOREIGN KEY (id_kasus)  REFERENCES kasus (id_kasus) ON DELETE CASCADE,
  CONSTRAINT fk_dk_gejala FOREIGN KEY (id_gejala) REFERENCES gejala (id_gejala),
  CONSTRAINT chk_dk_nilai CHECK (nilai BETWEEN 0 AND 3)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. detail_diagnosa : gejala yang diinput pasien per sesi
-- ------------------------------------------------------------
CREATE TABLE detail_diagnosa (
  id_detail    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_diagnosa  INT UNSIGNED NOT NULL,
  id_gejala    INT UNSIGNED NOT NULL,
  nilai        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id_detail),
  UNIQUE KEY uq_detail_diagnosa (id_diagnosa, id_gejala),
  CONSTRAINT fk_dd_diagnosa FOREIGN KEY (id_diagnosa) REFERENCES diagnosa (id_diagnosa) ON DELETE CASCADE,
  CONSTRAINT fk_dd_gejala   FOREIGN KEY (id_gejala)   REFERENCES gejala (id_gejala),
  CONSTRAINT chk_dd_nilai CHECK (nilai BETWEEN 0 AND 3)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9. hasil_diagnosa : kandidat hasil Retrieve (top-K)
-- ------------------------------------------------------------
CREATE TABLE hasil_diagnosa (
  id_hasil     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_diagnosa  INT UNSIGNED NOT NULL,
  id_kasus     INT UNSIGNED NOT NULL,
  id_penyakit  INT UNSIGNED NOT NULL,
  similarity   DECIMAL(5,4) NOT NULL,
  peringkat    TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id_hasil),
  KEY idx_hasil_diagnosa_peringkat (id_diagnosa, peringkat),
  CONSTRAINT fk_hd_diagnosa FOREIGN KEY (id_diagnosa) REFERENCES diagnosa (id_diagnosa) ON DELETE CASCADE,
  CONSTRAINT fk_hd_kasus    FOREIGN KEY (id_kasus)    REFERENCES kasus (id_kasus),
  CONSTRAINT fk_hd_penyakit FOREIGN KEY (id_penyakit) REFERENCES penyakit (id_penyakit),
  CONSTRAINT chk_hd_sim CHECK (similarity BETWEEN 0 AND 1)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 10. validasi_kasus : keputusan pakar (Revise -> Retain)
-- ------------------------------------------------------------
CREATE TABLE validasi_kasus (
  id_validasi        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_diagnosa        INT UNSIGNED NOT NULL,
  id_pakar           INT UNSIGNED NOT NULL,
  keputusan          ENUM('terima','revisi','tolak') NOT NULL,
  id_penyakit_final  INT UNSIGNED NULL,                -- NULL bila ditolak
  solusi_revisi      TEXT NULL,
  catatan            TEXT NULL,                        -- wajib diisi saat 'tolak' (aturan di aplikasi)
  id_kasus_baru      INT UNSIGNED NULL,                -- kasus hasil Retain
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_validasi),
  UNIQUE KEY uq_validasi_diagnosa (id_diagnosa),
  CONSTRAINT fk_vk_diagnosa FOREIGN KEY (id_diagnosa)       REFERENCES diagnosa (id_diagnosa),
  CONSTRAINT fk_vk_pakar    FOREIGN KEY (id_pakar)          REFERENCES users (id_user),
  CONSTRAINT fk_vk_penyakit FOREIGN KEY (id_penyakit_final) REFERENCES penyakit (id_penyakit),
  CONSTRAINT fk_vk_kasus    FOREIGN KEY (id_kasus_baru)     REFERENCES kasus (id_kasus) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 11. pengaturan : parameter CBR
-- ------------------------------------------------------------
CREATE TABLE pengaturan (
  kunci  VARCHAR(50)  NOT NULL,
  nilai  VARCHAR(255) NOT NULL,
  PRIMARY KEY (kunci)
) ENGINE=InnoDB;

INSERT INTO pengaturan (kunci, nilai) VALUES
  ('threshold_reuse',     '0.80'),   -- >= nilai ini: Reuse
  ('threshold_revise',    '0.50'),   -- antara revise dan reuse: Revise; di bawahnya: kasus baru
  ('threshold_duplikat',  '0.98'),   -- cek duplikat saat Retain
  ('top_k',               '3'),
  ('metode_similarity',   'nearest_neighbor');  -- atau 'jaccard'

-- ------------------------------------------------------------
-- 12. log_aktivitas : audit trail
-- ------------------------------------------------------------
CREATE TABLE log_aktivitas (
  id_log      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_user     INT UNSIGNED NULL,
  aksi        VARCHAR(50) NOT NULL,
  tabel       VARCHAR(50) NULL,
  id_rekaman  INT UNSIGNED NULL,
  data_lama   JSON NULL,
  data_baru   JSON NULL,
  ip_address  VARCHAR(45) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_log),
  KEY idx_log_user_waktu (id_user, created_at),
  CONSTRAINT fk_log_user FOREIGN KEY (id_user) REFERENCES users (id_user) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Catatan: akun admin/pakar pertama dibuat lewat skrip PHP yang memakai password_hash(),
-- jangan menaruh hash atau kata sandi bawaan di berkas ini.
