<?php
/**
 * Fungsi bersama tema kartu bulian-kartu dan bulian-kartu-potret:
 * escape teks setelan, validasi warna/angka, serta barcode Code128 dan QR
 * sebagai inline SVG.
 */

if (!function_exists('bk_card_text')) {
  // nilai setelan disimpan dengan htmlentities(); kembalikan lalu escape ulang
  function bk_card_text($value)
  {
    return htmlspecialchars(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
  }

  function bk_card_color($value, $default)
  {
    return preg_match('/^#[0-9a-fA-F]{6}$/', trim((string)$value)) ? trim($value) : $default;
  }

  function bk_card_number($value, $default)
  {
    $value = (float)str_replace(',', '.', (string)$value);
    return $value > 0 ? $value : $default;
  }

  // [lebar, tinggi] dalam cm. Setelan kartu dipakai bersama semua tema, jadi
  // ukuran dari tema lain (lanskap/potret) dibalik sesuai orientasi tema ini.
  function bk_card_size($conf, $portrait)
  {
    $w = bk_card_number($conf['box_width'] ?? 0, $portrait ? 5.4 : 8.56);
    $h = bk_card_number($conf['box_height'] ?? 0, $portrait ? 8.56 : 5.4);
    return ($portrait xor $h > $w) ? [$h, $w] : [$w, $h];
  }

  // inline SVG Code128; null bila Zend_Barcode tidak tersedia
  function bk_card_barcode($code)
  {
    static $loaded = null;
    if ($loaded === null) {
      $loaded = false;
      if (is_file(LIB . 'Zend/Barcode.php')) {
        set_include_path(LIB . PATH_SEPARATOR . get_include_path());
        require_once LIB . 'Zend/Barcode.php';
        $loaded = true;
      }
    }
    if (!$loaded) return null;
    try {
      $doc = Zend_Barcode::factory('code128', 'svg', ['text' => $code, 'drawText' => false, 'barHeight' => 40, 'factor' => 1], ['topOffset' => 0, 'leftOffset' => 0])->draw();
      $svg = $doc->documentElement;
      $w = $svg->getAttribute('width');
      $h = $svg->getAttribute('height');
      $svg->setAttribute('viewBox', "0 0 $w $h");
      $svg->setAttribute('preserveAspectRatio', 'none');
      $svg->setAttribute('role', 'img');
      $svg->setAttribute('aria-label', 'Barcode ' . $code);
      $svg->removeAttribute('width');
      $svg->removeAttribute('height');
      return $doc->saveXML($svg);
    } catch (\Throwable $e) {
      return null;
    }
  }

  // inline SVG QR berisi nomor anggota polos (dibaca kiosk buku tamu)
  function bk_card_qr($code)
  {
    if (!class_exists('BaconQrCode\\Writer')) return null;
    try {
      $renderer = new BaconQrCode\Renderer\ImageRenderer(
        new BaconQrCode\Renderer\RendererStyle\RendererStyle(200, 1),
        new BaconQrCode\Renderer\Image\SvgImageBackEnd()
      );
      $svg = (new BaconQrCode\Writer($renderer))->writeString($code);
      return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    } catch (\Throwable $e) {
      return null;
    }
  }
}
