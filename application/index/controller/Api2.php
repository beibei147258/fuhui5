<?php
namespace app\index\controller;
use think\Controller;
use think\Db;

class Api extends Controller{

    public function __construct(){
        parent::__construct();

        $this->nowtime = time();
        $minute = date('Y-m-d H:i',$this->nowtime).':00';
        $this->minute = strtotime($minute);

        //指定客户赢利或亏损：
        //array()里面写客户编号，如客户id为1027则写为  array(1027)
        //如多个客户，则以英文逗号分开，如 array(1027,2018,3765)
        //注意一定是英文逗号，中文逗号会报错
        $this->user_win = array();//指定客户赢利
        $this->user_loss = array();//指定客户亏损
        //K线数据库
        $this->klinedata = db('klinedata');
    }

    public function getdate1()
    {
        //产品列表
        $pro = db('productinfo')->where('isdelete',0)->select();
        if(!isset($pro)) return false;
        $nowtime = time();
        $_rand = rand(1,900)/100000;
        $thisdatas = array();
        foreach ($pro as $k => $v) {
            //验证休市
            $isopen = ChickIsOpen($v['pid']);
            if($isopen){
                continue;
            }

            //腾讯证券
            if($v['procode'] == "btc" || $v['procode'] == "ltc"|| $v['procode'] == "eth" || $v['procode'] == "eos"){

                $minute = date('i',$nowtime);
                if($minute >= 0 && $minute < 15){ $minute = 0;}
                elseif($minute >= 15 && $minute < 30){ $minute = 15;}
                elseif($minute >= 30 && $minute < 45){ $minute = 30;}
                elseif($minute >= 45 && $minute < 60){ $minute = 45;}
                $new_date = strtotime(date('Y-m-d H',$nowtime).':'.$minute.':00');

                if($v['procode'] == 'btc'){
                    $url = 'http://api.zb.plus/data/v1/ticker?market=btc_usdt';
                }elseif($v['procode'] == 'ltc'){
                    $url = 'http://api.zb.plus/data/v1/ticker?market=ltc_usdt';
                }elseif($v['procode'] == 'eth'){
                    $url = 'http://api.zb.plus/data/v1/ticker?market=eth_usdt';
                }elseif($v['procode'] == 'eos'){
                    $url = 'http://api.zb.plus/data/v1/ticker?market=eos_usdt';
                }
                $getdata = $this->curlfun($url);
                $res = json_decode($getdata,1);
                $data_arr=$res['ticker'];
                //var_dump($res['ticker']);
                //exit;
                //$getdata = substr($getdata,2,-2);
                //$data_arr = explode(':',$getdata);

                if(!is_array($data_arr)) continue;
                //$Price = explode(',',$data_arr[10]);
                //$Open = explode(',',$data_arr[7]);
                //$Close = explode(',',$data_arr[7]);
                //$High = explode(',',$data_arr[3]);
                //$Low = explode(',',$data_arr[7]);
                $thisdata['Price'] = $this->fengkong($data_arr['sell'],$v);
                $thisdata['Open'] = $data_arr['buy'];
                $thisdata['Close'] = $data_arr['last'];
                $thisdata['High'] = $data_arr['high'];
                $thisdata['Low'] = $data_arr['low'];
                $thisdata['Diff'] = 0;
                $thisdata['DiffRate'] = 0;
                $thisdata['Name'] = $v['ptitle'];


            }elseif(in_array($v['procode'],array("sz399300"))){

                $url = "http://web.sqt.gtimg.cn/q=".$v['procode']."?r=0.".$this->nowtime*88;
                $getdata = $this->curlfun($url);
                $data_arr = explode('~',$getdata);
                $thisdata['Price'] = $data_arr[3];
                $thisdata['Open'] = $data_arr[4];
                $thisdata['Close'] = $data_arr[5];
                $thisdata['High'] = $data_arr[41];
                $thisdata['Low'] = $data_arr[42];
                $thisdata['Diff'] = 0;
                $thisdata['DiffRate'] = 0;

                //比特币

            }elseif(in_array($v['procode'],array(12,13,116))){  	//口袋贵金属
                $url = 'https://m.sojex.net/api.do?rtp=GetQuotesDetail&id='.$v['procode'];
                //$html = file_get_contents($url);
                $html = $this->curlfun($url);

                $res = json_decode($html,1);
                $res = $res['data']['quotes'];

                //$thisdata['Price'] = $this->fengkong($res['buy'],$v);;
                $thisdata['Price'] = $res['buy'];
                $thisdata['Open'] = $res['open'];
                $thisdata['Close'] = $res['last_close'];
                $thisdata['High'] = $res['top'];
                $thisdata['Low'] = $res['low'];
                $thisdata['Diff'] = 0;
                $thisdata['DiffRate'] = 0;

            }elseif(in_array($v['procode'],array('llg','lls'))){
                $url = "https://www.91pme.com/marketdata/gethq?code=".$v['procode'];
                $html = $this->curlfun($url);
                $arr = json_decode($html,1);
                if(!isset($arr[0])) continue;
                $data_arr = $arr[0];

                $thisdata['Price'] = $this->fengkong($data_arr['buy'],$v);;
                $thisdata['Open'] = $data_arr['open'];
                $thisdata['Close'] = $data_arr['lastclose'];
                $thisdata['High'] = $data_arr['high'];
                $thisdata['Low'] = $data_arr['low'];
                $thisdata['Diff'] = 0;
                $thisdata['DiffRate'] = 0;

            }else{
                $url = "http://hq.sinajs.cn/rn=".$nowtime."list=".$v['procode'];

                $getdata = $this->curlfun($url);
                $data_arr = explode(',',$getdata);

                if(!is_array($data_arr) || count($data_arr) != 18) continue;
                //$thisdata['Price'] = $this->fengkong($data_arr[1],$v);
                $thisdata['Price'] = $data_arr[1];
                $thisdata['Open'] = $data_arr[5];
                $thisdata['Close'] = $data_arr[3];
                $thisdata['High'] = $data_arr[6];
                $thisdata['Low'] = $data_arr[7];
                $thisdata['Diff'] = $data_arr[12];
                $thisdata['DiffRate'] = $data_arr[4]/10000;

            }


            $thisdata['Name'] = $v['ptitle'];
            $thisdata['UpdateTime'] = $nowtime;
            $thisdata['pid'] = $v['pid'];

            $thisdatas[$v['pid']] = $thisdata;
            $ids = db('productdata')->where('pid',$v['pid'])->update($thisdata);
        }
        //print_r($thisdatas);
        cache('nowdata',$thisdatas);
    }

    public function getdate()
    {
        //产品列表
        $pro = db('productinfo')->where('isdelete',0)->select();
        if(!isset($pro)) return false;
        $nowtime = time();
        $_rand = rand(1,900)/100000;
        $thisdatas = array();
        foreach ($pro as $k => $v) {
            //验证休市
            $isopen = ChickIsOpen($v['pid']);
            if($isopen){
                continue;
            }
            $getData = $this->getVariation($v);
            echo "<pre>";var_dump($getData);
            $thisdata['Price'] = $getData['Price'];
            $thisdata['Open'] = $getData['Open'];
            $thisdata['Close'] = $getData['Close'];
            $thisdata['High'] = $getData['High'];
            $thisdata['Low'] = $getData['Low'];
            $thisdata['Diff'] = 0;
            $thisdata['DiffRate'] = 0;
            $thisdata['Name'] = $v['ptitle'];
            $thisdata['UpdateTime'] = $nowtime;
            $thisdata['pid'] = $v['pid'];
            $thisdatas[$v['pid']] = $thisdata;
            $ids = db('productdata')->where('pid',$v['pid'])->update($thisdata);
        }
        //print_r($thisdatas);
        cache('nowdata',$thisdatas);
    }

    public function getVariation($value){
        $res = [];
        $product = db('productdata')->field('Price,High,Low')->where('pid',$value['pid'])->find();
        if($product){
            $res['Open'] = $price1 = $price = $product['Price'];
            $rand = mt_rand($value['point_low']*100, $value['point_top']*100)/100;  //根据产品设置的最小和最大生成浮动值
            $rand1 = rand(0,10);
            $num = strlen(intval($price));
            if($num == 1){
                $price1 *= 10000;
                $rand2 = rand(2, 20);
                $res['Close'] = ($price1 + $rand2)/10000;
            }elseif($num == 2){
                $price1 *= 1000;
                $rand2 = rand(2, 50);
                $res['Close'] = ($price1 + $rand2)/1000;
            }elseif($num == 3){
                $price1 *= 100;
                $rand2 = rand(2, 20);
                $res['Close'] = ($price1 + $rand2)/100;
            }else{
                $price1 *= 100;
                $rand2 = rand(5, 100);
                $res['Close'] = ($price1 + $rand2)/100;
            }
            if($rand1 % 2 == 0){
                $price += $rand;
            }else{
                $price -= $rand;
            }
            $High_key = $value['procode'].'_'.$value['pid'].'_High';
            $Low_key = $value['procode'].'_'.$value['pid'].'_Low';
            if(!cache($High_key)){
                cache($High_key, $product['High']);
            }
            if(!cache($Low_key)){
                cache($Low_key, $product['Low']);
            }
            $high = floatval(cache($High_key));
            $low = floatval(cache($Low_key));
            if($price < $high){
                cache($High_key, $high);
            }
            if($price > $low){
                cache($Low_key, $low);
            }
            $res['High'] = cache($High_key);
            $res['Low'] = cache($Low_key);
            $res['Price'] = $price;
        }
        return $res;
    }
    /**
     * 数据风控
     * @author lukui  2017-06-27
     * @param  [type] $price [description]
     * @param  [type] $pro   [description]
     * @return [type]        [description]
     */
    public function fengkong($price,$pro)
    {

        $point_low = $pro['point_low'];
        $point_top = $pro['point_top'];
        $FloatLength = getFloatLength($point_top);
        $jishu_rand = pow(10,$FloatLength);
        $point_low = $point_low * $jishu_rand;
        $point_top = $point_top * $jishu_rand;
        $rand = rand($point_low,$point_top)/$jishu_rand;
        $_new_rand = rand(0,10);
        if($_new_rand % 2 == 0){
            $price = $price + $rand;
        }else{
            $price = $price - $rand;
        }
        return $price;
    }




    //curl获取数据
    public function curlfun($url, $params = array(), $method = 'GET')
    {

        $header = array();
        $opts = array(CURLOPT_TIMEOUT => 10, CURLOPT_RETURNTRANSFER => 1, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_HTTPHEADER => $header);

        /* 根据请求类型设置特定参数 */
        switch (strtoupper($method)) {
            case 'GET' :
                $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
                $opts[CURLOPT_URL] = substr($opts[CURLOPT_URL],0,-1);

                break;
            case 'POST' :
                //判断是否传输文件
                $params = http_build_query($params);
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_POSTFIELDS] = $params;
                break;
            default :

        }

        /* 初始化并执行curl请求 */
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $data = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if($error){
            $data = null;
        }

        return $data;

    }


    function randomFloat($min = 0, $max = 1) {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }

    /**
     * 全局平仓
     * @return [type] [description]
     */
    public function order()
    {
        $nowtime = time();
        $kong_end = getconf('kong_end');
        $kong_end_arr = explode('-',$kong_end );
        if(count($kong_end_arr) == 2){
            $s_rand = rand($kong_end_arr[0],$kong_end_arr[1]);
        }else{
            $s_rand = rand(6,12);
        }

        $db_order = db('order');
        $db_userinfo = db('userinfo');
        //订单列表
        $map['ostaus'] = 0;
        $map['selltime'] = array('elt',$nowtime+$s_rand );
        $_orderlist = $db_order->where($map)->order('selltime asc')->limit('0,50')->select();

        //dump($_orderlist);


        $data_info = db('productinfo');



        //风控参数
        $risk = db('risk')->find();

        //指定盈利id
        $is_win_ids = explode('|',$risk['to_win']);
        //指定亏损id
        $is_loss_ids = explode('|',$risk['to_loss']);

        $randUp = $this->randomFloat($risk['wb'],$risk['wt']);
        $randDown = $this->randomFloat($risk['lb'],$risk['lt']);

        //此刻产品价格
        $p_map['isdelete'] = 0;
        $pro = db('productdata')->field('pid,Price')->where($p_map)->select();
        $prodata = array();
        foreach ($pro as $k => $v) {

            $_pro = cache('nowdata');

            if(!isset($_pro[$v['pid']])){
                $prodata[$v['pid']] = $v['Price'];
                continue;
            }

            //$prodata[$v['pid']] = $this->order_type($_orderlist,$_pro[$v['pid']],$risk,$data_info);
            $prodata[$v['pid']] =  $v['Price'];
            // dump($prodata);
            //echo '---------------------------------------------------<br>';
        }
        //exit;
        //订单列表
        $map1['ostaus'] = 0;
        $map1['buytime'] = array('elt',$nowtime);
        $orderlist = $db_order->where($map1)->limit(0,50)->select();
        if(!$orderlist){
            return '客户请别急，暂无订单！';
        }

        //循环处理订单
        $nowtime = time();
        foreach ($orderlist as $k => $v) {

            $win_ids = in_array((string)$v['uid'],$is_win_ids);
            $loss_ids = in_array((string)$v['uid'],$is_loss_ids);
            $dang_kong = $v['kong_type'];

            //此刻可平仓价位
            $sellprice = isset($prodata[$v['pid']])?$prodata[$v['pid']]:0;
            if($sellprice == 0){
                continue;
            }
            //买入价
            $buyprice = $v['buyprice'];
            $fee = $v['fee'];

            $order_cha = round(floatval($sellprice)-floatval($buyprice),6);

            //优先考虑单控
            if (isset($dang_kong) && ($dang_kong == 1 || $dang_kong == 2 || $dang_kong == 3 || $dang_kong == 4)) {

                //盈利
                if ($dang_kong == 1 || $dang_kong == 3) {
                    $yingli = $v['fee']*($v['endloss']/100);
                    $d_map['is_win'] = 1;
                } else if ($dang_kong == 2 || $dang_kong == 4) {
                    $yingli = -1*$v['fee'];
                    $d_map['is_win'] = 2;
                }
                $yingli = round($yingli,2);

                //平仓增加用户金额
                $u_add = $yingli + $v['fee'];
                $db_userinfo->where('uid',$v['uid'])->setInc('usermoney',$u_add);

                //$d_map['commission'] = $v['commission'] + $yingli + $fee;

                //写入日志
                $this->set_order_log($v,$u_add);

                //平仓处理订单
                $d_map['ostaus'] = 1;
                $d_map['sellprice'] = $sellprice;
                $d_map['ploss'] = $yingli;
                $d_map['oid'] = $v['oid'];
                db('order')->update($d_map);

            } else {

                //买涨
                if($v['ostyle'] == 0 && $nowtime >= $v['selltime']){
                    if($order_cha > 0){  //盈利
                        if ($loss_ids) {
                            $yingli = -1*$v['fee'];
                            $d_map['is_win'] = 2;
                        } else {
                            $yingli = $v['fee']*($v['endloss']/100);
                            $d_map['is_win'] = 1;
                        }
                        $yingli = round($yingli,2);

                        //平仓增加用户金额
                        //$u_add = $yingli + $fee;
                        $u_add = $yingli + $v['fee'];
                        $db_userinfo->where('uid',$v['uid'])->setInc('usermoney',$u_add);

                        //$d_map['commission'] = $v['commission'] + $yingli + $fee;

                        //写入日志
                        $this->set_order_log($v,$u_add);


                    }elseif($order_cha < 0){	//亏损
                        if ($win_ids) {
                            $yingli = $v['fee']*($v['endloss']/100);
                            $d_map['is_win'] = 1;
                        } else {
                            $yingli = -1*$v['fee'];
                            $d_map['is_win'] = 2;
                        }
                        $yingli = round($yingli,2);

                        //$u_add = $yingli + $fee;
                        $u_add = $yingli + $v['fee'];
                        $db_userinfo->where('uid',$v['uid'])->setInc('usermoney',$u_add);


                        //$d_map['commission'] = $v['commission'] + $yingli + $fee;
                        $this->set_order_log($v,0);

                    }else{		//无效
                        $yingli = 0;
                        $d_map['is_win'] = 3;

                        //平仓增加用户金额
                        $u_add = $fee;
                        $db_userinfo->where('uid',$v['uid'])->setInc('usermoney',$u_add);
                        //写入日志
                        $this->set_order_log($v,$u_add);
                    }

                    //平仓处理订单
                    $d_map['ostaus'] = 1;
                    $d_map['sellprice'] = $sellprice;
                    $d_map['ploss'] = $yingli;
                    $d_map['oid'] = $v['oid'];
                    db('order')->update($d_map);


                    //买跌
                }elseif($v['ostyle']==1 && $nowtime >= $v['selltime']){



                    if($order_cha < 0){  //盈利
                        //$yingli = $v['fee']*($v['endloss']/100);
                        if ($loss_ids) {
                            $yingli = -1*$v['fee'];
                            $d_map['is_win'] = 2;
                        } else {
                            $yingli = $v['fee']*($v['endloss']/100);
                            $d_map['is_win'] = 1;
                        }

                        $yingli = round($yingli,2);

                        //平仓增加用户金额
                        $u_add = $yingli + $fee;
                        $db_userinfo->where('uid',$v['uid'])->setInc('usermoney',$u_add);

                        //$d_map['commission'] = $v['commission'] + $yingli + $fee;
                        //写入日志
                        $this->set_order_log($v,$u_add);


                    }elseif($order_cha > 0){	//亏损
                        if ($win_ids) {
                            $yingli = $v['fee']*($v['endloss']/100) - $v['fee'];
                            $d_map['is_win'] = 1;
                        } else {
                            $yingli = -1*$v['fee'];
                            $d_map['is_win'] = 2;
                        }

                        $yingli = round($yingli,2);

                        //$yingli = -1*$v['fee'];

                        //$d_map['commission'] = $v['commission'] + $yingli + $fee;
                        $this->set_order_log($v,0);

                        $u_add = $yingli + $fee;
                        $db_userinfo->where('uid',$v['uid'])->setInc('usermoney',$u_add);

                    }else{		//无效
                        $yingli = 0;
                        $d_map['is_win'] = 3;

                        //平仓增加用户金额
                        $u_add = $fee;
                        $db_userinfo->where('uid',$v['uid'])->setInc('usermoney',$u_add);
                        //写入日志
                        $this->set_order_log($v,$u_add);
                    }

                    //平仓处理订单
                    $d_map['ostaus'] = 1;
                    $d_map['sellprice'] = $sellprice;
                    $d_map['ploss'] = $yingli;
                    $d_map['oid'] = $v['oid'];
                    $db_order->update($d_map);

                }
            }

        }

    }



    /**
     * 写入平仓日志
     * @author lukui  2017-07-01
     * @param  [type] $v        [description]
     * @param  [type] $addprice [description]
     */
    public function set_order_log($v,$addprice)
    {
        $o_log['uid'] = $v['uid'];
        $o_log['oid'] = $v['oid'];
        $o_log['addprice'] = $addprice;
        $o_log['addpoint'] = 0;
        $o_log['time'] = time();
        $o_log['user_money'] = db('userinfo')->where('uid',$v['uid'])->value('usermoney');
        db('order_log')->insert($o_log);

        //资金日志
        set_price_log($v['uid'],1,$addprice,'结单','订单到期获利结算',$v['oid'],$o_log['user_money']);
    }


    /**
     * 订单类型
     * @param  [type] $orders [description]
     * @return [type]         [description]
     */
    public function order_type($orders,$pro,$risk,$data_info){

        $_prcie = $pro['Price'];

        $pid = $pro['pid'];

        //此产品购买人数
        $price_num = 0;
        //买涨金额，计算过盈亏比例以后的
        $up_price = 0;
        //买跌金额，计算过盈亏比例以后的
        $down_price = 0;
        //买入最低价
        $min_buyprice = 0;
        //买入最高价
        $max_buyprice = 0;
        //下单最大金额
        $max_fee = 0;
        //指定客户亏损
        $to_win = explode('|',$risk['to_win']);
        $to_win = array_filter(array_merge($to_win,$this->user_win));
        $is_to_win = array();
        //指定客户亏损
        $to_loss = explode('|',$risk['to_loss']);
        $to_loss = array_filter(array_merge($to_loss,$this->user_loss));
        $is_to_loss = array();

        $i = 0;

        foreach ($orders as $k => $v) {

            if($v['pid'] == $pid ){
                //没超过最小风控值直接退出price
                if ($v['fee'] < $risk['min_price']) {
                    //return $pro['Price'];
                }
                $i++;

                //单控 赢利
                if($v['kong_type'] == '1' || $v['kong_type'] == '3'){
                    $dankong_ying = $v;
                    break;
                }

                //单控 亏损
                if($v['kong_type'] == '2'){

                    $dankong_kui = $v;
                    break;
                }
                //dump($v['kong_type']);
                //是否存在指定盈利
                if(in_array($v['uid'], $to_win)){
                    $is_to_win = $v;
                    break;

                }
                //是否存在指定亏损
                if(in_array($v['uid'], $to_loss)){
                    $is_to_loss = $v;
                    break;

                }

                //总下单人数
                $price_num++;
                //买涨买跌累加
                if($v['ostyle'] == 0){
                    $up_price += $v['fee']*$v['endloss']/100;
                }else{
                    $down_price += $v['fee']*$v['endloss']/100;
                }
                //统计最大买入价与最大下单价
                if($i == 1){
                    $min_buyprice = $v['buyprice'];
                    $max_buyprice = $v['buyprice'];
                    $max_fee = $v['fee'];
                }else{
                    if($min_buyprice > $v['buyprice']){
                        $min_buyprice = $v['buyprice'];

                    }
                    if($max_buyprice < $v['buyprice']){
                        $max_buyprice = $v['buyprice'];
                    }
                    if($max_fee < $v['fee']){
                        $max_fee = $v['fee'];
                    }
                }
            }

        }


        $proinfo = $data_info->where('pid',$pro['pid'])->find();
        //根据现在的价格算出风控点
        $FloatLength = getFloatLength((float)$pro['Price']);

        if($FloatLength == 0){
            $FloatLength = getFloatLength($proinfo['point_top']);
        }

        //是否存在指定盈利
        $is_do_price = 0; 	//是否已经操作了价格

        $jishu_rand = pow(10,$FloatLength);
        $beishu_rand = rand(1,10);

        $data_rands = $data_info->where('pid',$pro['pid'])->value('rands');

        $data_randsLength = getFloatLength($data_rands);
        if($data_randsLength > 0){
            $_j_rand = pow(10,$data_randsLength)*$data_rands;
            $_s_rand = rand(1,$_j_rand)/pow(10,$data_randsLength);

        }else{
            $_s_rand = 0;

        }


        $do_rand = $_s_rand;

        //先考虑单控
        if(!empty($dankong_ying) && $is_do_price == 0){ 		//单控 1赢利
            if($dankong_ying['ostyle'] == 0 ){
                $pro['Price'] = $v['buyprice'] + $do_rand;
            }elseif($dankong_ying['ostyle'] == 1 ){
                $pro['Price'] = $v['buyprice'] - $do_rand;
            }
            $is_do_price = 1;
            //echo 1;
        }

        if(!empty($dankong_kui) && $is_do_price == 0){ 		//单控 2亏损
            if($dankong_kui['ostyle'] == 0  ){
                $pro['Price'] = $v['buyprice'] - $do_rand;
            }elseif($dankong_kui['ostyle'] == 1 ){
                $pro['Price'] = $v['buyprice'] + $do_rand;
            }

            //echo 2;
            $is_do_price = 1;
        }

        //指定客户赢利
        if(!empty($is_to_win) && $is_do_price == 0){

            if($is_to_win['ostyle'] == 0 ){
                $pro['Price'] = $v['buyprice'] + $do_rand;
            }elseif($is_to_win['ostyle'] == 1 ){
                $pro['Price'] = $v['buyprice'] - $do_rand;
            }
            $is_do_price = 1;
            ////echo 1;
            //echo 3;

        }
        //是否存在指定亏损
        if(!empty($is_to_loss) && $is_do_price == 0){


            if($is_to_loss['ostyle'] == 0 ){
                $pro['Price'] = $v['buyprice'] - $do_rand;
            }elseif($is_to_loss['ostyle'] == 1 ){
                $pro['Price'] = $v['buyprice'] + $do_rand;
            }
            $is_do_price = 1;
            //echo 4;
        }
        //没有任何下单记录
        if($up_price == 0 && $down_price == 0 && $is_do_price == 0){
            $is_do_price = 2;
            //return $pro['Price'];
        }

        //只有一个人下单，或者所有人下单买的方向相同
        if(( ($up_price == 0 && $down_price != 0) || ($up_price != 0 && $down_price == 0) )  && $is_do_price == 0 ){

            //风控参数
            $chance = $risk["chance"];
            $chance_1 = explode('|',$chance);
            $chance_1 = array_filter($chance_1);
            //循环风控参数
            if(count($chance_1) >= 1){
                foreach ($chance_1 as $key => $value) {
                    //切割风控参数 $arr_1[0]未切割的金额范围 $arr_1[1]盈亏概率
                    $arr_1 = explode(":",$value);
                    //金额范围
                    $arr_2 = explode("-",$arr_1[0]);
                    //比较最大买入价格
                    if($max_fee >= $arr_2[0] && $max_fee < $arr_2[1]){
                        //得出风控百分比 未设置
                        if(!isset($arr_1[1])){
                            //未设置盈亏概率，默认未30%
                            $chance_num = 30;
                        }else{
                            //有设置盈亏概率，按设置的来
                            $chance_num = $arr_1[1];
                        }

                        //随机概率
                        $_rand = rand(1,100);
                        continue;

                    } else {
                        //设置 止盈止损 客户盈亏
                    }

                }
            }




            //买涨
            if(isset($_rand) && $up_price != 0){

                if($_rand > $chance_num){	//客损
                    $pro['Price'] = $min_buyprice-$do_rand;

                    $is_do_price = 1;
                    //echo 5;
                }else{		//客赢
                    $pro['Price'] = $max_buyprice+$do_rand;
                    $is_do_price = 1;
                }

            }

            if(isset($_rand) && $down_price != 0){

                if($_rand > $chance_num){	//客损
                    $pro['Price'] = $max_buyprice+$do_rand;
                    $is_do_price = 1;
                }else{		//客赢
                    $pro['Price'] = $min_buyprice-$do_rand;
                    $is_do_price = 1;
                }

            }
        }


        //多个人下单，并且所有人下单买的方向不相同
        if($up_price != 0 && $down_price != 0  && $is_do_price == 0){

            //买涨大于买跌的
            if ($up_price > $down_price) {
                $pro['Price'] = $min_buyprice-$do_rand;
                $is_do_price = 1;

            }
            //买涨小于买跌的
            if ($up_price < $down_price) {
                $pro['Price'] = $max_buyprice+$do_rand;

                $is_do_price = 1;
            }
            if ($up_price == $down_price) {
                $is_do_price = 2;
            }

        }



        if($is_do_price == 2 || $is_do_price == 0){
            $pro['Price'] = $this->fengkong($pro['Price'],$proinfo);
        }
        //if( $pid == 12) dump($pro['Price']);

        db('productdata')->where('pid',$pro['pid'])->update($pro);

        //存储k线值
        $k_map['pid'] = $pro['pid'];
        $k_map['ktime'] = $this->minute;

        return $pro['Price'];

    }

    public function curl_get($url) {
        $ch = curl_init();
        $timeout = 10;
        curl_setopt ($ch, CURLOPT_URL,$url);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        return $data;
    }


    /**
     * 获取K线数据
     * @author lukui  2017-07-01
     * @return [type] [description]
     */
    public function getkdata($pid=null,$num=null,$interval=null,$isres=null)
    {

        $pid = empty($pid)?input('param.pid'):$pid;
        $num = empty($num)?input('param.num'):$num;
        $num = empty($num)?30:$num;
        $pro = GetProData($pid);
        $all_data = array();

        if(!$pro){
            exit;
        }
        $interval = empty($interval)?input('param.interval'):$interval;
        $interval = input('param.interval') ? input('param.interval') : 1;
        $nowtime = time().rand(100,999);

        //数据库里的产品K线参考值
        $klength = $interval*60*$num;
        if($klength == 'd') $klength = 1*60*24*$num;

        $k_map['pid'] = $pid;
        $k_map['ktime'] = array('between',array( ($this->nowtime - $klength),$this->nowtime ) );
        /*
        $kline = $this->klinedata->where($k_map)->select();
        foreach ($kline as $k => $v) {
            $_kline[$v['ktime']] = $v;
        }*/

        $pro['procode']=GetProcode($pid);

//print_r($pro);
        //  exit($pro['procode']);
        //guonei
        if($pro['procode'] == "btc" || $pro['procode'] == "ltc"|| $pro['procode'] == "eth"|| $pro['procode'] == "eos"){

            switch ($interval) {
                case '1':
                    $datalen = "1min";
                    break;
                case '5':
                    $datalen = "5min";
                    break;
                case '15':					$datalen = "15min";					break;
                case '30':					$datalen = "30min";					break;
                case '60':					$datalen = "1hour";					break;
                case 'd':					$datalen = "1day";					break;
                default:
                    exit;
                    break;
            }
            //////////////////star
            if($pro['procode'] == "btc"){
                $geturl = "http://api.zb.plus/data/v1/kline?market=btc_usdt&type=".$datalen."&size=".$num;
            }elseif($pro['procode'] == "ltc"){
                $geturl = "http://api.zb.plus/data/v1/kline?market=ltc_usdt&type=".$datalen."&size=".$num."&contract_type=this_week";
            }elseif($pro['procode'] == "eth"){
                $geturl = "http://api.zb.plus/data/v1/kline?market=eth_usdt&type=".$datalen."&size=".$num;
            }elseif($pro['procode'] == "eos"){
                $geturl = "http://api.zb.plus/data/v1/kline?market=eos_usdt&type=".$datalen."&size=".$num;
            }
            //$html = file_get_contents($geturl);
            $html = $this->curl_get($geturl);
            $html = substr($html,25,-22);

            $_data_arr = explode('],[',$html);
            //var_dump($_data_arr);
//exit;
            foreach ($_data_arr as $k => $v) {
                $_son_arr = explode(',', $v);
                $res_arr[] = array($_son_arr[0]/1000,$_son_arr[1],$_son_arr[4],$_son_arr[3],$_son_arr[2]);
            }
            ////////////////end

            /*************黄金白银K线数据开始*****************/

        }elseif(in_array($pro['procode'],array("sz399300"))){
            if($interval == 'd'){
                $url = "http://web.ifzq.gtimg.cn/appstock/app/fqkline/get?_var=kline_dayqfq&param=".$pro['procode'].",day,,,$num,qfq&r=0.".$this->nowtime;
            }else{
                $url = "http://ifzq.gtimg.cn/appstock/app/kline/mkline?param=".$pro['procode'].",m$interval,,$num&_var=m".$interval."_today&r=0.".$this->nowtime;;
            }
            $res = $this->curlfun($url);
            $arr1 = explode('=',$res);
            $arr2 = json_decode($arr1[1],1);
            if($interval == 'd'){
                $arr3 = $arr2["data"][$pro['procode']]['day'];
            }else{
                $arr3 = $arr2["data"][$pro['procode']]['m'.$interval];
            }

            foreach($arr3 as $k=>$v){
                $_time = strtotime($v[0]);

                $res_arr[] = array($_time,$v[1],$v[2],$v[3],$v[4]);
            }

        }elseif(in_array($pro['procode'],array('llg','lls'))){
            if($interval == 'd') $interval = 1440;
            $geturl = "https://hq.91pme.com/query/kline?callback=jQuery183014447531082730047_".$nowtime."&code=".$pro['procode']."&level=".$interval."&maxrecords=".$num."&_=".$nowtime;

            $html = $this->curlfun($geturl);
            $str_1 = explode('[{',$html);
            if(!isset($str_1[1])){
                return;
            }
            $str_2 = substr($str_1[1],0,-4);
            $str_3 = explode('},{',$str_2);

            krsort($str_3);

            foreach ($str_3 as $k => $v) {

                $_son_arr = explode(',', $v);

                $_time = substr($_son_arr[11],7,-3);
                if(in_array($interval,array(1,5)) && isset($_kline[$_time])){
                    $_h = $_kline[$_time]['updata'];
                    $_l = $_kline[$_time]['downdata'];
                    $_o = $_kline[$_time]['opendata'];
                    $_c = $_kline[$_time]['closdata'];
                }else{
                    $_h = substr($_son_arr[4],6,-1);
                    $_l = substr($_son_arr[3],7,-1);
                    $_o = substr($_son_arr[10],7,-1);
                    $_c = substr($_son_arr[0],8,-1);
                }

                $res_arr[] = array($_time,$_o,$_c,$_h,$_l);


            }




        }else{

            switch ($interval) {
                case '1':
                    $datalen = 1440;
                    break;
                case '5':
                    $datalen = 1440;
                    break;
                case '15':
                    $datalen = 480;
                    break;
                case '30':
                    $datalen = 240;
                    break;
                case '60':
                    $datalen = 120;
                    break;
                case 'd':

                    break;

                default:
                    exit;
                    break;
            }

            $year = date('Y_n_j',time());
            if(in_array($pro['procode'],array(13,12,116))){
                if($interval == 1) $interval =1;
                if($interval == 5) $interval =2;
                if($interval == 15) $interval =3;
                if($interval == 30) $interval =4;
                if($interval == 60) $interval =5;
                if($interval == 'd') $interval =6;

                $geturl = 'https://m.sojex.net/api.do?rtp=CandleStick&type='.$interval.'&qid='.$pro['procode'];



            }else{
                if($interval == 'd'){

                    $geturl = "http://vip.stock.finance.sina.com.cn/forex/api/jsonp.php/var%20_".$pro['procode']."$year=/NewForexService.getDayKLine?symbol=".$pro['procode']."&_=$year";
                }else{
                    $geturl = "http://vip.stock.finance.sina.com.cn/forex/api/jsonp.php/var%20_".$pro['procode']."_".$interval."_$nowtime=/NewForexService.getMinKline?symbol=".$pro['procode']."&scale=".$interval."&datalen=".$datalen;
                }
            }



            $html = $this->curlfun($geturl);


            if($interval == 'd'){
                $_arr = explode('("',$html);
                if(!isset($_arr[1])){
                    return;
                }
                $_str = substr($_arr[1],1,-4);
                $_data_arr = explode(',|',$_str);

            }else{
                $_arr = explode('[',$html);
                if(!isset($_arr[1])){
                    return;
                }
                $_str = substr($_arr[1],1,-3);
                $_data_arr = explode('},{',$_str);

            }

            $_count = count($_data_arr);
            $_data_arr = array_slice($_data_arr,$_count-$num,$_count);







            foreach ($_data_arr as $k => $v) {

                $_son_arr = explode(',', $v);

                if($interval == 'd'){
                    $res_arr[] = array(
                        substr($_son_arr[0],5),
                        $_son_arr[1],
                        $_son_arr[4],
                        $_son_arr[2],
                        $_son_arr[3],

                    );
                }else{
                    if(in_array($pro['procode'],array(13,12,116))){
                        if($interval == 6){
                            $_ktime = substr($_son_arr[1],5,-1).' 00:00:00';
                        }else{
                            $_ktime = '2017-'.substr($_son_arr[1],5,-1);
                        }

                        $_time = $_ktime;
                        if(in_array($interval,array(1,5)) && isset($_kline[$_time])){
                            $_h = $_kline[$_time]['updata'];
                            $_l = $_kline[$_time]['downdata'];
                            $_o = $_kline[$_time]['opendata'];
                            $_c = $_kline[$_time]['closdata'];
                        }else{
                            $_h = substr($_son_arr[4],5,-1);
                            $_l = substr($_son_arr[2],5,-1);
                            $_o = substr($_son_arr[3],5,-1);
                            $_c = substr($_son_arr[3],5,-1);
                        }

                        $res_arr[] = array(
                            strtotime($_ktime),
                            $_o,
                            $_c,
                            $_l,
                            $_h,

                        );

                    }else{
                        $_time = strtotime(substr($_son_arr[0],5,-1));
                        if(in_array($interval,array(1,5)) && isset($_kline[$_time])){
                            $_h = $_kline[$_time]['updata'];
                            $_l = $_kline[$_time]['downdata'];
                            $_o = $_kline[$_time]['opendata'];
                            $_c = $_kline[$_time]['closdata'];
                        }else{
                            $_h = substr($_son_arr[3],5,-1);
                            $_l = substr($_son_arr[2],5,-1);
                            $_o = substr($_son_arr[1],5,-1);
                            $_c = substr($_son_arr[4],5,-1);
                        }
                        $res_arr[] = array($_time,$_o,$_c,$_h,$_l);

                    }

                }


            }



            //dump($res_arr);

            //$res_arr[$num] = array(date('H:i:s',$pro['UpdateTime']),$pro['Price'],$pro['Open'],$pro['Close'],$pro['Low']);





        }



        if($pro['Price'] < $res_arr[$num-1][1]){
            $_state = 'down';
        }else{
            $_state = 'up';
        }


        $all_data['topdata'] = array(
            'topdata'=>strtotime("now"),
            'now'=>$pro['Price'],
            'open'=>$pro['Open'],
            'lowest'=>$pro['Low'],
            'highest'=>$pro['High'],
            'close'=>$pro['Close'],
            'state'=>$_state

        );

        $all_data['items'] = $res_arr;
        if($isres){
            return (json_encode($all_data));
        }else{
            exit(json_encode(base64_encode(json_encode($all_data))));
        }


    }

    //test web data
    public function setusers()
    {
        test_web();
    }



    public function getprodata()
    {


        $pid = input('param.pid');

        $pro = GetProData($pid);


        if(!$pro){
            //echo 'data error!';
            exit;
        }

        $topdata = array(
            'topdata'=>$pro['UpdateTime'],
            'now'=>$pro['Price'],
            'open'=>$pro['Open'],
            'lowest'=>$pro['Low'],
            'highest'=>$pro['High'],
            'close'=>$pro['Close']

        );

        //exit(json_encode($topdata));
        exit(json_encode(base64_encode(json_encode($topdata))));

    }




    /**
     * 分配订单
     * @return [type] [description]
     */
    public function allotorder()
    {
        //查找以平仓未分配的订单  isshow字段
        $map['isshow'] = 0;
        $map['ostaus'] = 1;
        $map['selltime'] = array('<',time()-300);
        $list = db('order')->where($map)->limit(0,10)->select();

        if(!$list){
            return false;
        }

        foreach ($list as $k => $v) {
            //分配金额
            $this->allotfee($v['uid'],$v['fee'],$v['is_win'],$v['oid'],$v['ploss']);
            //更改订单状态
            db('order')->where('oid',$v['oid'])->update(array('isshow'=>1));

        }
    }



    public function allotfee($uid,$fee,$is_win,$order_id,$ploss)
    {
        $userinfo = db('userinfo');
        $allot = db('allot');
        $nowtime = time();

        $user = $userinfo->field('uid,oid')->where('uid',$uid)->find();
        $myoids = myupoid($user['oid']);



        if(!$myoids){
            return;
        }

        //红利
        $_fee = 0;
        //佣金
        $_feerebate = 0;
        //手续费
        $web_poundage = getconf('web_poundage');
        //分配金额
        if($is_win == 1){
            $pay_fee = $ploss;
        }elseif($is_win == 2){
            $pay_fee = $fee;
        }else{
            //20170801 edit
            return;
        }


        foreach ($myoids as $k => $v) {

            if($user['oid'] == $v['uid']){	//直接推荐者拿自己设置的比例


                $_fee = round($pay_fee * ($v["rebate"]/100),2);
                $_feerebate = round($fee*$web_poundage/100 * ($v["feerebate"]/100),2);
                echo $_feerebate;

            }else{		//他上级比例=本级-下级比例

                $_my_rebate = ($v["rebate"] - $myoids[$k-1]["rebate"]);

                if($_my_rebate < 0) $_my_rebate = 0;
                $_fee = round($pay_fee * ( $_my_rebate /100),2);

                $_my_feerebate = ($v["feerebate"]  - $myoids[$k-1]["feerebate"] );
                if($_my_feerebate < 0) $_my_feerebate = 0;
                $_feerebate = round($fee*$web_poundage/100 * ( $_my_feerebate /100),2);


            }


            //红利
            if($is_win == 1){	//客户盈利代理亏损
                if($_fee != 0){
                    $ids_fee = $userinfo->where('uid',$v['uid'])->setDec('usermoney', $_fee);
                }else{
                    $ids_fee = null;
                }

                $type = 2;
                $_fee = $_fee*-1;
            }elseif($is_win == 2){	//客户亏损代理盈利
                if($_fee != 0){
                    $ids_fee = $userinfo->where('uid',$v['uid'])->setInc('usermoney', $_fee);
                }else{
                    $ids_fee = null;
                }

                $type = 1;
            }elseif($is_win == 3){	//无效订单不做操作
                $ids_fee = null;
            }

            if($ids_fee){
                //余额
                $nowmoney = $userinfo->where('uid',$v['uid'])->value('usermoney');
                set_price_log($v['uid'],$type,$_fee,'对冲','下线客户平仓对冲',$order_id,$nowmoney);

            }

            //手续费
            if($_feerebate != 0){
                $ids_feerebate = $userinfo->where('uid',$v['uid'])->setInc('usermoney', $_feerebate);
            }else{
                $ids_feerebate = null;
            }

            if($ids_feerebate){
                //余额
                $nowmoney = $userinfo->where('uid',$v['uid'])->value('usermoney');
                set_price_log($v['uid'],1,$_feerebate,'客户手续费','下线客户下单手续费',$order_id,$nowmoney);

            }

        }

    }



    /**
     * 获取K线。缓存起来
     * @author lukui  2017-08-13
     * @return [type] [description]
     */
    public function cachekline()
    {

        $pro = db('productinfo')->field('pid')->where('isdelete',0)->select();
        $kline = cache('cache_kline');
        foreach ($pro as $k => $v) {

            $res[$v['pid']][1] = $this->getkdata($v['pid'],60,1,1);
            if(!$res[$v['pid']][1]) $res[$v['pid']][1] = $kline[$v['pid']][1] ;
            $res[$v['pid']][5] = $this->getkdata($v['pid'],60,5,1);
            if(!$res[$v['pid']][5]) $res[$v['pid']][5] = $kline[$v['pid']][5] ;
            $res[$v['pid']][15] = $this->getkdata($v['pid'],60,15,1);
            if(!$res[$v['pid']][15]) $res[$v['pid']][15] = $kline[$v['pid']][15] ;
            $res[$v['pid']][30] = $this->getkdata($v['pid'],60,30,1);
            if(!$res[$v['pid']][30]) $res[$v['pid']][30] = $kline[$v['pid']][30] ;
            $res[$v['pid']][60] = $this->getkdata($v['pid'],60,60,1);
            if(!$res[$v['pid']][60]) $res[$v['pid']][60] = $kline[$v['pid']][60] ;
            $res[$v['pid']]['d'] = $this->getkdata($v['pid'],60,'d',1);
            if(!$res[$v['pid']]['d']) $res[$v['pid']]['d'] = $kline[$v['pid']]['d'] ;
        }



        cache('cache_kline',$res);

    }

    function getkline(){

        $kline = cache('cache_kline');
        $pid = input('pid');
        $interval = input('interval');

        if(!$interval || !$pid){
            return false;
        }

        $info = json_decode($kline[$pid][$interval],1);

        return exit(json_encode($info));;

    }



    public function checkbal(){
        $lttime = $this->nowtime-10*60;
        $map['bptime'] = array('lt',$lttime);
        $map['pay_type'] = array('in',array('ysy_alwap','ysy_wxwap'));
        $map['bptype'] = 3;
        $db_balance = db('balance');
        $list = $db_balance->where($map)->select();

        if(!$list) return false;

        foreach($list as $key=>$val){

            $miyao="5ca7b74af0d54b2483c1a9e75bb935fd";
            $mchntid = '308652650940006';
            $inscd = '93081888';

            $data = array();
            $qxdata = array();
            $data['version'] = "2.2";
            $data['signType'] = 'SHA256';
            $data['charset'] = 'utf-8';
            $data['origOrderNum'] = $val['balance_sn'];
            $data['busicd'] = 'INQY';
            //$data['respcd'] = '00';
            $data['inscd'] = $inscd;
            $data['mchntid'] = $mchntid;
            $data['terminalid'] = $inscd;
            $data['txndir'] = 'Q';
            ksort($data);
            $str = '';
            foreach($data as $k=>$v){
                if($str!=''){
                    $str .= '&';
                }
                $str .= $k.'='.$v;
            }
            $str.= $miyao;
            $sign=hash("sha256", $str);
            $data['sign'] = $sign;
            $data=json_encode($data);
            $pc = json_decode($this->post_curl('https://showmoney.cn/scanpay/unified',$data),true);


            if($pc['errorDetail']=='待买家支付'){

                $qxdata['busicd'] = 'CANC';
                $qxdata['charset'] = 'utf-8';
                $qxdata['inscd'] = $inscd;
                $qxdata['mchntid'] = $mchntid;
                $qxdata['orderNum'] = time().rand(1000,9999);
                $qxdata['origOrderNum'] = $val['balance_sn'];
                $qxdata['signType'] = 'SHA256';
                $qxdata['terminalid'] = $inscd;
                $qxdata['txndir'] = 'Q';
                $qxdata['version'] = '2.2';
                ksort($qxdata);
                $str = '';
                foreach($qxdata as $k=>$v){
                    if($str!=''){
                        $str .= '&';
                    }
                    $str .= $k.'='.$v;
                }
                $str.= $miyao;
                $qxdata['sign'] = hash("sha256", $str);
                $qpc = json_decode($this->post_curl('https://showmoney.cn/scanpay/unified',json_encode($qxdata)),true);




            }elseif($pc['errorDetail']=='成功'){

            }elseif($pc['errorDetail']=='订单不存在'){

            }

            $_map['bpid'] = $val['bpid'];
            $_map['bptype'] = 4;
            $db_balance->update($_map);

        }


    }

    public function post_curl($url,$data){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            print curl_error($ch);
        }
        curl_close($ch);
        return $result;
    }

    /**
     * 实时盈亏
     */
    public function inline() {
        $uid = $_SESSION['uid'];
        $update_time = time();
        $online = input('online');
        $data = $data = ['uid' => $uid,'updatime'=>$update_time];

        $il = db('inline')->where('uid',$uid)->find();

        if (isset($il)) {
            db('inline')->where('uid',$uid)->update($data);
        } else {
            if (isset($online)) {
                db('inline')->insert($data);
                return WPreturn("在线",1);
            } else {
                return WPreturn("失败",2);
            }
        }
    }

    public function kf_link() {
        $kf = \db('config')->where('name','kfs')->find();
        return $kf['value'];
    }


}


?>