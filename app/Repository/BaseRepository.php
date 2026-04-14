<?php

namespace App\Repository;

use Illuminate\Support\Facades\Auth;

class BaseRepository
{
    /**
     * @var \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    protected $mainModel;

    /**
     * 返回一个新的查询构造器实例，避免共享状态。
     */
    protected function newQuery()
    {
        if ($this->mainModel instanceof \Illuminate\Database\Eloquent\Model) {
            return $this->mainModel->newQuery();
        }

        return clone $this->mainModel;
    }

    public function query()
    {
        return $this->newQuery();
    }

    public function editById($data, $id)
    {
        // 如果是 DB::table() 而不是 Eloquent 模型，需要手动维护 updated_at
        if (! ($this->mainModel instanceof \Illuminate\Database\Eloquent\Model)) {
            if (! array_key_exists('updated_at', $data) || empty($data['updated_at'])) {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }
            if (! array_key_exists('updated_by', $data) || empty($data['updated_by'])) {
                $data['updated_by'] = Auth::user()->name ?? '';
            }
        }

        return $this->newQuery()->where('id', $id)->update($data);
    }

    public function find($id)
    {
        return $this->newQuery()->find($id);
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        return $this->newQuery()->where($column, $operator, $value, $boolean);
    }

    public function __call($name, $arguments)
    {
        $query = $this->newQuery();

        if (method_exists($query, $name)) {
            return $query->$name(...$arguments);
        }

        throw new \BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $name));
    }
}
