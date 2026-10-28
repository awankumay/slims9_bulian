<?php
$inSimbioRequest = $this->withSimbioAJAXRequest(outputWithHeader: false);

defined('SB') or define('SB', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);

$requestUrl = $_SERVER['PHP_SELF'] . (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

if ($inSimbioRequest === false):
    // halaman penuh dengan tampilan yang sama seperti /errorpage.php
    require_once dirname(__DIR__) . '/lib/errorpage.inc.php';
    $inAdminArea = (bool)preg_match('@/admin(/|$)@', $_SERVER['PHP_SELF'] ?? '');
    echo slimsErrorPage(500, [
        'detail' => $inAdminArea
            ? 'Detail kesalahan disembunyikan karena sistem berjalan dalam mode <strong>production</strong>. Untuk melihat detailnya, ubah <em>System Environment</em> menjadi <strong>development</strong> di modul System, atau periksa log error server.'
            : null,
    ]);
else:
?>
        <section class="container">
            <div class="row">
                <div class="p-5 m-5">
                    <div class="w-full p-5">
                        <div class="col-12">
                            <h1 style="font-size: 30pt">Error</h1>
                            <p style="font-size: 14pt">Please contact system admin or change <strong>system environment to development</strong> at system module for more information about this error.</p>
                            <div>
                                <strong>URL : </strong>
                                <span class="text-muted"><?= htmlspecialchars($requestUrl, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
<?php endif; ?>
