-- DUMMY: bukan data medis
-- ============================================================
-- seed.sql — Sistem Pakar Penyakit Pernapasan (CBR)
--
-- SELURUH ISI BERKAS INI ADALAH DATA DUMMY untuk pengembangan dan
-- pengujian. Tidak boleh dipakai sebagai sumber pengetahuan medis
-- sungguhan (aturan data medis, AGENTS.md).
--
-- Isi: master penyakit (8) dan master gejala (18), semuanya versi
-- DUMMY. Kasus TIDAK diisi di sini (dikerjakan di Fase 2: CRUD kasus /
-- impor kasus awal). Tabel bobot_gejala_penyakit sengaja dibiarkan
-- kosong sampai ada keputusan bobot spesifik (bagian Keputusan terbuka
-- di AGENTS.md) — mesin CBR versi ini memakai bobot global
-- gejala.bobot pada rentang 1-10.
--
-- Pemakaian:
--   php database/setup_init.php      (cara yang disarankan)
--   atau: mysql -u root sistem_pakar_cbr < database/seed.sql
-- ============================================================

USE sistem_pakar_cbr;

-- DUMMY: bukan data medis — master penyakit (8 baris)
INSERT INTO penyakit (kode_penyakit, nama_penyakit, deskripsi, penyebab, solusi, pencegahan, tingkat_urgensi, is_active) VALUES
  ('P01','Common Cold (Pilek)',
   'Infeksi saluran pernapasan atas yang sangat umum terjadi dan biasanya bersifat ringan. Kondisi ini terutama menyerang hidung dan tenggorokan, ditandai dengan bersin-bersin, hidung tersumbat atau berair, serta rasa tidak nyaman pada tenggorokan.',
   'Disebabkan oleh infeksi virus, paling sering Rhinovirus. Virus menyebar dengan mudah melalui percikan udara saat orang yang terinfeksi batuk atau bersin, maupun kontak langsung dengan permukaan benda yang terkontaminasi.',
   'Istirahat yang cukup di rumah, perbanyak minum air putih hangat untuk mengencerkan lendir, dan konsumsi makanan bergizi. Penggunaan obat bebas seperti dekongestan atau parasetamol dapat membantu meredakan hidung tersumbat dan rasa nyeri ringan. Segera konsultasikan ke dokter apabila gejala tidak membaik lebih dari 10 hari atau disertai demam tinggi menetap.',
   'Rutin mencuci tangan menggunakan sabun dan air mengalir, hindari menyentuh area wajah dengan tangan yang belum dicuci, gunakan masker saat berada di keramaian atau saat sedang flu, serta jaga daya tahan tubuh dengan tidur cukup dan pola makan seimbang.',
   'ringan',1),

  ('P02','Influenza (Flu)',
   'Penyakit infeksi saluran pernapasan akut yang lebih berat daripada pilek biasa. Flu sering kali datang secara mendadak dengan gejala demam tinggi, menggigil, nyeri otot dan sendi di seluruh tubuh, sakit kepala, rasa sangat lelah, serta batuk kering.',
   'Disebabkan oleh infeksi virus Influenza (terutama tipe A dan B) yang menyerang hidung, tenggorokan, dan terkadang paru-paru. Virus ini mudah menular melalui percikan air liur (droplet) saat penderita batuk, bersin, atau berbicara.',
   'Istirahat total (bed rest) dan hindari aktivitas fisik yang berat. Cukupi kebutuhan cairan tubuh dengan minum air putih atau sup hangat. Konsumsi obat penurun panas dan pereda nyeri jika diperlukan. Bila Anda memiliki faktor risiko tinggi (lansia, anak-anak, ibu hamil, atau memiliki penyakit penyerta) atau mengalami sesak napas, segera periksakan diri ke dokter.',
   'Melakukan vaksinasi influenza tahunan, menghindari kontak dekat dengan orang yang sedang sakit flu, menjaga kebersihan tangan, menerapkan etika batuk dan bersin dengan menutup mulut dan hidung, serta menjaga ventilasi ruangan tetap baik.',
   'waspada',1),

  ('P03','Faringitis',
   'Peradangan pada bagian belakang tenggorokan (faring) yang umumnya menimbulkan rasa gatal, kering, panas, dan nyeri terutama saat menelan makanan atau minuman.',
   'Sebagian besar kasus disebabkan oleh infeksi virus (seperti virus penyebab pilek atau flu), namun sebagian kecil dapat disebabkan oleh infeksi bakteri (seperti Streptococcus). Faktor lain meliputi paparan udara kering, asap rokok, polusi, atau reaksi alergi.',
   'Berkumur dengan larutan air garam hangat beberapa kali sehari untuk mengurangi pembengkakan tenggorokan. Perbanyak minum cairan hangat dan konsumsi makanan bertekstur lembut. Hindari minuman dingin, makanan pedas, berminyak, atau asam untuk sementara waktu. Jika sakit tenggorokan berlangsung lebih dari seminggu, disertai demam tinggi atau sulit menelan ludah, segera periksakan ke dokter.',
   'Tidak berbagi peralatan makan atau minum dengan orang lain, rutin mencuci tangan, menghindari paparan asap rokok dan polutan udara, serta segera mengganti sikat gigi setelah sembuh dari radang tenggorokan.',
   'ringan',1),

  ('P04','Bronkitis Akut',
   'Peradangan jangka pendek pada lapisan saluran bronkus (saluran udara utama yang membawa udara masuk dan keluar dari paru-paru). Kondisi ini ditandai dengan batuk yang sering kali berdahak (bening, kuning, atau kehijauan), rasa tidak nyaman di dada, dan sedikit sesak napas.',
   'Mayoritas dipicu oleh infeksi virus yang sama dengan virus penyebab pilek atau flu yang menyebar ke saluran napas bawah. Paparan asap rokok, debu, uap kimia, atau polusi udara juga dapat memperburuk dan memicu peradangan.',
   'Perbanyak istirahat dan konsumsi cairan hangat guna membantu mengencerkan dahak sehingga lebih mudah dikeluarkan. Gunakan pelembap udara (humidifier) jika diperlukan. Hindari obat penekan batuk tanpa anjuran dokter agar dahak tidak menumpuk. Segera konsultasikan ke dokter jika batuk berlanjut lebih dari 3 minggu, disertai dahak bercampur darah, atau terjadi demam tinggi dan napas pendek.',
   'Berhenti merokok dan hindari menjadi perokok pasif, gunakan masker saat berada di lingkungan berdebu atau berpolusi tinggi, dapatkan vaksinasi flu dan pneumonia sesuai anjuran medis, serta rawat pilek atau flu hingga tuntas.',
   'waspada',1),

  ('P05','Asma',
   'Penyakit pernapasan kronis jangka panjang yang menyebabkan saluran pernapasan meradang, membengkak, dan menyempit serta memproduksi lendir berlebih. Kondisi ini menimbulkan episode sesak napas berulang, dada terasa sesak atau terikat, batuk (terutama malam atau pagi hari), dan suara napas bersiul (mengi/wheezing).',
   'Dipengaruhi oleh kombinasi faktor genetik (riwayat alergi atau asma dalam keluarga) dan paparan pemicu lingkungan, seperti debu, bulu hewan, serbuk sari bunga, asap rokok, udara dingin, stres, atau infeksi saluran napas.',
   'Segera gunakan obat pereda (inhaler pelega/reliever) sesuai rencana aksi asma yang telah ditentukan dokter. Duduk dengan posisi tegak, jangan berbaring, dan usahakan bernapas perlahan dengan tenang. Hindari faktor pemicu yang sedang dihadapi. Jika serangan sesak tidak kunjung membaik setelah penggunaan inhaler atau bibir mulai membiru, segera cari pertolongan medis darurat ke IGD.',
   'Kenali dan catat pemicu alergi atau kekambuhan asma pribadi, lalu hindari sebisa mungkin. Gunakan obat pengendali (controller) secara teratur sesuai resep dokter meski merasa sedang sehat. Bersihkan kasur dan ruangan dari debu tungau, hindari asap rokok, dan lakukan pemanasan sebelum berolahraga.',
   'segera',1),

  ('P06','Pneumonia',
   'Infeksi akut yang menyebabkan kantung-kantung udara kecil di salah satu atau kedua paru-paru (alveoli) meradang dan terisi cairan atau nanah. Hal ini menyebabkan pertukaran oksigen terganggu, ditandai dengan demam tinggi, menggigil, napas cepat dan dangkal, nyeri dada saat menarik napas atau batuk, serta batuk berdahak kental.',
   'Disebabkan oleh infeksi mikroorganisme, paling umum bakteri (seperti Streptococcus pneumoniae), virus pernapasan, atau jamur. Penularan terjadi melalui inhalasi droplet udara atau aspirasi kuman dari saluran napas atas ke paru-paru.',
   'Kondisi ini memerlukan pemeriksaan medis langsung oleh dokter. Pasien memerlukan pengobatan antibiotik terarah (jika disebabkan bakteri) atau terapi antivirus sesuai resep dokter. Istirahat cukup, terapi oksigen jika kadar oksigen rendah, serta pemantauan ketat. Jangan menunda ke rumah sakit jika muncul tanda darurat seperti sesak napas berat, kebingungan, atau bibir kebiruan.',
   'Mendapatkan vaksinasi pneumonia (PCV/PPSV) dan vaksin flu rutin, berhenti merokok karena merusak pertahanan alami paru-paru, menjaga kebersihan diri dan tangan, serta menjaga sistem kekebalan tubuh dengan nutrisi yang baik.',
   'segera',1),

  ('P07','TBC Paru (Tuberkulosis)',
   'Penyakit infeksi bakteri menular kronis yang utamanya menyerang jaringan paru-paru, namun dapat menyebar ke organ tubuh lain. Ciri khasnya adalah batuk terus-menerus yang berlangsung lebih dari 2 minggu, terkadang batuk bercampur darah, keringat dingin di malam hari tanpa aktivitas fisik, penurunan berat badan secara drastis, nafsu makan berkurang, dan demam meriang yang hilang timbul.',
   'Disebabkan oleh infeksi bakteri Mycobacterium tuberculosis. Kuman menyebar melalui udara ketika penderita TBC paru aktif batuk, bersin, meludah, atau berbicara tanpa menutup mulut.',
   'Wajib segera melakukan pemeriksaan dahak (TCM/BTA) dan rontgen dada di puskesmas atau rumah sakit. Jika terkonfirmasi, pasien harus menjalani pengobatan Obat Anti Tuberkulosis (OAT) secara tuntas dan teratur selama minimal 6 bulan di bawah pengawasan tenaga kesehatan. Pengobatan tidak boleh dihentikan sepihak agar bakteri tidak menjadi kebal obat (TBC-RO).',
   'Pemberian vaksin BCG pada bayi baru lahir, memastikan sirkulasi udara dan sinar matahari cukup masuk ke dalam rumah, menerapkan etika batuk yang benar dan menggunakan masker bagi penderita, serta melakukan skrining kontak serumah jika ada anggota keluarga yang terdiagnosis TBC.',
   'segera',1),

  ('P08','PPOK',
   'Penyakit pernapasan progresif jangka panjang yang ditandai dengan hambatan aliran udara di saluran napas yang tidak sepenuhnya reversibel (tidak dapat kembali normal sepenuhnya). Gejala berkembang bertahap meliputi sesak napas menahun yang memburuk saat beraktivitas fisik, batuk kronis berdahak berkepanjangan, rasa berat di dada, serta mudah lelah.',
   'Penyebab utama (sekitar 80-90% kasus) adalah kebiasaan merokok jangka panjang (rokok tembakau atau rokok elektrik). Faktor penyebab lainnya meliputi paparan debu kerja, asap pembakaran biomassa (kayu bakar), dan polusi udara di tempat tinggal atau tempat kerja.',
   'Segera periksakan diri ke dokter spesialis paru untuk pemeriksaan fungsi paru (spirometri). Langkah paling krusial adalah berhenti merokok total guna memperlambat kerusakan paru lebih lanjut. Terapi meliputi obat hirup (bronkodilator dan kortikosteroid) untuk melegakan saluran napas, rehabilitasi paru melalui latihan pernapasan, serta terapi oksigen jika terjadi penurunan saturasi.',
   'Berhenti merokok sepenuhnya dan hindari paparan asap rokok orang lain (perokok pasif), gunakan alat pelindung pernapasan (masker/respirator) di lingkungan kerja yang berdebu atau berpapar zat kimia, perbaiki ventilasi dapur dan rumah, serta lakukan vaksinasi tahunan flu dan pneumonia untuk mencegah eksaserbasi (perburukan mendadak).',
   'segera',1);

-- DUMMY: bukan data medis — master gejala (18 baris)
-- Bobot adalah angka DUMMY pada rentang 1-10. is_redflag menandai gejala
-- darurat yang wajib munculkan peringatan segera ke IGD.
INSERT INTO gejala (kode_gejala, nama_gejala, deskripsi, kategori, tipe_input, bobot, is_redflag, is_active) VALUES
  ('G01','Demam','Panas badan dari ringan sampai berat (0 tidak, 1 ringan, 2 sedang, 3 berat).','Gejala Umum','skala',6.00,0,1),
  ('G02','Menggigil','Tubuh bergetar merinding tanpa terkendali.','Gejala Umum','biner',3.00,0,1),
  ('G03','Mudah Lelah','Badan lemas sekali melakukan aktivitas biasa.','Gejala Umum','biner',3.00,0,1),
  ('G04','Keringat Malam','Berkeringat berlimpah saat tidur meskipun udara tidak panas.','Gejala Umum','biner',5.00,0,1),
  ('G05','Pilek (Cair Bening)','Cairan mengalir keluar dari hidung.','Hidung dan Tenggorokan','biner',3.00,0,1),
  ('G06','Hidung Tersumbat','Hidung macet sehingga napas terasa tersendat.','Hidung dan Tenggorokan','biner',2.00,0,1),
  ('G07','Bersin-Bersin','Bersin berulang beberapa kali dalam sehari.','Hidung dan Tenggorokan','biner',2.00,0,1),
  ('G08','Sakit Tenggorokan','Tenggorokan gatal, garau, sampai perih saat menelan (0 tidak, 1 ringan, 2 sedang, 3 berat).','Hidung dan Tenggorokan','skala',4.00,0,1),
  ('G09','Suara Serak','Suara berubah kasar atau hilang sementara.','Hidung dan Tenggorokan','biner',3.00,0,1),
  ('G10','Batuk','Lama batuk (0 tidak, 1 kurang dari 3 hari, 2 sampai 7 hari, 3 lebih dari 7 hari).','Batuk','durasi',8.00,0,1),
  ('G11','Batuk Berdahak','Saat batuk keluar dahak berwarna kuning atau hijau.','Batuk','biner',6.00,0,1),
  ('G12','Batuk Darah','Darah keluar bersama batuk. TERMASUK GEJALA DARURAT.','Batuk','biner',10.00,1,1),
  ('G13','Batuk Kering','Batuk tanpa dahak dan terasa mengganjal.','Batuk','biner',4.00,0,1),
  ('G14','Sesak Napas Saat Beraktivitas','Saat berjalan atau menaiki tangga terasa kehabisan napas (0 tidak, 1 ringan, 2 sedang, 3 berat).','Sesak Napas','skala',9.00,0,1),
  ('G15','Sesak Napas Saat Istirahat','Sesak napas terjadi meski sedang duduk atau berbaring (0 tidak hingga 3 berat). TERMASUK GEJALA DARURAT.','Sesak Napas','skala',10.00,1,1),
  ('G16','Napas Mengi (Wheezing)','Terlubang terdengar suara bersiir tipis saat melepas napas.','Sesak Napas','biner',7.00,0,1),
  ('G17','Bibir atau Ujung Jari Kebiruan','Bibir atau ujung jari tampak kebiruan. TERMASUK GEJALA DARURAT.','Sesak Napas','biner',10.00,1,1),
  ('G18','Nyeri Dada','Dada terasa sakit atau berdenyut (0 tidak, 1 ringan, 2 sedang, 3 berat). Gejala berat TERMASUK DARURAT.','Nyeri','skala',9.00,1,1);

-- DUMMY: bukan data medis — selesai seed Fase 1
-- Tabel berikut sengaja TIDAK diisi:
--   bobot_gejala_penyakit (menunggu keputusan AGENTS.md bagian terbuka)
--   kasus, detail_kasus, diagnosa, detail_diagnosa, hasil_diagnosa,
--   validasi_kasus (Fase 2-4), sedangkan akun Admin dan Pakar pertama
--   dibuat lewat database/setup_init.php agar memakai password_hash().
