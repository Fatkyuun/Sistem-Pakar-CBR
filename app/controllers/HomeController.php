<?php
declare(strict_types=1);

/**
 * app/controllers/HomeController.php — beranda publik.
 * Formulir diagnosa sendiri baru di Fase 3 (kerjakan per fase, AGENTS.md).
 */
class HomeController extends Controller
{
    protected const ACTIONS = ['index'];

    public function indexAction(): void
    {
        $this->view('home/index', [
            'pageTitle'      => 'Beranda',
            'jumlahPenyakit' => (new Penyakit())->jumlahAktif(),
            'jumlahGejala'   => (new Gejala())->jumlahAktif(),
        ]);
    }
}
