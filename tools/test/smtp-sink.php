<?php
// Minimal SMTP sink for lib-check.php: accepts one message and writes it to /tmp/smtp.log
$srv = stream_socket_server('tcp://127.0.0.1:2525', $e, $es);
$c = stream_socket_accept($srv, 30);
$w = function ($s) use ($c) { fwrite($c, $s . "\r\n"); };
$w('220 test ESMTP'); $log = ''; $data = false;
while (($l = fgets($c)) !== false) {
    $log .= $l;
    if ($data) { if (rtrim($l) === '.') { $data = false; $w('250 OK queued'); } continue; }
    $cmd = strtoupper(substr(trim($l), 0, 4));
    if ($cmd === 'EHLO') { fwrite($c, "250-test\r\n250 HELP\r\n"); }
    elseif ($cmd === 'DATA') { $data = true; $w('354 go'); }
    elseif ($cmd === 'QUIT') { $w('221 bye'); break; }
    else { $w('250 OK'); }
}
file_put_contents('/tmp/smtp.log', $log);
