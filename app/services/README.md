# app/services/

Berisi logika mesin CBR (`CBREngine.php`) yang akan dibuat pada **Fase 3**
sesuai AGENTS.md: Retrieve, Reuse, Revise, Retain.

Aturan AGENTS.md:
- Logika CBR **tidak boleh** berada di controller atau view, supaya dapat
  diuji PHPUnit tanpa antarmuka (uji pertama ada di `tests/`).
- Ambang batas dibaca dari tabel `pengaturan`, tidak di-hardcode.
