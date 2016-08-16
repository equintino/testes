<?php
/**
 * DAO for {@link Todo}.
 * <p>
 * It is also a service, ideally, this class should be divided into DAO and Service.
 */
final class TodoDao {
    /** @var PDO */
    private $db = null;
    public function __destruct() {
        // close db connection
        $this->db = null;
    }
    /**
     * Find all {@link Todo}s by search criteria.
     * @return array array of {@link Todo}s
     */
    public function find(TodoSearchCriteria $search = null) {
        $result = array();
        //print_r($this->getFindSql($search));die;
        foreach ($this->query($this->getFindSql($search)) as $row) {
            $todo = new Todo();
            TodoMapper::map($todo, $row);
            $result[$todo->getId()] = $todo;
        }
        //print_r($result);die;
        return $result;
    }
    public function find2() {
        foreach ($this->query($this->getFindSql2()) as $row) {
            $todo = new Todo();
            TodoMapper::map($todo, $row);
            $numero = $todo->getNumero();
        }
        return @$numero;
    }

    /**
     * Find {@link Todo} by identifier.
     * @return Todo Todo or <i>null</i> if not found
     */
    public function findById($id) {
        $row = $this->query('SELECT * FROM processojudicial WHERE deleted = 0 and id = ' . (int) $id)->fetch();
        if (!$row) {
            return null;
        }
        $todo = new Todo();
        TodoMapper::map($todo, $row);
        return $todo;
    }

    /**
     * Save {@link Todo}.
     * @param ToDo $todo {@link Todo} to be saved
     * @return Todo saved {@link Todo} instance
     */
    public function save(ToDo $todo) {
        if ($todo->getId() === null) {
            return $this->insert($todo);
        }
        return $this->update($todo);
    }

    /**
     * Delete {@link Todo} by identifier.
     * @param int $id {@link Todo} identifier
     * @return bool <i>true</i> on success, <i>false</i> otherwise
     */
    public function delete($id) {
        $sql = '
            UPDATE todo SET
                last_modified_on = :last_modified_on,
                deleted = :deleted
            WHERE
                id = :id';
        $statement = $this->getDb()->prepare($sql);
        $this->executeStatement($statement, array(
            ':last_modified_on' => self::formatDateTime(new DateTime(), new DateTimeZone('America/Sao_Paulo')),
            ':deleted' => true,
            ':id' => $id,
        ));
        return $statement->rowCount() == 1;
    }

    /**
     * @return PDO
     */
    private function getDb() {
        if ($this->db !== null) {
            return $this->db;
        }
        $config = Config::getConfig("db");
        try {
            $this->db = new PDO($config['dsn'], $config['username'], $config['password']);
        } catch (Exception $ex) {
            throw new Exception('DB connection error: ' . $ex->getMessage());
        }
        return $this->db;
    }

    private function getFindSql(TodoSearchCriteria $search = null) {
        if(strlen($search->getSINISTRO())==16){
            $sinistro=TodoValidator::mask($search->getSINISTRO(),"####.##.##.########");
        }else{
            $sinistro=$search->getSINISTRO();
        }
        
        $sql = 'SELECT * FROM processojudicial WHERE deleted = 0 ';
        $orderBy = ' SINISTRO';
        if ($search !== null) {
            if ($search->getStatus() !== null) {
                $sql .= 'AND status = ' . $this->getDb()->quote($search->getStatus());
                switch ($search->getStatus()) {
                    case Todo::STATUS_PENDING:
                        $orderBy = 'due_on, priority';
                        break;
                    case Todo::STATUS_DONE:
                    case Todo::STATUS_VOIDED:
                    case Todo::STATUS_CANCELADO:
                        $orderBy = 'due_on DESC, priority';
                        break;
                    default:
                        throw new Exception('No order for status: ' . $search->getStatus());
                }
            }
            if($search->getSEGURADOS()){
                $sql .= " and SEGURADOS like '%".$search->getSEGURADOS()."%'";
            }
           $sql .= " and SINISTRO like '%".$sinistro."%'";//'0135.93.03.00003108'";
        }
        $sql .= ' ORDER BY ' . $orderBy;
        //$sql = "SELECT * FROM processojudicial WHERE SINISTRO like '%0135.93.03.00003108%'";
        //$sql = "SELECT * FROM processojudicial WHERE SINISTRO like '%".$search->getSINISTRO()."%'";
        //print_r($sql);die;
        return $sql;
    }
    private function getFindSql2(TodoSearchCriteria $search = null) {
        $sql = 'SELECT * FROM processojudicial WHERE 1';
        $orderBy = 'id';
        if ($search !== null) {
            if ($search->getStatus() !== null) {
                $sql .= 'AND status = ' . $this->getDb()->quote($search->getStatus());
            }
        }
        $sql .= ' ORDER BY ' . $orderBy;
        return $sql;
    }

    /**
     * @return Todo
     * @throws Exception
     */
    private function insert(Todo $todo) {
        $now = new DateTime();
        $todo->setId(null);
        $todo->setCreatedOn($now);
        $todo->setLastModifiedOn($now);
        $todo->setStatus(Todo::STATUS_PENDING);
        //$todo->setAndamento(Todo::ANDAMENTO);
        
///// Configurar a $sql nos campos para inclusão no banco //////
        
        $sql = '
            INSERT INTO `processojudicial` (`ESI`, `ARQUIVO`, `AVISO`, `SINISTRO`, `PESSOA`, `CERTIFICADO`, `CPF`, `OBS`, `SEGURADOS`, `N_PROC`, `N_NATIGO`, `NATUREZA`, `PROCED`, `UF`, `CIDADE`, `FORO`, `N_VARA`, `VARA`, `CLIENTE`, `RECLAMANTE`, `FASE`, `TP_PROBA`, `PROVAVIL`, `VLPEDIDO`, `DTPEDIDO`, `TPACAO`, `NULL`, `deleted`, `id`, `priority`, `status`, `created_on`, `last_modified_on`) VALUES (:ESI, :ARQUIVO, :AVISO, :SINISTRO, :PESSOA, :CERTIFICADO, :CPF, :OBS, :SEGURADOS, :N_PROC, :N_NATIGO, :NATUREZA, :PROCED, :UF, :CIDADE, :FORO, :N_VARA, :VARA, :CLIENTE, :RECLAMANTE, :FASE, :TP_PROBA, :PROVAVIL, :VLPEDIDO, :DTPEDIDO, :TPACAO, :NULL, :deleted, :id, :priority, :status, :created_on, :last_modified_on)';
        return $this->execute($sql, $todo);
    }
    /**
     * @return Todo
     * @throws Exception
     */
    private function update(Todo $todo) {
        $todo->setLastModifiedOn(new DateTime(), new DateTimeZone('America/Sao_Paulo'));
        $sql = '
            UPDATE todo SET
                ESI = :ESI, ARQUIVO = :ARQUIVO, AVISO = :AVISO, SINISTRO = :SINISTRO, PESSOA = :PESSOA, CERTIFICADO = :CERTIFICADO, CPF = :CPF, OBS = :OBS, SEGURADOS = :SEGURADOS, N_PROC = :N_PROC, N_NATIGO = :N_NATIGO, NATUREZA = :NATUREZA, PROCED = :PROCED, UF = :UF, CIDADE = :CIDADE, FORO = :FORO, N_VARA = :N_VARA, VARA = :VARA, CLIENTE = :CLIENTE, RECLAMANTE = :RECLAMANTE, FASE = :FASE, TP_PROBA = :TP_PROBA, PROVAVIL = :PROVAVIL, VLPEDIDO = :VLPEDIDO, DTPEDIDO = :DTPEDIDO, TPACAO = :TPACAO, NULL = :NULL, deleted = :deleted, id = :id, priority = :priority, status = :status, created_on = :created_on, last_modified_on = :last_modified_on
            WHERE
                id = :id';
        return $this->execute($sql, $todo);
    }

    /**
     * @return Todo
     * @throws Exception
     */
    private function execute($sql, Todo $todo) {
        $statement = $this->getDb()->prepare($sql);
        $this->executeStatement($statement, $this->getParams($todo));
        if (!$todo->getId()) {
            return $this->findById($this->getDb()->lastInsertId());
        }
        if (!$statement->rowCount()) {
            throw new NotFoundException('Processo com ID "' . $todo->getId() . '" nao existe.');
        }
        return $todo;
    }

    private function getParams(Todo $todo) {
        $params = array(
            ':ESI'=> $todo->getESI(),
            ':ARQUIVO'=> $todo->getARQUIVO(),
            ':AVISO'=> $todo->getAVISO(),
            ':SINISTRO'=> $todo->getSINISTRO(),
            ':PESSOA'=> $todo->getPESSOA(),
            ':CERTIFICADO'=> $todo->getCERTIFICADO(),
            ':CPF'=> $todo->getCPF(),
            ':OBS'=> $todo->getOBS(),
            ':SEGURADOS'=> $todo->getSEGURADOS(),
            ':N_PROC'=> $todo->getN_PROC(),
            ':N_NATIGO'=> $todo->getN_NATIGO(),
            ':NATUREZA'=> $todo->getNATUREZA(),
            ':PROCED'=> $todo->getPROCED(),
            ':UF'=> $todo->getUF(),
            ':CIDADE'=> $todo->getCIDADE(),
            ':FORO'=> $todo->getFORO(),
            ':N_VARA'=> $todo->getN_VARA(),
            ':VARA'=> $todo->getVARA(),
            ':CLIENTE'=> $todo->getCLIENTE(),
            ':RECLAMANTE'=> $todo->getRECLAMANTE(),
            ':FASE'=> $todo->getFASE(),
            ':TP_PROBA'=> $todo->getTP_PROBA(),
            ':PROVAVIL'=> $todo->getPROVAVIL(),
            ':VLPEDIDO'=> $todo->getVLPEDIDO(),
            ':DTPEDIDO'=> $todo->getDTPEDIDO(),
            ':TPACAO'=> $todo->getTPACAO(),
            ':NULL'=> $todo->getNULL(),
            ':deleted'=> $todo->getdeleted(),
            ':id'=> $todo->getId(),
            ':priority'=> $todo->getPriority(),
            ':status'=> $todo->getStatus(),
            ':created_on' => self::formatDateTime($todo->getCreatedOn()),
            ':last_modified_on' => self::formatDateTime($todo->getLastModifiedOn())
        );
        if ($todo->getId()) {
            // unset created date, this one is never updated
            unset($params[':created_on']);
        }
       // var_dump($params);
        return $params;
    }

    private function executeStatement(PDOStatement $statement, array $params) {
        if (!$statement->execute($params)) {
            self::throwDbError($this->getDb()->errorInfo());
        }
    }

    /**
     * @return PDOStatement
     */
    private function query($sql) {
        $statement = $this->getDb()->query($sql, PDO::FETCH_ASSOC);
        if ($statement === false) {
            self::throwDbError($this->getDb()->errorInfo());
        }
        return $statement;
    }

    private static function throwDbError(array $errorInfo) {
        // TODO log error, send email, etc.
        throw new Exception('DB error [' . $errorInfo[0] . ', ' . $errorInfo[1] . ']: ' . $errorInfo[2]);
    }

    private static function formatDateTime(DateTime $date) {
        return $date->format(DateTime::ISO8601);
    }
}
?>