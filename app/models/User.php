<?php
declare(strict_types=1);

/**
 * app/models/User.php — akses tabel users.
 * Semua query memakai PDO prepared statements (AGENTS.md).
 * Kata sandi selalu di-hash dengan password_hash().
 */
class User
{
    /** Cari pengguna berdasarkan email (case-insensitive di MySQL). */
    public function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Email sudah dipakai pengguna lain? */
    public function emailExists(string $email, int $kecualiId = 0): bool
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id_user <> ?');
        $stmt->execute([$email, $kecualiId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Buat akun baru. $data kunci: nama, email, password_hash, role,
     * serta opsional tanggal_lahir, jenis_kelamin, is_active.
     * Role harus salah satu dari ENUM pada schema (pasien|pakar|admin).
     */
    public function create(array $data): int
    {
        $role = in_array($data['role'] ?? '', ['pasien', 'pakar', 'admin'], true)
            ? $data['role'] : 'pasien';

        $stmt = db()->prepare(
            'INSERT INTO users (nama, email, password_hash, role, tanggal_lahir, jenis_kelamin, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (string) ($data['nama'] ?? ''),
            (string) ($data['email'] ?? ''),
            (string) ($data['password_hash'] ?? ''),
            $role,
            $data['tanggal_lahir'] ?? null,
            $data['jenis_kelamin'] ?? null,
            (int) ($data['is_active'] ?? 1),
        ]);
        return (int) db()->lastInsertId();
    }

    /** Ubah kata sandi (hash baru). */
    public function updatePassword(int $idUser, string $passwordHash): void
    {
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id_user = ?');
        $stmt->execute([$passwordHash, $idUser]);
    }

    /* ============================================================
       MANAJEMEN PENGGUNA (khusus admin)
       ============================================================ */

    /** Semua pengguna (opsional filter peran), urut peran lalu nama. */
    public function list(?string $role = null): array
    {
        $sql = 'SELECT id_user, nama, email, role, is_active, created_at
                FROM users';
        $params = [];
        if ($role !== null && in_array($role, ['pasien', 'pakar', 'admin'], true)) {
            $sql .= ' WHERE role = ?';
            $params[] = $role;
        }
        $sql .= ' ORDER BY FIELD(role, "admin", "pakar", "pasien"), nama ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $idUser): ?array
    {
        $stmt = db()->prepare(
            'SELECT id_user, nama, email, role, is_active, created_at
             FROM users WHERE id_user = ?'
        );
        $stmt->execute([$idUser]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Aktif/nonaktifkan akun (admin tidak boleh menonaktifkan dirinya sendiri). */
    public function setActive(int $idUser, bool $aktif): void
    {
        $stmt = db()->prepare('UPDATE users SET is_active = ? WHERE id_user = ?');
        $stmt->execute([$aktif ? 1 : 0, $idUser]);
    }

    /** Jumlah pengguna per peran (kartu ringkas manajemen pengguna). */
    public function statistikPeran(): array
    {
        $hasil = ['pasien' => 0, 'pakar' => 0, 'admin' => 0, 'nonaktif' => 0];
        foreach (db()->query('SELECT role, is_active, COUNT(*) AS jumlah FROM users GROUP BY role, is_active') as $row) {
            $hasil[(string) $row['role']] = ($hasil[(string) $row['role']] ?? 0) + (int) $row['jumlah'];
            if ((int) $row['is_active'] !== 1) {
                $hasil['nonaktif'] += (int) $row['jumlah'];
            }
        }
        return $hasil;
    }
}
