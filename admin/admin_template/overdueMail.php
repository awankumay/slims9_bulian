<?php
/**
 * @author Drajat Hasan
 * @email drajathasan20@gmail.com
 * @create date 2022-10-08 11:10:32
 * @modify date 2022-11-12 06:31:55
 * @license GPLv3
 * @desc [description]
 */

use SLiMS\DB;
use SLiMS\Mail\TemplateContract;

class overdueMail extends TemplateContract
{
    private $circulation = null;
    private $member = [];
    private $overdueData = [];

    public function __construct($member)
    {
        $this->member = $member;
    }

    /**
     * SEt circulation instance
     * to calculate overdue and overdue data
     *
     * @param mysqli $db
     * @param array $overdueLoan
     * @return void
     */
    public function setCirculationData($db, $overdueLoan)
    {
        require MDLBS . 'circulation/circulation_base_lib.inc.php';

        $this->circulation = new circulation($db, $this->member->member_id);
        $this->circulation->ignore_holidays_fine_calc = config('ignore_holidays_fine_calc');
        $this->circulation->holiday_dayname = $_SESSION['holiday_dayname'];
        $this->circulation->holiday_date = $_SESSION['holiday_date'];

        // overude data
        $this->overdueData = $overdueLoan;
    }

    /**
     * Mail content output process
     *
     * @return overdueMail
     */
    public function render()
    {
        $items = '';
        foreach ($this->overdueData as $loan) {
            $days = $this->circulation->countOverdueValue($loan['loan_id'], date('Y-m-d'))['days'] ?? 0;
            $items .= $this->itemCard($this->generateCoverUrl($loan['image']), $loan['title'], [
                'Kode eksemplar: ' . $this->e($loan['item_code']),
                'Tanggal pinjam: ' . $this->e($loan['loan_date']),
                'Jatuh tempo: ' . $this->e($loan['due_date']),
                '<strong style="color:#8A1C0F;">Terlambat: ' . (int) $days . ' hari</strong>',
            ]);
        }

        $body = '<p style="margin:0 0 16px 0;">Halo <strong>' . $this->e($this->member->member_name) . '</strong> (' . $this->e($this->member->member_id) . '),</p>'
            . '<p style="margin:0 0 16px 0;">Koleksi berikut sudah melewati tanggal jatuh tempo dan belum dikembalikan:</p>'
            . $items
            . $this->note('Segera kembalikan koleksi ke meja sirkulasi. Denda keterlambatan dihitung sesuai ketentuan perpustakaan.');

        $this->contents = $this->layout('Pemberitahuan keterlambatan pengembalian', $body, 'TERLAMBAT', 'danger');

        return $this;
    }
}
