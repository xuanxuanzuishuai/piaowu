<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/7/6
 * Time: 3:16 PM
 */

namespace App\Libs;

/**
 * Class ListTree
 * @package App\Libs
 *
 * 用列表构建树形结构，便于查询
 * 数据是只读的，目前不支持修改节点，更改原始数据需要重新调用 $this->makeTree()
 */
class ListTree
{
    public $list;
    public $hash;
    public $tree;
    public $keyName;
    public $parentKeyName;
    public $subNodeKeyName;

    /**
     * ListTree constructor.
     *
     * @param $list
     * @param $keyName
     * @param $parentKeyName
     * @param string $subNodeKeyName
     */
    public function __construct($list, $keyName = 'id', $parentKeyName = 'parent_id', $subNodeKeyName = 'subs')
    {
        $this->list = $list;
        $this->keyName = $keyName;
        $this->parentKeyName = $parentKeyName;
        $this->subNodeKeyName = $subNodeKeyName;

        $this->makeTree();
    }

    /**
     * 构建树结构
     *
     * $this->tree 保存树
     * $this->hash 保存哈希表
     * 两个结构的子节点都引用自原始数据$this->list
     *
     * 数据规则：
     * 原始数据每个节点应包含key和parentKey
     * 原始数据节点key不能为0
     * parentKey=0表示没有父节点
     * 所有没有父节点的节点会放在一个虚拟的根节点下
     * 每个节点会添加名为$this->subNodeKeyName的字段存放所有子节点的引用
     */
    public function makeTree()
    {
        $hashList = [];

        // 虚拟根节点
        $root = [
            $this->keyName => 0,
            $this->parentKeyName => null,
            $this->subNodeKeyName => [],
        ];
        $hashList[0] = &$root;

        foreach ($this->list as &$value) {
            $hashList[$value[$this->keyName]] = &$value;
        }

        foreach ($hashList as &$node) {
            $parentKey = $hashList[$node[$this->keyName]][$this->parentKeyName] ?? null;

            if ($parentKey !== null && !empty($hashList[$parentKey])) {
                $hashList[$parentKey][$this->subNodeKeyName][] = &$node;
            }
        }

        $this->hash = $hashList;
        $this->tree = $root;
    }

    /**
     * 检测key节点是否属于rootKey子树
     *
     * 检查规则：
     * 从key节点想上查找，如果找到rootKey节点则表示包含
     * 0节点一定包含所有节点
     * key与rootKey相同时返回true
     *
     * @param $key
     * @param int $rootKey
     * @return bool
     */
    public function contains($key, $rootKey = 0)
    {
        if ($rootKey == 0) {
            return true;
        }

        if ($key == $rootKey) {
            return true;
        }

        if (empty($this->hash[$key])) {
            return false;
        }
        $node = $this->hash[$key];

        while ($node[$this->parentKeyName] != 0) {
            $pid = $node[$this->parentKeyName];
            if ($pid == $rootKey) {
                return true;
            }

            $node = $this->hash[$pid];
        }

        return false;
    }

    /**
     * 获取某个节点为根的子树的所有节点
     *
     * 从rootKey向下BFS
     *
     * @param int $rootKey
     * @param bool $onlyKey
     * @return array
     */
    public function getChildren($rootKey = 0, $onlyKey = false)
    {
        $node = $this->hash[$rootKey];

        $queue = [$node];
        $children = [];

        while($i = array_shift($queue)) {
            if (empty($i[$this->subNodeKeyName])) {
                continue;
            }

            foreach ($i[$this->subNodeKeyName] as $subNode) {
                array_unshift($queue, $subNode);
                $children[] = $onlyKey ? $subNode[$this->keyName] : $subNode;
            }
        }

        return $children;
    }
}