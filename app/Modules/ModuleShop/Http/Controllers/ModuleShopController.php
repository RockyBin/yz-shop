<?php

namespace App\Modules\ModuleShop\Http\Controllers;

use App\Modules\ModuleShop\Libs\Shop\NormalShopOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ModuleShopController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        echo "当前网站ID：" . \YZ\Core\Site\Site::getCurrentSite()->getSiteId()."<br>";
		event(new \App\Modules\ModuleShop\Events\TestEventTrigger('testevent'))."<br>";
        return view('moduleshop::index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        return view('moduleshop::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param  Request $request
     * @return Response
     */
    public function store(Request $request)
    {
    }

    /**
     * Show the specified resource.
     * @return Response
     */
    public function show()
    {
        return view('moduleshop::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @return Response
     */
    public function edit()
    {
        return view('moduleshop::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param  Request $request
     * @return Response
     */
    public function update(Request $request)
    {
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy()
    {
    }

    public function test()
    {
        //$list = \App\Modules\ModuleShop\Libs\Distribution\Distributor::getList(['member_id' => 69,'return_parent_info' => 1,'return_total_record' => 1,'return_buy_times' => 1,'return_commission_money' => 1,'buy_time_min' => 1]);
        //print_r($list);myexit();
        
        //$distributor = new \App\Modules\ModuleShop\Libs\Distribution\Distributor(69);
        //$info = $distributor->getInfo(['return_parent_info' => 1,'return_bind_weixin' => 1]);
        //return $info;

        //$m = new \YZ\Core\Member\Member(77);
        //$m->setParent(75);

        //$m = new \App\Modules\ModuleShop\Libs\Member\Member();
        //echo $m->getTotalTeam(69);

        //$distributor = new \App\Modules\ModuleShop\Libs\Distribution\Distributor(69);
        //$distributor->upgrade();

        //$dd = new \App\Modules\ModuleShop\Libs\Distribution\Distribution();
        //$c = \App\Modules\ModuleShop\Libs\Distribution\DistributionConfig::getGlobalDistributionConfig();
        //$parents = $dd->calDistributionMoney(77,200,100,$c);
        //print_r($parents);

        /*
        $c = new \App\Modules\ModuleShop\Libs\Shop\PointDeductionConfig();
        $c->enable = true;
        $c->max = 50;
        $c->ratio = 20;
        $c->moneyUnit = 100;

        $d = new \App\Modules\ModuleShop\Libs\Shop\Discount();
        $res = $d->calMoney(5000,100*100,20*100,$c);
        return $res;
        */

        /*$pro = new \App\Modules\ModuleShop\Libs\Shop\NormalShopProduct();
        $pro->couponMoney = 0;
        $pro->costMoney = 1000;
        $pro->money = 2000;
        $pro->num = 1;
        return $pro->calDistribution(77);
        return $pro->calPoint(10000,5000);*/

        if($_REQUEST['cancel']){ //测试取消订单
            $order = \App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory::createOrderByOrderId('201901170944209992');
            $order->cancel();
            echo 'cancel ok';
            myexit();
        }

        if($_REQUEST['finish']){ //测试完成订单
            $order = \App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory::createOrderByOrderId('201901171523019975');
            $order->finish();
            echo 'finish ok';
            myexit();
        }

        if($_REQUEST['pay']){ //测试支付订单
            $order = \App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory::createOrderByOrderId('201901171523019975');
            $order->pay([]);
            echo 'pay ok';
            myexit();
        }

        $pro = new \App\Modules\ModuleShop\Libs\Shop\NormalShopProduct(15,0,2);
        echo "价格：".$pro->calPrice(77)."<br>";
        echo "运费：".$pro->calFreight(440600)."<br>";
        echo "是否可以使用积分：".$pro->canUsePoint()."<br>";
        echo "是否可以使用优惠券：".json_encode($pro->canUseCoupon(9999,19))."<br>";
        echo "是否可以购买：".json_encode($pro->canBuy(0,99))."<br>";

        echo "订单：<hr>";
        $pro2 = new \App\Modules\ModuleShop\Libs\Shop\NormalShopProduct(16,48,2);
        $order = new NormalShopOrder(77);
        $order->addProduct($pro);
        $order->addProduct($pro2);
        $order->setAddressId(1);
        $order->setCouponID(19);
        echo "价格：".$order->calProductMoney()."<br>";
        echo "运费：".$order->calFreight()."<br>";
        echo "是否可以用优惠券：".$order->canUseCoupon(19)."<br>";
        echo "优惠券抵扣结果：".$order->calCoupon()."<br>";
        echo "积分抵扣结果：".json_encode($order->calPoint())."<br>";
        echo "最终需支付金额：".$order->getTotalMoney()."<br>";
        echo "分佣情况：".json_encode($order->calDistribution())."<br>";
        //echo json_encode($order->save());
    }
}
