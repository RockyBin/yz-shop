<?php

namespace App\Modules\ModuleShop\Libs\Member;

use App\Modules\ModuleShop\Libs\Model\MemberRelationLabelModel;
use Illuminate\Support\Facades\DB;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\MemberLabelModel;

/**
 * 会员标签类
 * Class MemberLabel
 * @package App\Modules\ModuleShop\Libs\Member
 */
class MemberLabel
{
    private $siteID = 0; // 站点ID

    /**
     * 初始化
     * MemberLabel constructor.
     * @param int $siteID
     */
    public function __construct($siteID = 0)
    {
        if ($siteID) {
            $this->siteID = $siteID;
        } else {
            $this->siteID = Site::getCurrentSite()->getSiteId();
        }
    }

    public function edit($params)
    {
        try {
            DB::beginTransaction();
            $adminId = $params['admin_id'] ? $params['admin_id'] : 0;
            //要先确认标签是做什么操作,要首先拿到父标签的ID
            if ($params['id']) {
                $parent_id = $params['id'];
                $parentModel = MemberLabelModel::find($parent_id);
                $parentModel->name = $params['name'];
                $parentModel->save();
            } else {
                $parentModel = new MemberLabelModel();
                // 有admin_id
                $parentModel->name = $adminId ? '自定义标签组' : $params['name'];
                if ($adminId) {
                    $customMemberLabel = MemberLabelModel::query()
                        ->where('site_id', $this->siteID)
                        ->where('parent_id', 0)
                        ->where('admin_id', $adminId)
                        ->count();
                    if ($customMemberLabel > 0) {
                        throw new \Exception(trans('已有自定义标签组，不需要再添加'));
                    }
                    $parentModel->admin_id = $adminId;
                }

                $parentModel->site_id = $this->siteID;

                // 拿去当前最大的排序+1
                $maxSort = MemberLabelModel::query()
                    ->where('site_id', $this->siteID)
                    ->where('parent_id', 0)
                    ->where('admin_id', 0)
                    ->max('sort');
                $parentModel->sort = $maxSort + 1;
                $parentModel->save();
                $parent_id = $parentModel->id;
            }
            foreach ($params['children'] as $item) {
                if ($item['id']) {
                    if ($item['is_del']) {
                        MemberLabelModel::query()->where('id', $item['id'])->where('site_id', $this->siteID)->delete();
                        MemberRelationLabelModel::query()->where('site_id', $this->siteID)->where('label_id', $item['id'])->delete();
                    } else {
                        $childrenModel = MemberLabelModel::find($item['id']);
                        $childrenModel->name = $item['name'];
                        $childrenModel->sort = $item['sort'];
                        $childrenModel->save();
                    }
                } else {
                    $childrenModel = new   MemberLabelModel();
                    $childrenModel->name = $item['name'];
                    $childrenModel->sort = $item['sort'];
                    $childrenModel->site_id = $this->siteID;
                    $childrenModel->parent_id = $parent_id;
                    $childrenModel->admin_id = $adminId;
                    $childrenModel->save();
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return makeApiResponseError($e);
        }
    }

    public function getList($params, $page = 1, $pageSize = 20)
    {
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;
        $expression = MemberLabelModel::query()
            ->where('site_id', $this->siteID)
            ->where('parent_id', 0)
            ->where('admin_id', 0)
            ->with(['children' => function ($query) {
                $query->withCount('memberRelationLabel');
                $query->orderBy('sort', 'asc');
            }]);

        if (isset($params['admin_id'])) {
            $expression->where('admin_id', $params['admin_id']);
        }
        $expression->orderBy('sort', 'desc');
        $total = $expression->count();
        if ($params['show_all']) {
            // 显示全部
            $pageSize = $total > 0 ? $total : 1;
            $page = 1;
        }
        $expression->forPage($page, $pageSize);
        $last_page = ceil($total / $pageSize);
        $list = $expression->get();
        foreach ($list as $item) {
            $item->member_relation_label_count = 0;
            if ($item->children) {
                foreach ($item->children as $childrenItem) {
                    $item->member_relation_label_count += $childrenItem->member_relation_label_count;
                }
            }
        }
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    public function getInfo($params)
    {
        $expression = MemberLabelModel::query()
            ->where('site_id', $this->siteID)
            ->where('parent_id', 0)
            ->with(['children' => function ($query) {
                $query->withCount('memberRelationLabel');
                $query->orderBy('sort', 'asc');
            }]);

        if (isset($params['admin_id'])) {
            $expression->where('admin_id', $params['admin_id']);
        }
        return $expression->first();
    }

    public function delete($id)
    {
        try {
            DB::beginTransaction();
            MemberLabelModel::query()->where('id', $id)->where('site_id', $this->siteID)->delete();
            $children = MemberLabelModel::query()->where('parent_id', $id)->where('site_id', $this->siteID)->get();
            foreach ($children as $item) {
                MemberRelationLabelModel::query()->where('site_id', $this->siteID)->where('label_id', $item->id)->delete();
            }
            MemberLabelModel::query()->where('parent_id', $id)->where('site_id', $this->siteID)->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return makeApiResponseError($e);
        }
    }

    public function check($id)
    {
        $ids = myToArray($id);
        if (count($ids) == 1) {
            $model = MemberLabelModel::query()->where('id', $id)->where('site_id', $this->siteID)->first();
            if ($model->parent_id == 0) {
                $ids = MemberLabelModel::query()->where('parent_id', $id)->where('site_id', $this->siteID)->pluck('id')->all();
            }
        }
        $count = MemberRelationLabelModel::query()->where('site_id', $this->siteID)->whereIn('label_id', $ids)->count();

        return $count;
    }

    public function editMemberRelationLabel($member_id, $label_id)
    {
        $labelArray = myToArray($label_id);
        $member = (new MemberModel())::find($member_id);
        $customLabel = $member->label()->where('admin_id', '<>', 0)
            ->where('member_id', $member_id)
            ->pluck('label_id')
            ->all();
        $labelArray = array_merge($labelArray, $customLabel);
        $newLabelArray = [];
        foreach ($labelArray as $key => $item) {
            $newLabelArray[$item] = ['site_id' => Site::getCurrentSite()->getSiteId()];
        }
        $member->label()->sync($newLabelArray);
        return true;
    }

    public function editCrmMemberRelationLabel($member_id, $label_id)
    {
        $labelArray = myToArray($label_id);
        $member = (new MemberModel())::find($member_id);
        foreach ($labelArray as $key => $item) {
            $labelArray[$item] = ['site_id' => Site::getCurrentSite()->getSiteId()];
        }
        $member->label()->sync($labelArray);
        return true;
    }

    public function getMemberRelationLabel($member_id, $adminId = 0)
    {
        return MemberRelationLabelModel::query()
            ->leftJoin("tbl_member_label", "tbl_member_label.id", "tbl_member_relation_label.label_id")
            ->leftJoin("tbl_member_label as plabel", 'tbl_member_label.parent_id', 'plabel.id')
            ->where('tbl_member_relation_label.site_id', $this->siteID)
            ->where('tbl_member_relation_label.member_id', $member_id)
            ->where(function ($query) use ($adminId) {
                $query->orWhere('tbl_member_label.admin_id', 0);
                $query->orWhere('tbl_member_label.admin_id', $adminId);
            })
            ->orderBy('plabel.admin_id', "asc")
            ->orderBy('plabel.sort', "asc")
            ->orderBy('tbl_member_label.sort', 'asc')
            ->select('tbl_member_label.name')
            ->pluck('tbl_member_label.name')
            ->toArray();
    }

    public function getCrmMemberLabel($params)
    {
        $companyExpression = MemberLabelModel::query()
            ->where('site_id', $this->siteID)
            ->where('parent_id', 0)
            ->where('admin_id', 0)
            ->with(['children' => function ($query) use ($params) {
                if ($params['member_id']) {
                    $query->withCount(['memberRelationLabel as check' => function ($subquery) use ($params) {
                        $subquery->where('member_id', $params['member_id']);
                    }]);
                }
                $query->orderBy('sort', 'asc');
            }])
            ->orderBy('sort', 'desc')
            ->get();
        $customExpression = MemberLabelModel::query()
            ->where('site_id', $this->siteID)
            ->where('parent_id', 0)
            ->where('admin_id', $params['admin_id'])
            ->with(['children' => function ($query) use ($params) {
                if ($params['member_id']) {
                    $query->withCount(['memberRelationLabel as check' => function ($subquery) use ($params) {
                        $subquery->where('member_id', $params['member_id']);
                    }]);
                }
                $query->orderBy('sort', 'asc');
            }])
            ->get();
        $data['company_label'] = $companyExpression;
        $data['custom_label'] = $customExpression;
        return $data;
    }

    public function sort(array $label)
    {
        try {
            (new MemberLabelModel())->updateBatch($label);
        } catch (\Exception $e) {
            throw  $e;
        }
    }

    public function addCustomLabel($param)
    {
        try {
            $adminId = $param['admin_id'];
            if ($param['parent_id']) {
                $parent_id = $param['parent_id'];
            } else {
                $parentModel = new MemberLabelModel();
                // 有admin_id
                $parentModel->name = '自定义标签组';

                $customMemberLabel = MemberLabelModel::query()
                    ->where('site_id', $this->siteID)
                    ->where('parent_id', 0)
                    ->where('admin_id', $adminId)
                    ->count();
                if ($customMemberLabel > 0) {
                    throw new \Exception(trans('已有自定义标签组，不需要再添加'));
                }
                $parentModel->admin_id = $adminId;
                $parentModel->site_id = $this->siteID;
                $parentModel->save();
                $parent_id = $parentModel->id;
            }
            $childrenModel = new   MemberLabelModel();
            $childrenModel->name = $param['name'];
            // 拿去当前最大的排序+1
            $maxSort = MemberLabelModel::query()
                ->where('site_id', $this->siteID)
                ->where('parent_id', $parent_id)
                ->where('admin_id', $adminId)
                ->max('sort');
            $childrenModel->sort = $maxSort + 1;
            $childrenModel->site_id = $this->siteID;
            $childrenModel->parent_id = $parent_id;
            $childrenModel->admin_id = $param['admin_id'];
            $childrenModel->save();
        } catch (\Exception $e) {
            throw  $e;
        }
    }
}