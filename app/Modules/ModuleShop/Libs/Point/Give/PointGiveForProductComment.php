<?php

namespace App\Modules\ModuleShop\Libs\Point\Give;

use YZ\Core\Constants;

/**
 * 评论商品送积分
 * Class PointGiveForProductComment
 * @package App\Modules\ModuleShop\Libs\Point\Give
 */
class PointGiveForProductComment extends AbstractPointGive
{
    protected $statusColumnName = 'in_product_comment_status';
    protected $pointColumnName = 'in_product_comment_point';
    private $commentId = 0; // 评论id

    /**
     * 初始化
     * PointGiveForProductComment constructor.
     * @param $memberModal
     * @param $commentModelOrId
     */
    public function __construct($memberModal, $commentModelOrId)
    {
        parent::__construct($memberModal);
        if (is_numeric($commentModelOrId)) {
            $this->commentId = intval($commentModelOrId);
        }
    }

    /**
     * 自定义规则
     * @return bool|mixed
     */
    protected function customRule()
    {
        // 数值正常
        if (!$this->commentId) return false;

        // 这里要验证评论是否存在，且 验证是否对商品已做过评论

        // 验证是否已经赠送过
        $total = $this->point->count([
            'member_id' => $this->member->getMemberId(),
            'in_out_type' => Constants::PointInOutType_ProductComment,
            'in_id' => $this->commentId
        ]);
        if ($total > 0) return false;

        return true;
    }

    /**
     * 评论赠送积分
     * @return bool|mixed|null
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    protected function addPointHandle()
    {
        return $this->point->add([
            'member_id' => $this->member->getMemberId(),
            'in_out_type' => Constants::PointInOutType_ProductComment,
            'in_out_id' => $this->commentId,
            'point' => $this->getPoint(),
            'about' => '评论商品',
            'terminal_type' => $this->getTerminalType(),
            'status' => Constants::PointStatus_Active,
        ]);
    }
}