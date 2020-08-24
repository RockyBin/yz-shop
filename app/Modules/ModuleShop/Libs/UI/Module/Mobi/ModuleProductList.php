<?php
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;

use App\Modules\ModuleShop\Libs\Link\LinkHelper;
use App\Modules\ModuleShop\Libs\Model\ProductClassModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Product\Product;
use YZ\Core\Constants;

/**
 * 商品列表模块
 * Class ModuleProductList
 * @package App\Modules\ModuleShop\Libs\UI\Module\Mobi
 */
class ModuleProductList extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    public function __construct($idOrRow = 0)
    {
        parent::__construct($idOrRow);
    }

    /**
     * 设置数据来源
     * @param $source , 0=商品分类，1=手动选择
     */
    public function setParam_DataSource($source)
    {
        $this->_moduleInfo['param_data_source'] = $source;
    }

    /**
     * 获取数据来
     * @return mixed
     */
    public function getParam_DataSource()
    {
        return $this->_moduleInfo['param_data_source'];
    }

    /**
     * 设置分类ID
     * @param $ids , 当 DataSource 为 0 时，记录商品分类的ID
     */
    public function setParam_ClassIds($ids)
    {
        $this->_moduleInfo['param_class_ids'] = $ids;
    }

    /**
     * 获取分类ID
     * @return mixed
     */
    public function getParam_ClassIds()
    {
        return $this->_moduleInfo['param_class_ids'];
    }

    /**
     * 设置商品ID
     * @param $ids , 当 DataSource 为 1 时，记录商品的ID
     */
    public function setParam_ProductIds($ids)
    {
        $this->_moduleInfo['param_product_ids'] = $ids;
    }

    /**
     * 获取商品ID
     * @return mixed
     */
    public function getParam_ProductIds()
    {
        return $this->_moduleInfo['param_product_ids'];
    }

    /**
     * 设置排序字段
     * @param $rule , 记录商品的排序规则
     */
    public function setParam_SortRule($rule)
    {
        $this->_moduleInfo['param_sort_rule'] = $rule;
    }

    /**
     * 获取排序字段
     * @return mixed
     */
    public function getParam_SortRule()
    {
        return $this->_moduleInfo['param_sort_rule'];
    }

    /**
     * 设置商品个数
     * @param $num , 当 DataSource 为 0 时，记录商品的列出个数
     */
    public function setParam_ProductNum($num)
    {
        $this->_moduleInfo['param_product_num'] = $num;
    }

    /**
     * 获取商品个数
     * @return mixed
     */
    public function getParam_ProductNum()
    {
        return $this->_moduleInfo['param_product_num'];
    }

    /**
     * 设置商品样式
     * @param $style
     */
    public function setParam_ProductStyle($style)
    {
        $this->_moduleInfo['param_product_style'] = $style;
    }

    /**
     * 获取商品样式
     * @return mixed
     */
    public function getParam_ProductStyle()
    {
        return $this->_moduleInfo['param_product_style'];
    }

    /**
     * 设置字体样式
     * @param $style
     */
    public function setParam_FontStyle($style)
    {
        $this->_moduleInfo['param_font_style'] = $style;
    }

    /**
     * 获取字体样式
     * @return mixed
     */
    public function getParam_FontStyle()
    {
        return $this->_moduleInfo['param_font_style'];
    }

    /**
     * 设置商品间距
     * @param $margin
     */
    public function setParam_ProductMargin($margin)
    {
        $this->_moduleInfo['param_product_margin'] = $margin;
    }

    /**
     * 获取商品间距
     * @return mixed
     */
    public function getParam_ProductMargin()
    {
        return $this->_moduleInfo['param_product_margin'];
    }

    /**
     * 设置商品边框样式
     * @param $style,0 = 直角，1 = 圆角
     */
    public function setParam_BorderStyle($style)
    {
        $this->_moduleInfo['param_border_style'] = $style;
    }

    /**
     * 获取商品边框样式
     * @return mixed
     */
    public function getParam_BorderStyle()
    {
        return $this->_moduleInfo['param_border_style'];
    }

    /**
     * 设置商品显示内容
     * @param $params,数组
     */
    public function setParam_ShowParams($params)
    {
        $this->_moduleInfo['param_show_params'] = $params;
    }

    /**
     * 获取商品显示内容
     * @return mixed
     */
    public function getParam_ShowParams()
    {
        return $this->_moduleInfo['param_show_params'];
    }

    /**
     * 设置购买按钮样式
     * @param $cornerMark
     */
    public function setParam_BuyBtnStyle($style)
    {
        $this->_moduleInfo['param_btn_buy_style'] = $style;
    }

    /**
     * 获取购买按钮样式
     * @return mixed
     */
    public function getParam_BuyBtnStyle()
    {
        return $this->_moduleInfo['param_btn_buy_style'];
    }

    /**
     * 设置角标样式类型
     * @param $cornerMark
     */
    public function setParam_CornerMark($cornerMark)
    {
        $this->_moduleInfo['param_corner_mark'] = $cornerMark;
    }

    /**
     * 获取角标样式类型
     * @return mixed
     */
    public function getParam_CornerMark()
    {
        return $this->_moduleInfo['param_corner_mark'];
    }

    /**
     * 设置自定义角标样式
     * @param $cornerMark，当角标类型选择自定义时，记录自定义的角标的路径
     */
    public function setParam_CustomCornerMark($cornerMark)
    {
        $this->_moduleInfo['param_custom_corner_mark'] = $cornerMark;
    }

    /**
     * 获取自定义角标样式
     * @return mixed
     */
    public function getParam_CustomCornerMark()
    {
        return $this->_moduleInfo['param_custom_corner_mark'];
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info)
    {
        $this->setParam_DataSource($info['data_source']);
        $this->setParam_ClassIds($info['class_ids']);
        $this->setParam_ProductIds($info['product_ids']);
        $this->setParam_ProductNum($info['product_num']);
        $this->setParam_SortRule($info['sort_rule']);
        $this->setParam_ProductStyle($info['product_style']);
        $this->setParam_ProductMargin($info['product_margin']);
        $this->setParam_BorderStyle($info['border_style']);
        $this->setParam_FontStyle($info['font_style']);
        $this->setParam_ShowParams($info['show_params']);
        $this->setParam_BuyBtnStyle($info['btn_buy_style']);
        $this->setParam_CornerMark($info['corner_mark']);
        $this->setParam_CustomCornerMark($info['custom_corner_mark']);
        parent::update($info);
    }

    /**
     * 渲染模块
     */
    public function render()
    {
        $context = [];
        $context['layout'] = $this->getLayout();
        $context['padding_left_right'] = $this->getPaddingLeftRight();
        $context['data_source'] = $this->getParam_DataSource();
        $context['class_ids'] = $this->getParam_ClassIds();
        $context['product_ids'] = $this->getParam_ProductIds();
        $context['product_num'] = $this->getParam_ProductNum();
        $context['sort_rule'] = $this->getParam_SortRule();
        $context['product_style'] = $this->getParam_ProductStyle();
        $context['product_margin'] = $this->getParam_ProductMargin();
        $context['border_style'] = $this->getParam_BorderStyle();
        $context['font_style'] = $this->getParam_FontStyle();
        $context['show_params'] = $this->getParam_ShowParams();
        $context['btn_buy_style'] = $this->getParam_BuyBtnStyle();
        $context['corner_mark'] = $this->getParam_CornerMark();
        $context['custom_corner_mark'] = $this->getParam_CustomCornerMark();
        //读取分类信息(后台页面编辑时用到)
        if ($context['class_ids']) {
            $list = ProductClassModel::whereIn('id', $context['class_ids'])->select('class_name')->orderByRaw("FIELD(id,".implode(",", $context['class_ids']).")")->get();
            foreach ($list as $item) {
                $context['class_name'][] = $item->class_name;
            }
        }
        $context['product_list'] = null;
        // 只查找出售中的商品
        $param['status'] = Constants::Product_Status_Sell;
        $param['merge_sold_count'] = 1;
        $param['view_perm'] = 1; //限制浏览权限
        if ($context['data_source'] == 1 && is_array($context['product_ids']) && count($context['product_ids']) > 0) {
            $param['product_ids'] = $context['product_ids'];
            $param['order_by'] = ['raworder' => "FIELD(tbl_product.id,".implode(",", $context['product_ids']).")"];
            $data = Product::getList($param, 1, 1000);
            $context['product_list'] = $data['list'];
        } elseif ($context['data_source'] == 0 && is_array($context['class_ids']) && count($context['class_ids']) > 0) {
            $param['class'] = $context['class_ids'];
            if ($context['sort_rule']) {
                $param['order_by'] = ['raworder' => 'sort desc,'.$context['sort_rule']]; //强制加上按排序字段值排序
            }
            $data = Product::getList($param, 1, $context['product_num']);
            $context['product_list'] = $data['list'];
        } elseif ($context['data_source'] == -1) {
            // 来源为所有商品
            if ($context['sort_rule']) {
                $param['order_by'] = ['raworder' => 'sort desc,'.$context['sort_rule']]; //强制加上按排序字段值排序
            }
            $data = Product::getList($param, 1, $context['product_num']);
            $context['product_list'] = $data['list'];
        }
        unset($item);
        //直接输出两个空的用于前台页面编辑时的列表，如果在前端动态生成 product_list1 等节点，会导致 v-model 监听不动变化
        $context['product_list1'] = [];
        $context['product_list2'] = [];
        return $this->renderAct($context);
    }
}
