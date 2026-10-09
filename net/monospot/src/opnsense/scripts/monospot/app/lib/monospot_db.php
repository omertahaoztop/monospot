<?php
/**
 * SQLite3 uzantısı üzerinden PDO uyumlu minimal veritabanı katmanı.
 *
 * Neden: OPNsense depolarında pdo_sqlite paketi yok (php85-pdo_sqlite hiçbir repoda yok),
 * php85-sqlite3 ve PDO var ama PDO driver listesi boş. Idiorm/Paris PDO nesnesi ister;
 * bu sınıflar ORM::set_db() ile enjekte edilecek PDO-uyumlu bir cephe sağlar.
 *
 * Desteklenen yüzey: prepare/execute (isimli parametre çevrimiyle), fetch/fetchAll/
 * fetchColumn/rowCount/closeCursor/lastInsertId/quote/exec/getAttribute/setAttribute
 * ve transaction metotları. Başka bir şey gerekirse ekle, spekülatif metot yazma.
 */

class MonospotDbException extends Exception
{
}

class MonospotDbStatement
{
    private $db;
    private $sql;
    private $rows = array();
    private $cursor = 0;
    private $count = 0;
    private $writes = 0;

    public function __construct(MonospotSqliteDb $db, $sql)
    {
        $this->db = $db;
        $this->sql = $sql;
    }

    /**
     * Idiorm her zaman konumsal '?' yer tutucusu üretir; SQLite3 uzantısının isimli
     * parametre bağlaması daha güvenilir olduğu için '?' -> ':mspN' çevrimi yapılır.
     * Tırnak içindeki '?' karakterleri atlanır.
     */
    public static function namedSql($sql)
    {
        $out = '';
        $len = strlen($sql);
        $n = 0;
        $quote = null;
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($quote !== null) {
                $out .= $ch;
                if ($ch === $quote) {
                    if ($i + 1 < $len && $sql[$i + 1] === $quote) {  // '' kaçışı
                        $out .= $sql[++$i];
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $out .= $ch;
                continue;
            }
            if ($ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                $end = strpos($sql, "\n", $i);
                $out .= $end === false ? substr($sql, $i) : substr($sql, $i, $end - $i);
                $i = $end === false ? $len : $end - 1;
                continue;
            }
            if ($ch === '?') {
                $out .= ':msp' . (++$n);
                continue;
            }
            $out .= $ch;
        }
        return $out;
    }

    public function execute($params = array())
    {
        $this->rows = array();
        $this->cursor = 0;
        $this->count = 0;
        $this->writes = 0;

        $stmt = $this->db->raw()->prepare(self::namedSql($this->sql));
        if ($stmt === false) {
            throw new MonospotDbException('prepare başarısız: ' . $this->sql);
        }
        $i = 0;
        foreach ((array)$params as $value) {
            $i++;
            $type = is_int($value) ? SQLITE3_INTEGER : (is_float($value) ? SQLITE3_FLOAT
                : (is_null($value) ? SQLITE3_NULL : SQLITE3_TEXT));
            if ($stmt->bindValue(':msp' . $i, $value, $type) === false) {
                throw new MonospotDbException('bindValue başarısız: param ' . $i);
            }
        }
        $result = @$stmt->execute();
        if ($result === false) {
            throw new MonospotDbException(
                'SQL hatası: ' . $this->db->raw()->lastErrorMsg() . ' (' . $this->sql . ')'
            );
        }
        // DİKKAT: yazma ifadelerinde (INSERT/UPDATE/DELETE) SQLite3Result::fetchArray()
        // ifadeyi yeniden adımlar ve yazma tekrar eder (PHP 8.5'te gözlemlendi:
        // tek execute iki satır insert). Yazma sonuçlarında asla fetch edilmez.
        if (!($result instanceof SQLite3Result) || $result->numColumns() === 0) {
            if ($result instanceof SQLite3Result) {
                $result->finalize();
            }
            $this->writes = $this->db->raw()->changes();
            return true;
        }
        while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
            $this->rows[] = $row;
        }
        $result->finalize();
        $this->count = count($this->rows);
        return true;
    }

    public function fetch($mode = null)
    {
        if ($this->cursor >= $this->count) {
            return false;
        }
        $row = $this->rows[$this->cursor++];
        if ($mode === PDO::FETCH_NUM) {
            return array_values($row);
        }
        if ($mode === PDO::FETCH_BOTH) {
            return array_merge($row, array_values($row));
        }
        return $row;
    }

    public function fetchAll($mode = null)
    {
        $out = array();
        while (($row = $this->fetch($mode)) !== false) {
            $out[] = $row;
        }
        return $out;
    }

    public function fetchColumn($column = 0)
    {
        $row = $this->fetch(PDO::FETCH_NUM);
        return $row === false ? false : $row[$column];
    }

    public function rowCount()
    {
        return $this->count ?: $this->writes;
    }

    public function closeCursor()
    {
        $this->rows = array();
        $this->cursor = 0;
        $this->count = 0;
        return true;
    }

    public function setFetchMode($mode)
    {
        return true;
    }

    public function errorCode()
    {
        return null;
    }

    public function errorInfo()
    {
        return array('00000', null, null);
    }
}

class MonospotSqliteDb
{
    private $handle;
    private $driver = 'sqlite';

    public function __construct($path)
    {
        $this->handle = new SQLite3($path);
        $this->handle->busyTimeout(5000);
        $this->handle->exec('PRAGMA journal_mode = WAL');
    }

    /** @return SQLite3 */
    public function raw()
    {
        return $this->handle;
    }

    public function prepare($sql)
    {
        return new MonospotDbStatement($this, $sql);
    }

    public function query($sql)
    {
        $stmt = new MonospotDbStatement($this, $sql);
        $stmt->execute(array());
        return $stmt;
    }

    public function exec($sql)
    {
        $ok = $this->handle->exec($sql);
        if ($ok === false) {
            throw new MonospotDbException('exec başarısız: ' . $this->handle->lastErrorMsg());
        }
        return $this->handle->changes();
    }

    public function quote($value, $type = null)
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return "'" . $this->handle->escapeString($value) . "'";
    }

    public function lastInsertId($name = null)
    {
        return $this->handle->lastInsertRowID();
    }

    public function getAttribute($attribute)
    {
        switch ($attribute) {
            case PDO::ATTR_DRIVER_NAME:
                return $this->driver;
            case PDO::ATTR_ERRMODE:
                return PDO::ERRMODE_EXCEPTION;
            default:
                return null;
        }
    }

    public function setAttribute($attribute, $value)
    {
        return true;
    }

    public function beginTransaction()
    {
        return $this->handle->exec('BEGIN');
    }

    public function commit()
    {
        return $this->handle->exec('COMMIT');
    }

    public function rollBack()
    {
        return $this->handle->exec('ROLLBACK');
    }

    public function errorInfo()
    {
        return array('00000', null, null);
    }
}

/**
 * ORM'i SQLite dosyasına bağlar. Gerçek PDO sqlite driver'ı varsa onu kullan
 * (yerel geliştirme makinesi), yoksa SQLite3 tabanlı cepheye düş (OPNsense).
 */
function monospot_db_init($path)
{
    $drivers = class_exists('PDO') ? PDO::getAvailableDrivers() : array();
    if (in_array('sqlite', $drivers, true) || in_array('sqlite2', $drivers, true)) {
        ORM::configure('sqlite:' . $path);
        return;
    }
    ORM::set_db(new MonospotSqliteDb($path));
}

// ponytail: yerel doğrulama — pdo_sqlite olmayan ortamda da çalışır.
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $check = function ($ok, $what) {
        if (!$ok) {
            fwrite(STDERR, "FAIL: $what\n");
            exit(1);
        }
    };
    $tmp = tempnam(sys_get_temp_dir(), 'mspdb') . '.sqlite';
    $db = new MonospotSqliteDb($tmp);
    $db->exec('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, n INTEGER)');
    $st = $db->prepare('INSERT INTO t (name, n) VALUES (?, ?)');
    $st->execute(array("o'brien?", 7));
    $check($db->lastInsertId() == 1, 'lastInsertId');
    $st = $db->prepare('SELECT name, n FROM t WHERE n = ?');
    $st->execute(array(7));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $check(isset($rows[0]) && $rows[0]['name'] === "o'brien?" && $rows[0]['n'] == 7, 'fetchAll + tırnak/parametre');
    $st = $db->prepare('SELECT COUNT(*) FROM t');
    $st->execute(array());
    $check($st->fetchColumn() == 1, 'fetchColumn');
    $st = $db->prepare('SELECT n FROM t WHERE n = ?');
    $st->execute(array(99));
    $check($st->fetch() === false && $st->rowCount() === 0, 'boş sonuç');
    $st = $db->prepare('UPDATE t SET name = ? WHERE id = ?');
    $st->execute(array('x', 1));
    $check($st->rowCount() === 1, 'güncelleme rowCount');
    $check($db->prepare('SELECT "?" AS q')->execute(array()) === true, 'tırnak içi ? çevrilmemeli');
    echo "monospot_db self-check OK\n";
    unlink($tmp);
}
