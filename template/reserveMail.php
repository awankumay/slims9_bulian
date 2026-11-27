<?php
/**
 * @author Drajat Hasan
 * @email drajathasan20@gmail.com
 * @create date 2022-10-16 15:51:49
 * @modify date 2022-10-16 17:53:52
 * @license GPLv3
 * @desc [description]
 */

use SLiMS\DB;
use SLiMS\Mail\TemplateContract;

class reserveMail extends TemplateContract
{
    public function render()
    {
        $ids = array_map('intval', $_SESSION['m_mark_biblio']);
        if (count($ids) === 0) throw new Exception("Biblio basket is empty!");

        $biblioStatement = DB::getInstance()->query('SELECT biblio_id, title, image FROM biblio WHERE biblio_id IN (' . implode(',', $ids) . ')');
        if ($biblioStatement->rowCount() === 0) throw new Exception("Biblio data not found!");

        $items = '';
        while ($biblio = $biblioStatement->fetchObject()) {
            $items .= $this->itemCard($this->generateCoverUrl($biblio->image), $biblio->title, []);
        }

        $field = fn($label, $value) => '<td valign="top" width="50%" style="padding:0 16px 10px 0;font-size:14px;"><div style="font-size:12px;color:#55566A;">' . $label . '</div>' . $value . '</td>';
        $member = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E4E5EC;margin:0 0 16px 0;"><tr><td style="padding:16px 0 6px 16px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
            . '<tr>' . $field('Anggota', '<strong>' . $this->e($_SESSION['m_name']) . '</strong>') . $field('ID anggota', $this->e($_SESSION['mid'])) . '</tr>'
            . '<tr>' . $field('Email anggota', $this->e($_SESSION['m_email'])) . $field('Tanggal permintaan', date('Y-m-d H:i') . ' WIB') . '</tr>'
            . '</table></td></tr></table>';

        $body = $member
            . '<p style="margin:0 0 16px 0;">Koleksi yang direservasi:</p>'
            . $items
            . $this->note('Untuk anggota: salinan email ini adalah tanda terima. Petugas akan menghubungi Anda bila koleksi siap diambil.');

        $this->contents = $this->layout('Permintaan reservasi koleksi', $body, 'RESERVASI', 'info');

        return $this;
    }
}
