<?php
/**
 *--------------------------------------------------------------------------------------
 * iZhanCMS 爱站内容管理系统 微信支付
 *
 * JsModel.php
 *
 * @filename   WeixinModel.php  UTF-8
 * @copyright  Copyright (c) 2004-2012 Mainone Technologies Inc. (http://www.b2b.cn)
 * @license    http://www.izhancms.com/license/   iZhanCMS 1.0
 * @version    iZhanCMS 1.0
 * @link       http://www.izhancms.com
 * @link       http://www.b2b.cn
 * @since      1.0.0
 *-------------------------------------------------------------------------------------
 */
class JsModel extends Model{
    public $tableName = 'member';	
    private $unifieUrl = "https://api.mch.weixin.qq.com/pay/unifiedorder";
	private $appid = 'wxcb192ad927b18849';  //wxcc6ec6007df61024
    private $mch_id = '1439057502';
	private $key = '0192023a7bbd73250516f069df18b500';
    private $AppSecret = 'f47fec664680f89c02d9aebf6bc7187d';
    private $curl_timeout = 5;	
	/**
	 * 
	 * 统一下单，WxPayUnifiedOrder中out_trade_no、body、total_fee、trade_type必填
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param  array $inputObj 数组
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
 	public function unifiedOrder($out_trade_no,$total_fee,$ip,$openid,$body){
	  $info = array();
	  $info['appid'] = $this->appid;
	  $info['mch_id'] = $this->mch_id;
	  $info['nonce_str'] = $this->getNonceStr();//随机字符串，不长于32位。推荐随机数生成算法
	  $info['body'] = $body; //商品描述
	  $info['device_info'] = 'WEB'; //设备号
	  $info['out_trade_no'] = $out_trade_no;//商户系统内部的订单号,32个字符内
	  $info['fee_type'] = 'CNY';//商户系统内部的订单号,32个字符内
	  $info['total_fee'] = $total_fee * 100;//订单总金额，单位为分
	  $info['spbill_create_ip'] = $ip;//用户端实际ip
	  $info['trade_type'] = 'JSAPI';//接收微信支付异步通知回调地址
	  $info['notify_url'] = 'http://hbcjrfljjh.var365.cn/index.php/goods/Payment/notify';//支付通知
	  $info['openid'] = $openid;//支付类型
	  $sign = $this->MakeSign($info);
	  $info['sign'] = $sign;
	  $xml = $this->ToXml($info);
	  $response = $this->postXmlCurl($xml, $this->unifieUrl,60);
	  return $this->responseFormate($response);
	}
     /**
	 * 
	 * 获取jsapi支付的参数
	 * @param array $UnifiedOrderResult 统一支付接口返回的数据
	 * @throws WxPayException
	 * 
	 * @return json数据，可直接填入js函数作为参数
	 */
    public function GetJsApiParameters($prepay_id){
       $payinfo = array(
         'appId'=>$this->appid,
         'timeStamp'=>" ".time(),
         'nonceStr'=>$this->getNonceStr(),
         'package'=>'prepay_id='.$prepay_id,
         'signType'=>'MD5',
      );
      $payinfo['paySign'] = $this->MakeSign($payinfo);
      return json_encode($payinfo);
    }
	 /**
	 * 格式化统一下单返回的信息 获取 预支付交易会话标识 prepay_id
	 * @param array $urlObj
	 * @return 返回已经拼接好的字符串
	 */
	public function responseFormate($response){
	  if(!$response) return array();	
	  $values = $this->xmlToArray($response);
	  if($values['return_code'] != 'SUCCESS'){
		 return array();
	  }elseif($values['return_code'] == 'SUCCESS'){
		$sign = $this->MakeSign($values);
		if($values['sign'] != $sign){
		  return array();	
		}else{
		  return $values;	
		}
	  }
	}
	//生成Sign签名
	public function MakeSign($input){
		ksort($input);
		$string = $this->ToUrlParams($input);
		$string = $string . "&key=".$this->key;
		$string = md5($string);
		$result = strtoupper($string);
		return $result;
   }
    /**
	 * 
	 * 拼接签名字符串
	 * @param array $urlObj
	 * @return 返回已经拼接好的字符串
	 */
	private function ToUrlParams($urlObj){
		$buff = "";
		foreach ($urlObj as $k => $v)
		{
			if($k != "sign"){
				$buff .= $k . "=" . $v . "&";
			}
		}
		
		$buff = trim($buff, "&");
		return $buff;
	}

   /**
	 * 获取随机的字符串
	 * @param array $urlObj
	 * @return 返回已经拼接好的字符串
	 */	
    private function getNonceStr($length = 32) 
	{
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";  
		$str ="";
		for ( $i = 0; $i < $length; $i++ )  {  
			$str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
		} 
		return $str;
	}
	
	/**
     * 将xml转为array
     * @param string $xml
     * @throws WxPayException
     */
	public function xmlToArray($xml)
	{	
		if(!$xml){
		  return array();
		}
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);		
		return $values;
	}
	/**
	 * 输出xml字符
	 * @throws WxPayException
	**/
	public function ToXml($input){
		if(!is_array($input) || count($input) <= 0){
    	  return '';
    	}
    	$xml = "<xml>";
    	foreach ($input as $key=>$val){
    		if (is_numeric($val)){
    			$xml.="<".$key.">".$val."</".$key.">";
    		}else{
    			$xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
    		}
        }
        $xml.="</xml>";
        return $xml; 
	}
	
		 /**
	 * 以post方式提交xml到对应的接口url
	 * 
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @throws WxPayException
	 */
	private function postXmlCurl($xml, $url,$second = 30)
	{

        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

        //运行curl
		$data = curl_exec($ch);
		//返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else { 
			$error = curl_errno($ch);
			curl_close($ch);
			return '';
		}
	}
	
	/**
 	 * 支付结果通用通知
 	 * @param function $notify
 	 */
	public function notify()
	{
		//获取通知的数据
		$xml = file_get_contents("php://input");
		$result = $this->responseFormate($xml);
	    return $result;
	}
	
	/**
 	 * 获取用户的OPENID
 	 * @param function $notify
 	 */
	 public function getOpenId(){
		if (!isset($_GET['code'])){
			$baseUrl = urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].$_SERVER['QUERY_STRING']);
			$url = $this->__CreateOauthUrlForCode($baseUrl);
			Header("Location: $url");
			exit();
		} else {
		    $code = trim($_GET['code']);
			$openid = $this->getOpenidFromMp($code);
			return $openid;
		} 
	 }
	 
	/**
	 * 
	 * 构造获取code的url连接
	 * @param string $redirectUrl 微信服务器回跳的url，需要url编码
	 * 
	 * @return 返回构造好的url
	 */ 
	private function __CreateOauthUrlForCode($redirectUrl)
	{
		$urlObj["appid"] = $this->appid;
		$urlObj["redirect_uri"] = "$redirectUrl";
		$urlObj["response_type"] = "code";
		$urlObj["scope"] = "snsapi_base";
		$urlObj["state"] = "STATE"."#wechat_redirect";
		$bizString = $this->ToUrlParams($urlObj);
		return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
	}
	
	/**
	 * 
	 * 通过code从工作平台获取openid机器access_token
	 * @param string $code 微信跳转回来带上的code
	 * 
	 * @return openid
	 */
	public function GetOpenidFromMp($code)
	{
		$url = $this->__CreateOauthUrlForOpenid($code);
		//初始化curl
		$ch = curl_init();
		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->curl_timeout);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		//运行curl，结果以jason形式返回
		$res = curl_exec($ch);
		curl_close($ch);
		//取出openid
		$data = json_decode($res,true);
		$this->data = $data;
		$openid = $data['openid'];
		return $openid;
	}
	
	/**
	 * 
	 * 构造获取open和access_toke的url地址
	 * @param string $code，微信跳转带回的code
	 * 
	 * @return 请求的url
	 */
	private function __CreateOauthUrlForOpenid($code)
	{
		$urlObj["appid"] = trim($this->appid);
		$urlObj["secret"] = trim($this->AppSecret);
		$urlObj["code"] = trim($code);
		$urlObj["grant_type"] = "authorization_code";
		$bizString = $this->ToUrlParams($urlObj);
		return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
	}


    public function isWeixin(){
      return strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ? true : false;
    }
	
}