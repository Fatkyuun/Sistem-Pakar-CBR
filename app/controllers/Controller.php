<?php
declare(strict_types=1);

/**
 * app/controllers/Controller.php — kelas dasar seluruh controller.
 *
 * Tugasnya kecil dan seragam: memutasi aksi ke metode {aksi}Action,
 * menyajikan view, dan menyediakan halaman galat 403/404 yang seragam.
 * Logika CBR tetap di app/services/ sesuai AGENTS.md.
 */

abstract class Controller
{
    /** Daftar aksi yang boleh dipanggil; dipetakan ke metode {aksi}Action. */
    protected const ACTIONS = ['index'];

    /** Jalankan satu aksi setelah memastikan aksinya halal. */
    public function dispatch(string $action): void
    {
        if (!in_array($action, static::ACTIONS, true) || !method_exists($this, $action . 'Action')) {
            respond_not_found();
        }
        $this->{$action . 'Action'}();
    }

    /** Render satu view dari app/views/. Data diektrak sebagai variabel. */
    protected function view(string $file, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require BASE_PATH . '/app/views/' . $file . '.php';
    }

    /**
     * Ambil nilai teks dari $_POST/$_GET dengan aman.
     *
     * Nilai non-skalar (mis. `usia[]=1`) tidak pernah di-cast paksa menjadi
     * "Array"; nilainya dianggap tidak ada sehingga validasi biasa yang
     * menolaknya, tanpa memicu warning PHP.
     */
    protected static function inputTeks(array $sumber, string $kunci, string $bawaan = ''): string
    {
        $nilai = $sumber[$kunci] ?? null;
        return is_scalar($nilai) ? (string) $nilai : $bawaan;
    }
}
