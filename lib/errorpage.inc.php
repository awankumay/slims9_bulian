<?php
/**
 * Halaman error mandiri (404, 403, 500, dll.) untuk SLiMS 9 Bulian.
 *
 * Dipakai oleh /errorpage.php (ErrorDocument Apache / error_page Nginx) dan
 * template/errorproduction.php. Sengaja tidak memuat sysconfig.inc.php atau
 * database, supaya tetap tampil saat aplikasi atau database bermasalah.
 * Warna mengikuti tema OPAC bulian-kampus.
 */

defined('SLIMS_ERROR_PAGE') or define('SLIMS_ERROR_PAGE', 1);

if (!function_exists('slimsErrorPageInfo')) {
    /**
     * Judul dan penjelasan untuk tiap kode status.
     *
     * @param int $code
     * @return array [judul, penjelasan, judul inggris]
     */
    function slimsErrorPageInfo(int $code): array
    {
        $map = [
            400 => ['Permintaan tidak valid', 'Alamat atau data yang dikirim tidak dapat diproses. Periksa kembali tautan yang Anda buka.', 'Bad Request'],
            401 => ['Perlu masuk terlebih dahulu', 'Halaman ini hanya dapat dibuka setelah Anda masuk (login).', 'Unauthorized'],
            403 => ['Akses ditolak', 'Anda tidak memiliki izin untuk membuka halaman atau berkas ini.', 'Forbidden'],
            404 => ['Halaman tidak ditemukan', 'Alamat yang Anda buka tidak tersedia. Mungkin tautannya salah ketik, sudah dipindahkan, atau sudah dihapus.', 'Not Found'],
            405 => ['Metode tidak diizinkan', 'Cara permintaan ini dikirim tidak didukung oleh halaman tersebut.', 'Method Not Allowed'],
            408 => ['Waktu permintaan habis', 'Server terlalu lama menunggu permintaan Anda. Silakan coba lagi.', 'Request Timeout'],
            410 => ['Halaman sudah tidak tersedia', 'Halaman ini sudah dihapus secara permanen.', 'Gone'],
            413 => ['Berkas terlalu besar', 'Ukuran berkas atau data yang dikirim melebihi batas yang diizinkan server.', 'Payload Too Large'],
            414 => ['Alamat terlalu panjang', 'Alamat (URL) yang dikirim terlalu panjang untuk diproses.', 'URI Too Long'],
            429 => ['Terlalu banyak permintaan', 'Anda mengirim terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar lalu coba lagi.', 'Too Many Requests'],
            500 => ['Terjadi kesalahan pada server', 'Sistem mengalami kendala saat memproses permintaan Anda. Silakan coba lagi beberapa saat lagi atau hubungi petugas perpustakaan.', 'Internal Server Error'],
            502 => ['Gateway bermasalah', 'Server perantara menerima jawaban yang tidak valid. Silakan coba lagi beberapa saat lagi.', 'Bad Gateway'],
            503 => ['Layanan sedang tidak tersedia', 'Sistem sedang dalam pemeliharaan atau sibuk. Silakan coba lagi beberapa saat lagi.', 'Service Unavailable'],
            504 => ['Waktu tunggu gateway habis', 'Server terlalu lama merespons. Silakan coba lagi beberapa saat lagi.', 'Gateway Timeout'],
        ];

        return $map[$code] ?? $map[$code >= 500 ? 500 : 400];
    }

    /**
     * Folder SLiMS relatif terhadap document root, berakhiran "/".
     * Cadangan dari SCRIPT_NAME bila document root tidak bisa dibaca.
     */
    function slimsErrorPageBase(): string
    {
        // utamakan posisi folder SLiMS terhadap document root
        $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $slims = realpath(dirname(__DIR__));
        if ($root && $slims && strpos($slims . '/', rtrim($root, '/') . '/') === 0) {
            $dir = str_replace('\\', '/', substr($slims, strlen(rtrim($root, '/'))));
            return htmlspecialchars(rtrim($dir, '/') . '/', ENT_QUOTES, 'UTF-8');
        }

        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
        $dir = rtrim(dirname($script), '/');
        $dir = preg_replace('@/admin(/.*)?$@', '', $dir);
        return htmlspecialchars($dir . '/', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Alamat yang diminta pengguna (tanpa query string).
     */
    function slimsErrorPageRequestPath(): string
    {
        $uri = $_SERVER['REDIRECT_URL'] ?? ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string)parse_url($uri, PHP_URL_PATH);
        if (strlen($path) > 160) $path = substr($path, 0, 157) . '...';
        return $path;
    }

    /**
     * Tampilkan halaman error lengkap.
     *
     * @param int $code kode status HTTP
     * @param array $opt detail (string|null), show_path (bool), send_status (bool)
     * @return string HTML
     */
    function slimsErrorPage(int $code, array $opt = []): string
    {
        [$title, $message, $english] = slimsErrorPageInfo($code);
        $base = slimsErrorPageBase();
        $path = slimsErrorPageRequestPath();
        $inAdmin = (bool)preg_match('@/admin(/|$)@', $path);
        $detail = $opt['detail'] ?? null;
        $showPath = $opt['show_path'] ?? true;
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $font = $base . 'template/bulian-kampus/assets/fonts/';

        ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $code ?> · <?= $e($title) ?></title>
<style>
@font-face{font-family:'Saira';src:url('<?= $font ?>saira-var.woff2') format('woff2');font-weight:100 900;font-display:swap}
@font-face{font-family:'Lato';src:url('<?= $font ?>lato-400.woff2') format('woff2');font-weight:400;font-display:swap}
@font-face{font-family:'Lato';src:url('<?= $font ?>lato-700.woff2') format('woff2');font-weight:700;font-display:swap}
:root{--bk-primary:#0067B8;--bk-accent:#F9DA00;--bk-ink:#12202F;--bk-navy:#0A2A4A;--bk-ground:#F5F7FA;--bk-card:#FFFFFF;--bk-line:#D8DEE6;--bk-muted:#4A5568;--bk-on-dark:#DCE3EC}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%}
body{min-height:100vh;display:flex;flex-direction:column;background:var(--bk-ground);color:var(--bk-ink);font:16px/1.6 'Lato',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif}
.bar{height:6px;background:linear-gradient(90deg,var(--bk-navy) 0 70%,var(--bk-accent) 70% 100%)}
main{flex:1;display:flex;align-items:center;justify-content:center;padding:32px 16px}
.card{width:100%;max-width:560px;background:var(--bk-card);border:1px solid var(--bk-line);border-radius:8px;padding:40px 32px;box-shadow:0 8px 24px rgba(10,42,74,.08)}
.code{font-family:'Saira','Lato',system-ui,sans-serif;font-weight:800;font-size:72px;line-height:1;color:var(--bk-primary);letter-spacing:-1px;margin:0}
.code span{display:inline-block;margin-left:12px;padding:4px 10px;border-radius:999px;background:var(--bk-accent);color:var(--bk-navy);font-size:13px;font-weight:700;letter-spacing:.5px;vertical-align:middle;text-transform:uppercase}
h1{font-family:'Saira','Lato',system-ui,sans-serif;font-size:26px;line-height:1.25;margin:20px 0 8px;color:var(--bk-navy)}
p{margin:0 0 12px;color:var(--bk-muted)}
.path{margin:16px 0 0;padding:10px 12px;border-radius:6px;background:var(--bk-ground);border:1px dashed var(--bk-line);font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:var(--bk-ink);word-break:break-all}
.detail{font-size:14px}
.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:28px}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;border-radius:8px;font-weight:700;font-size:15px;text-decoration:none;border:2px solid var(--bk-primary);cursor:pointer;font-family:inherit}
.btn-primary{background:var(--bk-primary);color:#fff}
.btn-primary:hover{background:var(--bk-navy);border-color:var(--bk-navy)}
.btn-ghost{background:transparent;color:var(--bk-primary)}
.btn-ghost:hover{background:var(--bk-ground)}
.btn:focus-visible{outline:3px solid var(--bk-accent);outline-offset:2px}
footer{padding:16px;text-align:center;font-size:13px;color:var(--bk-muted)}
@media (max-width:480px){.card{padding:28px 20px}.code{font-size:56px}h1{font-size:22px}.btn{flex:1 1 100%}}
@media (prefers-color-scheme:dark){:root{--bk-ink:#E8EEF5;--bk-navy:#E8EEF5;--bk-ground:#0F1B2A;--bk-card:#16263A;--bk-line:#2A3D55;--bk-muted:#B4C0CE;--bk-primary:#4DA3FF}.btn-primary{color:#0F1B2A}.btn-primary:hover{background:#E8EEF5;border-color:#E8EEF5}.code span{color:#0A2A4A}.bar{background:linear-gradient(90deg,#0067B8 0 70%,var(--bk-accent) 70% 100%)}}
</style>
</head>
<body>
<div class="bar" aria-hidden="true"></div>
<main>
  <div class="card" role="alert">
    <p class="code"><?= $code ?><span><?= $e($english) ?></span></p>
    <h1><?= $e($title) ?></h1>
    <p><?= $e($message) ?></p>
<?php if ($detail): ?>
    <p class="detail"><?= $detail ?></p>
<?php endif; ?>
<?php if ($showPath && $path !== ''): ?>
    <div class="path" aria-label="Alamat yang diminta"><?= $e($path) ?></div>
<?php endif; ?>
    <div class="actions">
<?php if ($inAdmin): ?>
      <a class="btn btn-primary" href="<?= $base ?>admin/">Ke halaman admin</a>
      <a class="btn btn-ghost" href="<?= $base ?>">Ke beranda OPAC</a>
<?php else: ?>
      <a class="btn btn-primary" href="<?= $base ?>">Ke beranda</a>
      <a class="btn btn-ghost" href="<?= $base ?>index.php?keywords=&amp;search=search">Cari koleksi</a>
<?php endif; ?>
      <button type="button" class="btn btn-ghost" onclick="history.back()">Kembali</button>
    </div>
  </div>
</main>
<footer>Kode <?= $code ?> · Jika masalah berlanjut, hubungi petugas perpustakaan.</footer>
</body>
</html>
<?php
        return ob_get_clean();
    }
}
