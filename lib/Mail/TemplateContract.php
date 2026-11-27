<?php
/**
 * @author Drajat Hasan
 * @email drajathasan20@gmail.com
 * @create date 2022-10-06 23:55:09
 * @modify date 2022-10-19 00:42:18
 * @license GPLv3
 * @desc [description]
 */

namespace SLiMS\Mail;

abstract class TemplateContract
{
    // Logo in the email header, embedded by Mail::loadTemplate() when the file exists.
    // It lives in the images volume, so each library supplies its own (120x140 PNG).
    const LOGO_CID = 'logo-email';
    const LOGO_FILE = 'images/default/logo-email.png';

    const BADGE_COLORS = [
        'danger' => ['#FBE9E7', '#8A1C0F'],
        'warning' => ['#FFF4CC', '#6B5200'],
        'info' => ['#E6ECF8', '#23234A'],
    ];

    protected $contents = '';
    protected $mail = null;
    protected $minify = false;

    /**
     * Host check
     *
     * @return bool
     */
    public function isLocal()
    {
        // host check
        if (preg_match('/localhost/i', $_SERVER['HTTP_HOST'])) return true;

        // ip address = local
        if (preg_match('/(([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])\\.){3}([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])/i', $_SERVER['HTTP_HOST'])) return true;

        // schema check
        if ($_SERVER['REQUEST_SCHEME'] == 'http' && !ip()->isBehindProxy()) return true;

        // online
        return false;
    }

    /**
     * Minify html template
     *
     * @param boolean $status
     * @return void
     */
    public function setMinify(bool $status)
    {
        $this->minify = $status;
    }

    /**
     * Book cover generator
     * 
     * https is required to make our resource is secure at 
     * browser (CSP issue)
     *
     * @param string $filename
     * @return void
     */
    public function generateCoverUrl($filename)
    {
        // in local environment? ok, use dummy image from shutterstock
        if ($this->isLocal()) return 'https://image.shutterstock.com/image-vector/abstract-a4-printable-brochure-book-600w-2105860082.jpg';

        // get from SLiMS with https
        return 'https://' . $_SERVER['SERVER_NAME'] . SWB . 'lib/minigalnano/createthumb.php?filename=images/' . (empty($filename) ? 'default/image.png' : 'docs/' . $filename) . '&width=60';
    }

    /**
     * Escape a value for the email HTML
     */
    protected function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Shared email layout: header (logo, sender name, library subname), body,
     * signature and footer. Table based with inline styles for Outlook and Gmail.
     *
     * @param string $title   heading, plain text
     * @param string $body    body HTML (already escaped)
     * @param string $badge   label above the heading, plain text ('' = none)
     * @param string $tone    badge color: danger, warning or info
     */
    protected function layout(string $title, string $body, string $badge = '', string $tone = 'info'): string
    {
        $name = $this->e(config('mail.from_name') ?: config('library_name'));
        $subname = $this->e(config('library_subname'));
        $replyTo = $this->e(config('mail.reply_to') ?: config('mail.from'));
        $site = $this->e($_SERVER['SERVER_NAME'] ?? '');

        $logo = '';
        if (file_exists(SB . self::LOGO_FILE)) {
            $logo = '<td style="padding-right:16px;"><img src="cid:' . self::LOGO_CID . '" width="40" height="47" alt="Logo" style="display:block;border:0;"></td>';
        }

        $badgeHtml = '';
        if ($badge !== '') {
            [$bg, $fg] = self::BADGE_COLORS[$tone] ?? self::BADGE_COLORS['info'];
            $badgeHtml = '<div style="margin:0 0 8px 0;"><span style="display:inline-block;background:' . $bg . ';color:' . $fg . ';font-size:12px;font-weight:bold;padding:4px 10px;border-radius:4px;">' . $this->e($badge) . '</span></div>';
        }

        $title = $this->e($title);
        $siteLink = $site === '' ? '' : '<br><a href="https://' . $site . '" style="color:#3A3A6A;">' . $site . '</a>';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
        <body style="margin:0;padding:0;background:#EEEFF4;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EEEFF4;"><tr><td align="center" style="padding:24px 12px;">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#FFFFFF;border:1px solid #DCDDE6;font-family:Arial,Helvetica,sans-serif;color:#1F1F33;">
        <tr><td style="background:#3A3A6A;padding:20px 32px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>{$logo}
        <td><div style="font-size:18px;font-weight:bold;color:#FFFFFF;">{$name}</div><div style="font-size:13px;color:#D6D6E8;">{$subname}</div></td>
        </tr></table></td></tr>
        <tr><td style="background:#F4D100;height:4px;line-height:4px;font-size:0;">&nbsp;</td></tr>
        <tr><td style="padding:32px;font-size:15px;line-height:1.6;">
        {$badgeHtml}<h1 style="margin:0 0 16px 0;font-size:21px;line-height:1.3;color:#1F1F33;">{$title}</h1>
        {$body}
        <p style="margin:24px 0 0 0;">Salam,<br>{$name}</p>
        </td></tr>
        <tr><td style="background:#F6F6FA;border-top:1px solid #E4E5EC;padding:20px 32px;font-size:12px;line-height:1.6;color:#55566A;">
        Email ini dikirim otomatis oleh {$name}. Ada pertanyaan? Balas email ini, balasan Anda diteruskan ke <a href="mailto:{$replyTo}" style="color:#3A3A6A;">{$replyTo}</a>.{$siteLink}
        </td></tr>
        </table></td></tr></table>
        </body></html>
        HTML;
    }

    /**
     * One loan/collection card: cover, title and detail lines (already escaped HTML).
     */
    protected function itemCard(string $coverUrl, string $title, array $lines): string
    {
        $cover = $this->e($coverUrl);
        $title = $this->e($title);
        $details = implode('<br>', $lines);

        return <<<HTML
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E4E5EC;margin:0 0 12px 0;"><tr>
        <td width="64" valign="top" style="padding:16px 0 16px 16px;"><img src="{$cover}" width="64" alt="Sampul" style="display:block;border:0;"></td>
        <td valign="top" style="padding:16px;font-size:13px;line-height:1.6;color:#55566A;"><div style="font-size:15px;font-weight:bold;line-height:1.4;color:#1F1F33;margin:0 0 6px 0;">{$title}</div>{$details}</td>
        </tr></table>
        HTML;
    }

    /**
     * Grey note box
     */
    protected function note(string $html): string
    {
        return '<div style="background:#F6F6FA;padding:12px 16px;margin:16px 0 0 0;font-size:13px;color:#55566A;">' . $html . '</div>';
    }

    /**
     * Rendering processs
     *
     * @return object
     */
    abstract public function render();
    
    /**
     * Generate email body without html tag
     *
     * @return value
     */
    public function asAltBody()
    {
        if (!$this->minify) return strip_tags($this->contents);
        
        return strip_tags(preg_replace(['/ {2,}/','/<!--.*?-->|\t|(?:\r?\n[ \t]*)+/s'], [' ',''],$this->contents));
    }

    /**
     * Convert object into string
     *
     * @return string
     */
    public function __toString()
    {
        if (!$this->minify) return $this->contents;

        return preg_replace(['/ {2,}/','/<!--.*?-->|\t|(?:\r?\n[ \t]*)+/s'], [' ',''],$this->contents);
    }
}