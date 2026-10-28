<?php
/**
 * @compose by Drajat Hasan
 * @email drajathasan20@gmail.com
 * @create date 2022-09-18 19:39:17
 * @modify date 2023-01-26 15:04:20
 * @license GPLv3
 * @desc [description]
 */

namespace SLiMS;

class Visitor
{
    /**
     * Default visitor property
     *
     * @var array
     */
    protected array $allowedIp;
    protected string $visitTimeLimit;
    protected bool $accessAllow = false;
    protected array $data = [];
    protected bool $alreadyCheckIn = false;
    protected bool $member = false;
    protected bool $memberExpire = false;
    protected bool $institutionEmpty = false;
    protected string $error = '';
    protected string $image = 'person.png';
    protected bool $result = false;
    protected $opac;

    public function __construct(array $allowedIp, string $visitTimeLimit, Opac $opac)
    {
        $this->allowedIp = $allowedIp;
        $this->visitTimeLimit = $visitTimeLimit;
        $this->opac = $opac;
    }

    /**
     * Access check by ip
     *
     * Rule format (Sistem > Allowed Counter IP, separated with ;):
     * exact ip (192.168.1.10), wildcard (192.168.1.*), prefix ending
     * with a dot (192.168.1.) or CIDR (192.168.1.0/24).
     *
     * @return Visitor
     */
    public function accessCheck()
    {
        $client = (string)ip();
        foreach ($this->allowedIp as $rule) {
            if (static::ipMatches((string)$rule, $client)) {
                $this->accessAllow = true;
                break;
            }
        }

        return $this;
    }

    /**
     * Match client ip against one allowed ip rule
     *
     * @param string $rule
     * @param string $client
     * @return bool
     */
    public static function ipMatches(string $rule, string $client): bool
    {
        $rule = strtolower(trim($rule));
        $client = strtolower(trim($client));
        if ($rule === '' || $client === '') return false;

        // the server itself
        if (in_array($rule, ['localhost', '127.0.0.1', '::1']) && in_array($client, ['127.0.0.1', '::1'])) return true;

        if ($rule === $client) return true;

        // CIDR
        if (strpos($rule, '/') !== false) {
            [$subnet, $bits] = explode('/', $rule, 2);
            $subnet = inet_pton($subnet);
            $address = inet_pton($client);
            if ($subnet === false || $address === false || strlen($subnet) !== strlen($address) || !ctype_digit($bits)) return false;
            $bits = (int)$bits;
            if ($bits > strlen($subnet) * 8) return false;
            $bytes = intdiv($bits, 8);
            if (substr($subnet, 0, $bytes) !== substr($address, 0, $bytes)) return false;
            if ($bits % 8 === 0) return true;
            $mask = (0xff << (8 - $bits % 8)) & 0xff;
            return (ord($subnet[$bytes]) & $mask) === (ord($address[$bytes]) & $mask);
        }

        // wildcard (192.168.1.*) or prefix (192.168.1.)
        if (substr($rule, -1) === '.') $rule .= '*';
        if (strpos($rule, '*') !== false) {
            $pattern = '/^' . str_replace('\\*', '[0-9a-f:.]*', preg_quote($rule, '/')) . '$/';
            return (bool)preg_match($pattern, $client);
        }

        return false;
    }

    /**
     * Record visitor counter
     *
     * @param int|string $memberId
     * @return Visitor
     */
    public function record($memberId)
    {
        $db = DB::getInstance();
        //DB::debug();

        try {
            $memberId = static::cleanText($memberId, 100);
            $institution = static::cleanText($_POST['institution'] ?? '', 100);

            $query = "SELECT member_id,member_name,member_image,inst_name, IF(TO_DAYS('".date('Y-m-d')."')>TO_DAYS(expire_date), 1, 0) AS is_expire FROM member WHERE member_id = ?";
            $statement = $db->prepare($query);
            $statement->execute([$memberId]);

            // room code check
            $roomCode = $this->roomCode();

            // Member
            if ($statement->rowCount() > 0)
            {
                $this->member = true;
                $member = $statement->fetch(\PDO::FETCH_ASSOC);
                
                // set image based on record data
                $this->image = $member['member_image']??'person.png';

                // set expire status
                if ($member['is_expire'] == 1) $this->memberExpire = true;

                $this->data = [$member['member_id'], $member['member_name'], $member['inst_name']];
                Plugins::getInstance()->execute('MEMBER_ON_VISIT', ['data' => $member]);
            }
            // Guest
            else
            {
                // institution check for guest
                if ($institution === '')
                {
                    $this->institutionEmpty = true;
                    return $this;
                }

                // default non member photos
                $this->image = 'non_member.png';
                $this->data = [ null, $memberId, $institution];
                Plugins::getInstance()->execute('NON_MEMBER_ON_VISIT', ['data' => array_slice($this->data, 1)]);
            }

        
            $insertQuery = "INSERT INTO visitor_count (member_id, member_name, institution, room_code, checkin_date) VALUES (?,?,?,?,?)";
            $insertStatement = $db->prepare($insertQuery);

            if ($this->opac->enable_visitor_limitation && $this->alreadyCheckIn($memberId, $this->member))
            {
                $this->result = true;
                $this->alreadyCheckIn = true;
            }
            else
            {
                $this->result = $insertStatement->execute(array_merge($this->data, [$roomCode, date('Y-m-d H:i:s')]));
            }
        } catch (\PDOException $e) {
            // set error
            $this->error = $e->getMessage();
            $this->result = false;
        }

        return $this;
    }

    /**
     * Normalize text from the visitor form.
     * Global Sanitizer already escaped it for SQL, but this class
     * uses prepared statements, so remove the extra slashes.
     *
     * @param mixed $value
     * @param int $maxLength
     * @return string
     */
    public static function cleanText($value, int $maxLength): string
    {
        if (!is_scalar($value)) return '';
        $value = stripslashes((string)$value);
        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '');
        return mb_substr($value, 0, $maxLength);
    }

    /**
     * Room code from ?room=, must be registered
     * at Master File > Visitor Room
     *
     * @return string|null
     */
    protected function roomCode()
    {
        $room = trim((string)(is_scalar($_GET['room'] ?? null) ? $_GET['room'] : ''));
        if ($room === '') return null;

        $statement = DB::getInstance()->prepare('SELECT unique_code FROM mst_visitor_room WHERE unique_code = ?');
        $statement->execute([$room]);
        $code = $statement->fetchColumn();
        if ($code === false) throw new \PDOException("Room code is not valid!");

        return $code;
    }

    /**
     * Simple per ip limit for visitor counter POST,
     * $limit request per minute, 0 = no limit.
     * Fails open when the cache folder is not writable.
     *
     * @param int $limit
     * @return bool
     */
    public function withinRateLimit(int $limit): bool
    {
        if ($limit <= 0) return true;

        $dir = SB . 'files' . DS . 'cache' . DS . 'visitor-rate';
        if (!is_dir($dir)) {
            $parent = dirname($dir);
            if (!is_dir($parent) && (!is_writable(dirname($parent)) || !mkdir($parent, 0755))) return true;
            if (!is_writable($parent) || !mkdir($dir, 0755)) return true;
        }
        if (!is_writable($dir)) return true;

        // one window file per client; drop stale ones now and then so the folder does not grow
        // ponytail: probabilistic cleanup, move to a cron job if the folder still grows too much
        if (random_int(1, 100) === 1) {
            foreach (glob($dir . DS . '*.txt') ?: [] as $old) {
                if (filemtime($old) < time() - 120) @unlink($old);
            }
        }

        $handle = fopen($dir . DS . sha1(self::rateLimitKey((string)ip())) . '.txt', 'c+');
        if ($handle === false) return true;

        flock($handle, LOCK_EX);
        $window = (int)floor(time() / 60);
        [$savedWindow, $count] = array_map('intval', explode(' ', trim((string)stream_get_contents($handle)) . ' 0 0'));
        $count = $savedWindow === $window ? $count + 1 : 1;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $window . ' ' . $count);
        flock($handle, LOCK_UN);
        fclose($handle);

        return $count <= $limit;
    }

    /**
     * IPv6 clients usually own a whole /64, so count them per /64.
     *
     * @param string $ip
     * @return string
     */
    public static function rateLimitKey(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed !== false && strlen($packed) === 16) return bin2hex(substr($packed, 0, 8)) . '::/64';
        return $ip;
    }

    /**
     * Get last data to decide
     * ready checkin now or later
     *
     * @param int|string $memberIdOrName
     * @param boolean $isMember
     * @return bool
     */
    protected function alreadyCheckIn($memberIdOrName, $isMember = true)
    {
        $db = DB::getInstance();
        $criteria = 'member_name';
        if ($isMember) $criteria = 'member_id';
    
        $statement = $db->prepare('SELECT checkin_date FROM visitor_count WHERE '.$criteria.'=? ORDER BY checkin_date DESC LIMIT 1');
        $statement->execute([$memberIdOrName]);
        
        if ($statement->rowCount() > 0) {
            $data = $statement->fetchObject();
            $time = new \DateTime($data->checkin_date);
            $time->add(new \DateInterval('PT'.max(0, (int)$this->visitTimeLimit).'M'));
            $timelimit = $time->format('Y-m-d H:i:s');
            $now = date('Y-m-d H:i:s');
            if ($now < $timelimit) {
                return true;
            }
        }
    
        return false;
    }

    public function isMember()
    {
        return $this->member;
    }

    public function isAlreadyCheckIn()
    {
        return $this->alreadyCheckIn;
    }

    public function isMemberExpire()
    {
        return $this->memberExpire;
    }

    public function isAccessAllow()
    {
        return $this->accessAllow;
    }

    public function isInstitutionEmpty()
    {
        return $this->institutionEmpty;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function getData()
    {
        return array_merge($this->data, [$this->image]);
    }

    public function getError()
    {
        return $this->error;
    }
}