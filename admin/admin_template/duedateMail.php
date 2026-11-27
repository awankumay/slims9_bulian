<?php
/**
 * @author Drajat Hasan
 * @email drajathasan20@gmail.com
 * @license GPLv3
 * @desc Due date warning e-mail template
 */

use SLiMS\Mail\TemplateContract;

class duedateMail extends TemplateContract
{
    private $member = [];
    private $duedateData = [];

    public function __construct($member)
    {
        $this->member = $member;
    }

    public function setCirculationData($duedateLoan)
    {
        $this->duedateData = $duedateLoan;
    }

    /**
     * Mail content output process
     *
     * @return duedateMail
     */
    public function render()
    {
        $items = '';
        foreach ($this->duedateData as $loan) {
            // overdue_days here is days left until the due date (0-3)
            $daysLeft = (int) $loan->overdue_days;
            $items .= $this->itemCard($this->generateCoverUrl($loan->image), $loan->title, [
                'Kode eksemplar: ' . $this->e($loan->item_code),
                'Tanggal pinjam: ' . $this->e($loan->loan_date),
                '<strong style="color:#1F1F33;">Jatuh tempo: ' . $this->e($loan->due_date) . '</strong>',
                'Sisa waktu: ' . ($daysLeft === 0 ? 'hari ini' : $daysLeft . ' hari'),
            ]);
        }

        $body = '<p style="margin:0 0 16px 0;">Halo <strong>' . $this->e($this->member->member_name) . '</strong> (' . $this->e($this->member->member_id) . '),</p>'
            . '<p style="margin:0 0 16px 0;">Koleksi berikut harus dikembalikan paling lambat pada tanggal jatuh tempo:</p>'
            . $items
            . $this->note('Kembalikan koleksi ke meja sirkulasi sebelum tanggal jatuh tempo agar tidak dikenai denda.');

        $this->contents = $this->layout('Masa pinjam segera berakhir', $body, 'PENGINGAT', 'warning');

        return $this;
    }
}
