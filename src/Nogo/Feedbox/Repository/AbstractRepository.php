<?php
namespace Nogo\Feedbox\Repository;

use Aura\Sql\Connection\AbstractConnection;

/**
 * Class AbstractRepository
 *
 * @package Nogo\Feedbox\Repository
 */
abstract class AbstractRepository implements Repository
{
    /**
     * @var AbstractConnection
     */
    protected $connection;

    protected $userScope = false;

    /**
     * @param AbstractConnection $connection
     */
    public function __construct(AbstractConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return AbstractConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Is user scope activated.
     *
     * @return bool
     */
    public function hasUserScope()
    {
        return $this->userScope;
    }

    /**
     * Set user id for queries
     *
     * @param $user_id int
     */
    public function setUserScope($user_id)
    {
        $this->userScope = $user_id;
    }

    /**
     * Find entity by id.
     *
     * @param $id
     * @return array | boolean
     */
    public function find($id)
    {
        return $this->findBy($this->identifier(), $id);
    }

    /**
     * Find one entity by name and value.
     *
     * @param $name
     * @param $value
     * @return array | boolean
     */
    public function findBy($name, $value)
    {
        /**
         * @var $select \Aura\Sql\Query\Select
         */
        $select = $this->connection->newSelect();
        $select->cols(['*'])
            ->from($this->tableName())
            ->where($name . ' = :' . $name)
            ->orderBy([$this->identifier() . ' ASC']);

        $bind = $this->scopeByUserId($select, [ $name => $value ]);

        $result = $this->connection->fetchOne($select, $bind);

        if (!empty($result)) {
            $result = $this->withRelations($result);
        }

        return $result;
    }

    /**
     * Find all entity by name and value.
     *
     * @param $name
     * @param $value
     * @return array | boolean
     */
    public function findAllBy($name, $value)
    {
        /**
         * @var $select \Aura\Sql\Query\Select
         */
        $select = $this->connection->newSelect();
        $select->cols(['*'])
            ->from($this->tableName())
            ->where($name . ' = :' . $name)
            ->orderBy([$this->identifier() . ' ASC']);

        $bind = $this->scopeByUserId($select, [ $name => $value ]);

        $result = $this->connection->fetchAll($select, $bind);

        if (!empty($result)) {
            $result = $this->withRelations($result);
        }

        return $result;
    }

    /**
     * Ffind all entities.
     *
     * @return array
     */
    public function findAll()
    {
        /**
         * @var $select \Aura\Sql\Query\Select
         */
        $select = $this->connection->newSelect();
        $select->cols(['*'])
            ->from($this->tableName())
            ->orderBy([$this->identifier() . ' ASC']);

        $bind = $this->scopeByUserId($select);

        $result = $this->connection->fetchAll($select, $bind);

        if (!empty($result)) {
            $result = $this->withRelations($result);
        }

        return $result;
    }

    /**
     * Persist entity, do insert if entity has now identifier and update with identifier.
     *
     * @param array $entity
     * @return int last insert id or updated rows
     */
    public function persist(array $entity)
    {
        $entity = $this->validate($entity);
        $id_key = $this->identifier();

        if (isset($entity[$id_key])) {
            $id = $entity[$id_key];
            unset($entity[$id_key]);

            return $this->connection->update($this->tableName(), $entity, $id_key . ' = :id', ['id' => $id]);
        } else {
            $this->connection->insert($this->tableName(), $entity);

            return $this->connection->lastInsertId();
        }
    }

    /**
     * Delete entity.
     *
     * @param $id
     * @return int deleted rows
     */
    public function remove($id)
    {
        return $this->connection->delete($this->tableName(), $this->identifier() . ' = :id', ['id' => $id]);
    }

    /**
     * Scope by user_id
     *
     * @param \Aura\Sql\Query\Select $select
     * @param array $bind
     * @return array bind
     */
    public function scopeByUserId(\Aura\Sql\Query\Select $select, array $bind = [])
    {
        if ($this->userScope) {
            $select->where('user_id = :user_id');
            $bind['user_id'] = $this->userScope;
        }

        return $bind;
    }
}