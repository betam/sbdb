<?php
declare(strict_types=1);

namespace Betam;

use PDO;
use PDOException;

class Sbdb
{
    private static $instance;
    protected $lastError = '';
    private $timeStart;
    protected $PDO;


    private $logLevel = self::LOGNONE;
    private $logFile = '/dev/null';

    public const LOGDEBUG = 100;
    public const LOGINFO = 200;
    public const LOGNOTICE = 250;
    public const LOGWARNING = 300;
    public const LOGERROR = 400;
    public const LOGCRITICAL = 500;
    public const LOGALERT = 550;
    public const LOGEMERGENCY = 600;
    public const LOGNONE = 9999;

    public $isConnected = false;
    private $displayLog = false;


    private function __clone()
    {
    }

    private function __construct()
    {
        //$this->PDO = new PDO(); //TODO kill. It is only for storm
        $this->timeStart = $this->getMicroTime();
    }


    public static function getInstance(): Sbdb
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    public function getPDO(): PDO
    {
        return $this->PDO;
    }


    public function PDOConnect(string $dbHost, string $dbName, string $dbLogin, string $dbPassword): void
    {
        $dsn = 'mysql:dbname=' . $dbName . ';host=' . $dbHost . '';
        $user = $dbLogin;
        $password = $dbPassword;

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_COMPRESS => TRUE,
            PDO::ATTR_EMULATE_PREPARES => true
        ];

        try {
            $this->PDO = new PDO($dsn, $user, $password, $options);
            $this->PDO->exec('set names utf8');
            $this->isConnected = true;
        } catch (PDOException $e) {
            $this->logger(self::LOGCRITICAL, 'Подключение PDO не удалось', [$e->getMessage()]);
            $this->logger(self::LOGDEBUG, 'Подключение PDO не удалось', [$e]);
            $this->isConnected = false;
        }
    }

    public function setLog(int $level, string $file): void
    {
        $this->logLevel = $level;
        $this->logFile = $file;
    }

    public function setDisplayLog(bool $status = true): void
    {
        $this->displayLog = $status;
    }

    public function getMicroTime(): float
    {
        [$uSec, $sec] = explode(' ', microtime());
        return ((float)$uSec + (float)$sec);
    }

    public function getDeltaTime(): float
    {
        $now = $this->getMicroTime();
        return $now - $this->timeStart;
    }


/////////////////////

    ///Функции для работы с таблицами БД

    public function pdoPrepare(string $query)
    {
        $dbh = $this->PDO;
        try {
            $sth = $dbh->prepare($query);
        } catch (PDOException $e) {
            $this->logger(self::LOGCRITICAL, 'PREPARE STMT FAILED', [$e->getMessage(), $query]);
            $this->logger(self::LOGDEBUG, 'PREPARE STMT FAILED', [$e]);
            return $this->unSuccess($e->getMessage());
        }
        return $sth;
    }


    public function pdoExecute(\PDOStatement $sth, array $values)
    {
        try {
            $sth->execute($values);
        } catch (PDOException $e) {
            $this->logger(self::LOGCRITICAL, 'EXECUTE STMT FAILED', [$e->getMessage(), $values]);
            $this->logger(self::LOGDEBUG, 'EXECUTE STMT FAILED', [$values]);
            $this->logger(self::LOGDEBUG, 'EXECUTE STMT FAILED', [$e]);
            return $this->unSuccess($e->getMessage());
        }

        return $sth;
    }

    public function pdoQuery(string $query)
    {
        $dbh = $this->PDO;
        try {
            $sth = $dbh->query($query);
        } catch (PDOException $e) {
            $this->logger(self::LOGCRITICAL, 'QUERY FAILED', [$e->getMessage(), $query]);
            $this->logger(self::LOGDEBUG, 'QUERY FAILED', [$e]);
            return $this->unSuccess($e->getMessage());
        }
        return $sth;
    }


    /**
     * Запрос к базе
     *
     * @param string $query The query
     * @param array $values The values
     * @param boolean $noFetch не фетчить, например на delete, ибо фатал
     *
     * @return array ( description_of_the_return_value )
     */
    public function query(string $query, array $values = [], bool $noFetch = false): array
    {
        $this->logger(self::LOGDEBUG, 'QUERY', [self::interpolateQuery($query, $values)]);

        $sth = $this->pdoPrepare($query);
        if (is_array($sth)) {
            return $sth;
        }

        $sth = $this->pdoExecute($sth, $values);
        if (is_array($sth)) {
            return $sth;
        }

        if (!$noFetch) {
            $arr = $sth->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $arr = [];
        }
        return $this->success(count($arr), 0, $arr);
    }


    /**
     * Вставляет строку
     *
     * @param string $table_name The table name
     * @param string $autoindexName The autoindex name
     *
     * @return array ( description_of_the_return_value )
     */
    public function insert(string $table_name, string $autoindexName = 'id'): array
    {
        $query = 'INSERT INTO ' . $table_name . ' (`' . $autoindexName . '`) VALUES (NULL)';
        $this->logger(self::LOGDEBUG, 'QUERY', [self::interpolateQuery($query, [])]);
        $sth = $this->pdoQuery($query);
        if (is_array($sth)) {
            return $sth;
        }
        return $this->success($sth->rowCount(), (int)$this->PDO->lastInsertId());
    }


    /**
     * Апдейт строки
     *
     * @param string $tableName Имя таблицы
     * @param array $array_val Массив, ключ - имя поля, значение - значение для запроса. Несуществующие имена полей будут удалены. Для Where используйте префикс ключа where_
     * @param string $where_stm Пример "1=1" "id=:where_id"
     * @param array $reject_keys Опция. Массив отторгаемых ключей, например ["id","dt_created"]. В таком случае элементы массива array_val с ключами,"id", "dt_created" не попадут в update. По умолчанию - пустой массив.
     * @param boolean|array $only_keys Опция. Фолз для игнорирования или массив разрешенных ключей, например ["id", "dt_created"]. В таком случае элементы массива array_val с ключами отличными от "id", "dt_created" не попадут в update. по умолчанию - фолз.
     *
     * @return     array          DBSError/DBSSuccess
     */
    public function update(string $tableName, array $array_val, string $where_stm, array $reject_keys = [], bool $only_keys = false): array
    {
        $query = "DESCRIBE {$tableName}";
        $this->logger(self::LOGDEBUG, 'QUERY', [self::interpolateQuery($query, [])]);
        $sth = $this->pdoQuery($query);

        $tableFields = $sth->fetchAll(PDO::FETCH_COLUMN);
        $tableFields = array_flip($tableFields);

        $toUpdate = [];
        $toWhere = [];

        ///////////REFACTOR

        foreach ($array_val as $k => $v) {
            if ($reject_keys[$k]) {
                continue;
            }

            if (is_array($only_keys && !$only_keys[$k] && !(strstr($k, "where_") && !isset($tableFields[$k])))) continue;
            //todo refactor

            if (!isset($tableFields[$k]) && false !== strpos($k, 'where_')) {
                $toWhere[$k] = $v;
                continue;
            }

            if (!isset($tableFields[$k])) {
                continue;
            }


            $toUpdate[$k] = $v;
        }

        //Todo добавить обработку если нечего апдейтить
        $query = "UPDATE {$tableName} SET";
        $values = [];

        foreach ($toUpdate as $name => $value) {
            //TODO переделать NOW на нативный Now()
            if (strtolower((string)$value) === 'now()') {
                $value = date('Y-m-d H:i:s');
            }
            $query .= ' ' . $name . ' = :' . $name . ',';
            $values[':' . $name] = $value;
        }

        foreach ($toWhere as $name => $value) {
            $values[':' . $name] = $value; // save the placeholder
        }

        $query = substr($query, 0, -1); // remove last ','
        $query .= " WHERE {$where_stm}";
        /////////////////


        $this->logger(self::LOGDEBUG, 'QUERY', [self::interpolateQuery($query, $values)]);
        $sth = $this->pdoPrepare($query);
        if (is_array($sth)) {
            return $sth;
        }

        $sth = $this->pdoExecute($sth, $values);
        if (is_array($sth)) {
            return $sth;
        }

        return $this->success($sth->rowCount());
    }


    public function getLastError(): string
    {
        return $this->lastError;
    }


    protected function unSuccess(string $errorText): array
    {
        $this->lastError = $errorText;
        $this->logger(self::LOGDEBUG, 'UNSUCCESS', [$errorText]);

        $error['error'] = true;
        $error['text'] = $errorText;
        return $error;
    }

    protected function success(int $countRows, int $insertedId = 0, array $rows = []): array
    {
        $this->logger(self::LOGDEBUG, 'SUCCESS', [$countRows]);
        if ($insertedId > 0) {
            $this->logger(self::LOGDEBUG, 'INSERTED ID', [$insertedId]);
        }

        $error['error'] = false;
        $error['count_rows'] = $countRows;
        $error['inserted_id'] = $insertedId;
        $error['rows'] = $rows;
        return $error;
    }

    public function beginTransaction(): array
    {
        $dbh = $this->PDO;
        try {
            $dbh->beginTransaction();
            $this->logger(self::LOGDEBUG, 'Begin transaction');
            return $this->success(0);
        } catch (PDOException $Exception) {
            $this->logger(self::LOGINFO, 'Start transaction failed');
            return $this->unSuccess($Exception->getMessage());
        }
    }

    public function commitTransaction(): array
    {
        $dbh = $this->PDO;
        try {
            $this->logger(self::LOGDEBUG, 'Commit transaction');
            $dbh->commit();
            return $this->success(0);
        } catch (PDOException $Exception) {
            $this->logger(self::LOGDEBUG, 'Commit transaction failed');
            return $this->unSuccess($Exception->getMessage());
        }
    }


    public function rollbackTransaction(): array
    {
        $dbh = $this->PDO;

        try {
            $this->logger(self::LOGDEBUG, 'Rollback transaction');
            $dbh->rollback();
            return $this->success(0);
        } catch (PDOException $Exception) {
            $this->logger(self::LOGDEBUG, 'Rollback transaction failed');
            return $this->unSuccess($Exception->getMessage());
        }

    }


    /**
     * Replaces any parameter placeholders in a query with the value of that
     * parameter. Useful for debugging. Assumes anonymous parameters from
     * $params are are in the same order as specified in $query
     *
     * @param string $query The sql query with parameter placeholders
     * @param array $params The array of substitution parameters
     * @return string The interpolated query
     */
    public static function interpolateQuery(string $query, array $params): string
    {
        $keys = [];
        # build a regular expression for each parameter
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $keys[] = '/' . $key . '/';
            } else {
                $keys[] = '/[?]/';
            }
        }
        $query = preg_replace($keys, $params, $query, 1, $count);
        return $query;
    }


    private function logger(int $level, string $message, array $data = []): void
    {
        if ($this->logLevel < $level) {
            return;
        }

        $str = date('d,m.Y h:i:s') . "\t";
        $str .= round($this->getDeltaTime(), 4) . "\t";
        $str .= $level . "\t";
        $str .= $message . "\t";
        $str .= json_encode($data) . PHP_EOL;

        if ($this->displayLog) {
            echo $str;
        }

        file_put_contents($this->logFile, $str, FILE_APPEND);
    }


}

