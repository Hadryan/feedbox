<?php
namespace Nogo\Feedbox\Repository;

/**
 * Class Item
 * @package Nogo\Feedbox\Repository
 */
class Item extends AbstractUserAwareRepository
{
    const ID = 'id';
    const TABLE = 'items';

    protected $filter = array(
        'id' => FILTER_VALIDATE_INT,
        'source_id' => FILTER_VALIDATE_INT,
        'user_id' => FILTER_VALIDATE_INT,
        'read' => array(
            'filter' => FILTER_CALLBACK,
            'options' => array('Nogo\Feedbox\Helper\Validator', 'datetime')
        ),
        'starred' => FILTER_VALIDATE_BOOLEAN,
        'title' => FILTER_SANITIZE_STRING,
        'pubdate' => array(
            'filter' => FILTER_CALLBACK,
            'options' => array('Nogo\Feedbox\Helper\Validator', 'datetime')
        ),
        'content' => FILTER_UNSAFE_RAW,
        'uid' => FILTER_SANITIZE_STRING,
        'uri' => FILTER_VALIDATE_URL,
        'created_at'  => array(
            'filter' => FILTER_CALLBACK,
            'options' => array('Nogo\Feedbox\Helper\Validator', 'datetime')
        ),
        'updated_at' => array(
            'filter' => FILTER_CALLBACK,
            'options' => array('Nogo\Feedbox\Helper\Validator', 'datetime')
        )
    );

    public function identifier()
    {
        return self::ID;
    }

    public function tableName()
    {
        return self::TABLE;
    }

    public function validate(array $entity)
    {
        return filter_var_array($entity, $this->filter, false);
    }

    public function withRelations(array $entities)
    {
        return $entities;
    }

    public function findAllFiltered(array $filter = array(), $count = false)
    {
        /**
         * @var $select \Aura\Sql\Query\Select
         */
        $select = $this->connection->newSelect();

        $select
            ->from($this->tableName());

        if ($count) {
            $select->cols(['count(*)']);
        } else {
            $select->cols(['*']);
        }

        if (!$count) {
            if (isset($filter['page']) && isset($filter['limit'])) {
                $select->setPaging(intval($filter['limit']));
                $select->page(intval($filter['page']));
            }
        }

        $bind = $this->scopeByUserId($select);
        foreach ($filter as $key => $value) {
            if ($value === null) {
                continue;
            }

            switch ($key) {
                case 'mode':
                    $value = filter_var($value, FILTER_SANITIZE_STRING);
                    switch($value) {
                        case 'unread':
                            $select->where('read IS NULL');
                            break;
                        case 'read':
                            $select->where('read IS NOT NULL');
                            break;
                        case 'starred':
                            $select->where('starred = 1');
                            break;
                    }
                    break;
                case 'search':
                    $value = filter_var($value, FILTER_SANITIZE_STRING);
                    if ($value) {
                        $select->where('title LIKE :value OR content LIKE :value');
                        $bind['value'] = '%' . $value . '%';
                    }
                    break;
                case 'sortby':
                    switch ($value) {
                        case 'oldest':
                            $select->orderBy(['pubdate ASC', 'id ASC']);
                            break;
                        case 'newest':
                            $select->orderBy(['pubdate DESC', 'id DESC']);
                            break;
                    }
                    break;
                case 'tag':
                    $value = filter_var($value, FILTER_VALIDATE_INT);
                    if ($value) {
                        $sources = $this->connection->fetchCol("SELECT id FROM sources WHERE tag_id = :tag_id", ['tag_id' => $value]);
                        if (!empty($sources)) {
                            $bind['source_list'] = $sources;
                            $select->where('source_id IN (:source_list)');
                        }
                    }
                    break;
                case 'source':
                    $value = filter_var($value, FILTER_VALIDATE_INT);
                    if ($value) {
                        $bind['source'] = $value;
                        $select->where('source_id = :source');
                    }
                    break;
            }
        }

        return $this->connection->fetchAll($select, $bind);
    }

    /**
     * Find all double uids
     * @param int $source_id filter double uids by source (default: 0)
     * @return array
     */
    public function findDoubleUid($source_id = 0)
    {
        $source_id = filter_var($source_id, FILTER_VALIDATE_INT);
        /**
         * @var $select \Aura\Sql\Query\Select
         */
        $select = $this->connection->newSelect();
        $select->cols(['uid', 'count(*) as doubleCount'])
            ->from($this->tableName())
            ->groupBy(['uid'])
            ->having("(doubleCount > 1)");

        $bind = $this->scopeByUserId($select);
        if ($source_id) {
            $select->where('source_id = :source_id');
            $bind['source_id'] = $source_id;
        }

        return $this->connection->fetchCol($select, $bind);
    }

    /**
     * Delete uid
     * @param $uid
     * @return int count how many
     */
    public function deleteUid($uid)
    {
        if (!empty($uid)) {
            /**
             * @var $delete \Aura\Sql\Query\Delete
             */
            $delete = $this->connection->newDelete();
            $delete->from($this->tableName());
            $bind = ['uid' => $uid];
            if (is_array($uid)) {
                $delete->where('uid IN (:uid)');
            } else {
                $delete->where('uid = :uid');
            }
            $bind = $this->scopeByUserId($delete, $bind);

            return $this->connection->query($delete, $bind);
        }
        return 0;
    }

    public function countSourceUnread(array $sourceIds = array())
    {
        /**
         * @var $select \Aura\Sql\Query\Select
         */
        $select = $this->connection->newSelect();
        $select->cols(['count(*)'])
            ->from($this->tableName())
            ->where('read IS NULL');

        $bind = $this->scopeByUserId($select);
        if (!empty($sourceIds)) {
            $select->where('source_id IN (:source_id)');
            $bind['source_id'] = $sourceIds;
        }

        return $this->connection->fetchValue($select, $bind);
    }

    public function countTagUnread($tag)
    {
        $sources = $this->connection
            ->fetchCol("SELECT id FROM sources WHERE tag_id = :tag_id", ['tag_id' => $tag]);

        return $this->countSourceUnread($sources);
    }
}