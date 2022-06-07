<?php



class Wxpay
{
    public $appid = '你的appid';
    public $mch_id = '你的商户号';
    public $key = 'api接口的key';

    /**
     * 微信支付
     * @param  integer  $total_fee     总支付金额(单位 元)
     * @param  string   $out_trade_no  内部订单号
     * @param  string   $nonce_str     随机字符串
     * @param  string   $notify_url    回调地址url
     * @return false|mixed
     */
    public function wxpay($total_fee,$out_trade_no,$nonce_str,$notify_url,$i = 0){
        if ($i >= 5){
            return false;
        }
        $total_fee = $total_fee*100;
        $data = [
            'appid'=>$this->appid,
            'mch_id'=>$this->mch_id,
            'nonce_str'=>$nonce_str,
            'body'=>'服务-预约',
            'out_trade_no'=>$out_trade_no,
            'total_fee'=>(int)$total_fee,
            'spbill_create_ip'=>$this->get_ip(),
            'notify_url'=>$notify_url,
            'trade_type'=>'MWEB',
            'scene_info'=>json_encode([
                'h5_info'=>[
                    'type'=>'Wap',
                    'wap_url'=>'',
                    'wap_name'=>''
                ]
            ])
        ];
        $sign = $this->get_sign($data);
        $data['sign'] = $sign;
        $data = $this->array_to_xml($data);
        $return = $this->wxapi($data);
        //加判断
        if ($return['return_code'] == 'SUCCESS' && $return['result_code'] == 'SUCCESS'){
            return $return['mweb_url'];
        }else{
            $i++;
            return $this->wxpay($total_fee,$out_trade_no,$nonce_str,$notify_url,$i);
        }
    }
    //获取随机字符串
    public function get_nonce_str(){
        $str='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890';
        $str = str_shuffle($str);
        $str = substr($str,0,32);
        return $str;
    }
    //获取订单号
    public function get_trade_no(){
        $str = mt_rand(1000,9999);
        $time = substr(microtime(),3,5);
        $orderNum = 'YY'.date("YmdHis",time()).$time.$str;
        return $orderNum;
    }
    //验签
    public function check_sign($data){
        $sign = $data['sign'];
        unset($data['sign']);
        $newSign = $this->get_sign($data);
        if ($sign == $newSign){
            return true;
        }else{
            return false;
        }
    }
    //获取sign签名
    public function get_sign($data){
        $data = $this->pinjie($data);
        $data .= 'key='.$this->key;
        $sign = strtoupper(md5($data));
        return $sign;
    }
    //去空拼接字符串
    public function pinjie($data){
        ksort($data);
        $str = '';
        foreach ($data as $key=>$value){
            if ($value != '' && !is_array($value)){
                $str .= $key.'='.$value.'&';
            }
        }
        return $str;
    }
    //array变成xml
    public function array_to_xml($arr){
        $xml = '<xml>';
        foreach ($arr as $key=>$value){
            if (is_numeric($value)){
                $xml .= '<'.$key.'>'.$value.'</'.$key.'>';
            }else{
                $xml .= '<'.$key.'><![CDATA['.$value.']]></'.$key.'>';
            }
        }
        $xml .='</xml>';
        return $xml;
    }
    //xml变成arr
    public function xml_to_arr($xml){
        libxml_disable_entity_loader(true);
        $arr = json_decode(json_encode(simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA)),true);
        return $arr;
    }
    //发送请求
    public function wxapi($data){
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_URL, $url);
        //验证SSL证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); //严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);


        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        set_time_limit(0);


        //运行curl
        $data = curl_exec($ch);
        $data = $this->xml_to_arr($data);
        //返回结果
        if ($data) {
            curl_close($ch);
            $data = $this->xml_to_arr($data);
            return $data;
        } else {
            curl_close($ch);
            $return = [
                'code'=>0,
                'msg'=>'调起微信支付失败,请重试',
                'data'=>[]
            ];
            return json($return);
        }
    }
    //获取用户ip
    public function get_ip(){
        if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), "unknown"))
            $ip = getenv("HTTP_CLIENT_IP");
        else if (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown"))
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        else if (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown"))
            $ip = getenv("REMOTE_ADDR");
        else if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown"))
            $ip = $_SERVER['REMOTE_ADDR'];
        else
            $ip = "unknown";
        return $ip;
    }
}