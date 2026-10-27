<?php
// Library checks run by run-local.sh inside the slims container. Expects
// smtp-sink.php listening on 127.0.0.1:2525.
define('INDEX_AUTH', '1');
chdir('/var/www/html');
require '/var/www/html/sysconfig.inc.php';
$ok = 0; $fail = 0;
function t($name, $fn) { global $ok, $fail; try { $r = $fn(); echo ($r ? 'PASS' : 'FAIL') . "  $name" . (is_string($r) ? " ($r)" : '') . "\n"; $r ? $ok++ : $fail++; } catch (\Throwable $e) { echo "FAIL  $name: " . get_class($e) . ': ' . $e->getMessage() . "\n"; $fail++; } }

echo 'PHP ' . PHP_VERSION . ', SLiMS ' . SENAYAN_VERSION_TAG . "\n";
t('PhpSpreadsheet XLSX export', function () {
    $s = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $s->getActiveSheet()->fromArray([['Judul', 'Tahun'], ['Buku Uji', 2026]]);
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($s))->save('/tmp/t.xlsx');
    $z = new ZipArchive(); $z->open('/tmp/t.xlsx');
    return strpos($z->getFromName('xl/sharedStrings.xml'), 'Buku Uji') !== false ? \PhpOffice\PhpSpreadsheet\Shared\File::sysGetTempDir() : false;
});
t('PHPMailer SMTP send', function () {
    $m = new \PHPMailer\PHPMailer\PHPMailer(true);
    $m->isSMTP(); $m->Host = '127.0.0.1'; $m->Port = 2525; $m->SMTPAuth = false; $m->SMTPAutoTLS = false;
    $m->setFrom('lib@example.org', 'Perpustakaan'); $m->addAddress('anggota@example.org');
    $m->Subject = 'Uji'; $m->Body = 'Isi pesan uji';
    $m->send(); sleep(1);
    return strpos(@file_get_contents('/tmp/smtp.log'), 'Isi pesan uji') !== false ? \PHPMailer\PHPMailer\PHPMailer::VERSION : false;
});
t('PHPMailer language files', function () {
    $m = new \PHPMailer\PHPMailer\PHPMailer(); return $m->setLanguage('id');
});
t('Guzzle HTTP GET', function () {
    $r = (new \GuzzleHttp\Client(['timeout' => 10]))->get('http://127.0.0.1/index.php');
    return $r->getStatusCode() === 200 ? 'Guzzle ' . \GuzzleHttp\ClientInterface::MAJOR_VERSION . ', ' . strlen((string)$r->getBody()) . ' bytes' : false;
});
t('mPDF + FPDI import', function () {
    $a = new \Mpdf\Mpdf(['tempDir' => '/tmp']); $a->WriteHTML('<h1>Kartu Anggota</h1>'); $a->Output('/tmp/a.pdf', 'F');
    $b = new \Mpdf\Mpdf(['tempDir' => '/tmp']); $n = $b->setSourceFile('/tmp/a.pdf'); $b->useTemplate($b->importPage(1));
    $pdf = $b->Output('', 'S');
    return $n === 1 && strpos($pdf, '%PDF') === 0 ? 'pages=' . $n : false;
});
t('Zend barcode EAN-13 and Code128', function () {
    ini_set('include_path', LIB); require_once LIB . 'Zend/Barcode.php';
    foreach (['ean13' => '978979123456', 'code128' => 'B00001'] as $type => $text) {
        $img = Zend_Barcode::factory($type, 'image', ['text' => $text], [])->draw();
        if (!($img instanceof \GdImage)) return false;
    }
    return true;
});
t('Zend ISBN validator', function () {
    ini_set('include_path', LIB); require_once LIB . 'Zend/Validate/Isbn.php';
    $v = new Zend_Validate_Isbn(); return $v->isValid('9780596520687') && !$v->isValid('9780596520688') && $v->isValid('0596520689');
});
t('PhpSpreadsheet Complex autoload', function () { return class_exists(\Complex\Complex::class); });
echo "== $ok passed, $fail failed\n";
exit($fail);
