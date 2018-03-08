<?php
/**
 *--------------------------------------------------------------------------------------
 * 
 *
 * PaymentController.php
 *
 * 支付详细类————前台
 *
 * @author     尤开利 573358951@qq.com
 * @copyright  Copyright (c) 2004-2014 Mainone Technologies Inc. (http://www.b2b.cn)
 * @link       http://www.b2b.cn
 * @since      3.0.0
 *-------------------------------------------------------------------------------------
 */
class PaymentController extends HomeController {
	
	private $PaymentModel;//支付工具类
	
	public function init() {
		$this->PaymentModel = M('Payment');//建立支付工具类
		parent::init();
		include "/app/web/dd20170122110845884/vendor/payment/WxPayPubHelper/WxPayPubHelper.php";
	}
	
	public function payAction() {
        $id_temp = $this->getParams('orderId', '');
        $id = rc4($id_temp, 'DECODE', 'MAINONE');
        $order = M('orders')->where(array('id' => $id, 'order_status' => 2, 'pay_status' => 0, 'compbig' => array('current_debt' => 0.00)))->getOne();
		if (empty($order)) {//如果订单信息为空
       	   $this->showMessage("订单已经支付，返回首页",URL_HOST,3);//进行提示
		}
        $pay_id = isset($order['pay_method']) ? $order['pay_method'] : 0;
        $total_fee = isset($order['current_debt']) ? $order['current_debt'] : 0.00;
        $goodsName = '';
        $orderGoods = M('orderGoods')->select(array('where' => array('order_id' => $id)));
        if ($orderGoods) {
            foreach ($orderGoods as $goods) {
                if ($goodsName) {
                    $goodsName .= ',';
                }
                $goodsName .= $goods['product_name'];
            }
        }
		if ($pay_id == 1) {//支付宝支付
			alipay($order['order_sn'] , $total_fee, $goodsName);
		} elseif ($pay_id == 2) {//财付通支付
			tenpay($id_temp ,$total_fee);
		} elseif ($pay_id == 3) {//快钱支付
			bill($id_temp ,$total_fee, $goodsName);	
		} elseif ($pay_id == 6) {//银联在线
			upop($id_temp , $total_fee , $goodsName);
		} elseif ($pay_id == 7) {//手机支付宝支付
			mobile_alipay($order['order_sn'] , $total_fee, $goodsName);
		}
	}
	
	public function testAction() {	    
	    mobile_alipay(1 ,0.01, '支付');
	}
	
	public function returnurlAction() {
		$pay_id = $this->getParams('payid', 0);//获得支付方式类型
		
		if ($pay_id == 1) {//支付宝支付
		 
		
			
		} elseif ($pay_id == 2) {//财付通支付
			$tenpay_info =  M('Payment')->where(array('pay_id'=>'2'))->select();//得到财付通的相关信息
			$tenpay_info = $tenpay_info[0];//财付通信息，以数组元素1为准
			
			/* 商户号 */
			//$bargainor_id = "1900000109";
			$bargainor_id = $tenpay_info['partner_id'];
			
			/* 密钥 */
			//$key = "8934e7d15453e97507ef794cf7b0519d";
			$key = $tenpay_info['key'];
			
			/* 创建支付应答对象 */
			$resHandler = new PayResponseHandler();
			$resHandler->setKey($key);
			
			//判断签名
			if($resHandler->isTenpaySign()) {
			
				//交易单号
				$transaction_id = $resHandler->getParameter("transaction_id");
			
				//金额,以分为单位
				$total_fee = $resHandler->getParameter("total_fee");
			
				//支付结果
				$pay_result = $resHandler->getParameter("pay_result");
				
				//订单ID
				$order_id = isset($_GET['orderid'])?$_GET['orderid']:'';//加密的
				$order_id = rc4($order_id, 'DECODE', 'MAINONE');//解密
			
				if( "0" == $pay_result ) {
						
					//调用doShow, 打印meta值跟js代码,告诉财付通处理成功,并在用户浏览器显示$show页面.
					//$show = "http://localhost/tenpay/show.php";
					//$resHandler->doShow($show);
					
					D('Order', 'User', 'home')->paySuccess ($order_id);//支付成功后订单状态修改
					$url = Cookie::get('HTTP_REFERER');// 获取来源页面
					$this->showMessage("支付成功" , $url , 3);//跳转到商家页面
			
				} else {
					//当做不成功处理
					$url = Cookie::get('HTTP_REFERER');// 获取来源页面
					$this->showMessage("支付失败" , $url , 3);//跳转到商家页面
				}
			
			} else {
				//echo "<br/>" . "认证签名失败" . "<br/>";
				$url = Cookie::get('HTTP_REFERER');// 获取来源页面
				$this->showMessage("认证签名失败" , $url , 3);//跳转到商家页面
			}							
		} elseif ($pay_id == 3) {//快钱支付
			
			$bill_info =  M('Payment')->where(array('pay_id'=>'3'))->select();//得到财付通的相关信息
			$bill_info = $bill_info[0];//财付通信息，以数组元素1为准
			
			//设置人民币网关密钥
	       ///区分大小写
			$key = $bill_info['key'];//商户密钥
			
				//人民币网关账号，该账号为11位人民币网关商户编号+01,该值与提交时相同。
			$kq_check_all_para=kq_ck_null($_REQUEST['merchantAcctId'],'merchantAcctId');
			//网关版本，固定值：v2.0,该值与提交时相同。
			$kq_check_all_para.=kq_ck_null($_REQUEST['version'],'version');
			//语言种类，1代表中文显示，2代表英文显示。默认为1,该值与提交时相同。
			$kq_check_all_para.=kq_ck_null($_REQUEST['language'],'language');
			//签名类型,该值为4，代表PKI加密方式,该值与提交时相同。
			$kq_check_all_para.=kq_ck_null($_REQUEST['signType'],'signType');
			//支付方式，一般为00，代表所有的支付方式。如果是银行直连商户，该值为10,该值与提交时相同。
			$kq_check_all_para.=kq_ck_null($_REQUEST['payType'],'payType');
			//银行代码，如果payType为00，该值为空；如果payType为10,该值与提交时相同。
			$kq_check_all_para.=kq_ck_null($_REQUEST['bankId'],'bankId');
			//商户订单号，,该值与提交时相同。
			$kq_check_all_para.=kq_ck_null($_REQUEST['orderId'],'orderId');
			//订单提交时间，格式：yyyyMMddHHmmss，如：20071117020101,该值与提交时相同。
			$kq_check_all_para.=kq_ck_null($_REQUEST['orderTime'],'orderTime');
			//订单金额，金额以“分”为单位，商户测试以1分测试即可，切勿以大金额测试,该值与支付时相同。
			$kq_check_all_para.=kq_ck_null($_REQUEST['orderAmount'],'orderAmount');
			// 快钱交易号，商户每一笔交易都会在快钱生成一个交易号。
			$kq_check_all_para.=kq_ck_null($_REQUEST['dealId'],'dealId');
			//银行交易号 ，快钱交易在银行支付时对应的交易号，如果不是通过银行卡支付，则为空
			$kq_check_all_para.=kq_ck_null($_REQUEST['bankDealId'],'bankDealId');
			//快钱交易时间，快钱对交易进行处理的时间,格式：yyyyMMddHHmmss，如：20071117020101
			$kq_check_all_para.=kq_ck_null($_REQUEST['dealTime'],'dealTime');
			//商户实际支付金额 以分为单位。比方10元，提交时金额应为1000。该金额代表商户快钱账户最终收到的金额。
			$kq_check_all_para.=kq_ck_null($_REQUEST['payAmount'],'payAmount');
			//费用，快钱收取商户的手续费，单位为分。
			$kq_check_all_para.=kq_ck_null($_REQUEST['fee'],'fee');
			//扩展字段1，该值与提交时相同
			$kq_check_all_para.=kq_ck_null($_REQUEST['ext1'],'ext1');
			//扩展字段2，该值与提交时相同。
			$kq_check_all_para.=kq_ck_null($_REQUEST['ext2'],'ext2');
			//处理结果， 10支付成功，11 支付失败，00订单申请成功，01 订单申请失败
			$kq_check_all_para.=kq_ck_null($_REQUEST['payResult'],'payResult');
			//错误代码 ，请参照《人民币网关接口文档》最后部分的详细解释。
			$kq_check_all_para.=kq_ck_null($_REQUEST['errCode'],'errCode');
					
			$kq_check_all_para.=kq_ck_null($key,"key");
		
			$kq_check_all_para=substr($kq_check_all_para,0,strlen($kq_check_all_para)-1);
			
			$merchantSignMsg= md5($kq_check_all_para);
			
			//获取加密签名串
			$signMsg=trim($_REQUEST['signMsg']);
			
			//初始化结果及地址
			$rtnOk=0;
			$rtnUrl="";
			$url = isset($_GET['ref'])?base64_decode($_GET['ref']):'';// 获取来源页面
			//商家进行数据处理，并跳转会商家显示支付结果的页面
			///首先进行签名字符串验证
			if(strtoupper($signMsg)==strtoupper($merchantSignMsg)){
					//订单ID
					$order_id = isset($_GET['orderid'])?$_GET['orderid']:'';//加密的
					$order_id = rc4($order_id, 'DECODE', 'MAINONE');//解密
					
					switch($_REQUEST['payResult']){
						case '10':
								D('Order', 'User', 'home')->paySuccess ($order_id);//支付成功后订单状态修改
								// 获取来源页面
								echo '<result>1</result><redirecturl>' . $url .'</redirecturl>';
								//跳转到商家页面
								break;
						default:
								//当做不成功处理
								echo '<result>1</result><redirecturl>' . $url .'</redirecturl>';//跳转到商家页面
								break;	
				
				}
		
			}else{
				echo '<result>1</result><redirecturl>' . $url .'</redirecturl>';//跳转到商家页面
									
			}
		} elseif ($pay_id == 6) {//银联在线支付
			$url = isset($_GET['ref'])?base64_decode($_GET['ref']):'';// 获取来源页面
			//订单ID
			$order_id = isset($_GET['orderid'])?$_GET['orderid']:'';//加密的
			$order_id = rc4($order_id, 'DECODE', 'MAINONE');//解密
			try {
				$response = new quickpay_service($_POST, quickpay_conf::RESPONSE);
				if ($response->get('respCode') != quickpay_service::RESP_SUCCESS) {
					$err = sprintf("Error: %d => %s", $response->get('respCode'), $response->get('respMsg'));
					throw new Exception($err);
				}
			
				$arr_ret = $response->get_args();
			
				//更新数据库，将交易状态设置为已付款
				 D('Order', 'User', 'home')->paySuccess ($order_id);//支付成功后订单状态修改
				//注意保存qid，以便调用后台接口进行退货/消费撤销
			

			
			}
			catch(Exception $exp) {
				
			}
		}
	}
	
	public function alipayreturnAction() {
	    
	    $alipay_info =  M('Payment')->where(array('pay_id'=>'1'))->select();//得到财付通的相关信息
	    $alipay_info = $alipay_info[0];//财付通信息，以数组元素1为准
	    //合作身份者id，以2088开头的16位纯数字
	    //$alipay_config['partner']		= '2088201881197742';
	    $alipay_config['partner']  = $alipay_info['partner_id'];
	    //安全检验码，以数字和字母组成的32位字符
	    //$alipay_config['key']			= '84natikibvwjhk0prcr4ujsf0o23eont';
	    $alipay_config['key'] = $alipay_info['key'];
	    	
	    //签名方式 不需修改
	    $alipay_config['sign_type']    = strtoupper('MD5');
	    	
	    //字符编码格式 目前支持 gbk 或 utf-8
	    $alipay_config['input_charset']= strtolower('utf-8');
	    	
	    //ca证书路径地址，用于curl中ssl校验
	    //请保证cacert.pem文件在当前文件夹目录中
	    $alipay_config['cacert']    = getcwd().'\\cacert.pem';
	    	
	    //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
	    $alipay_config['transport']    = 'http';
	  
        	//计算得出通知验证结果
        $alipayNotify = new AlipayNotify($alipay_config);
        $verify_result = $alipayNotify->verifyNotify();
        
        if($verify_result) {//验证成功
        	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        	//请在这里加上商户的业务逻辑程序代
        
        	
        	//——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
        	
            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
        	
        	//商户订单号
        
        	$out_trade_no = $_POST['out_trade_no'];
        
        	//支付宝交易号
        
        	$trade_no = $_POST['trade_no'];
        
        	//交易状态
        	$trade_status = $_POST['trade_status'];
              
        	//——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
            $order = M('orders')->find(array('order_sn'=>$out_trade_no));
            D('Order', 'User', 'home')->paySuccess ($order['id']);//支付成功后订单状态修改
            
                
        	echo "success";		//请不要修改或删除
        	
        	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        }
        else {
            //验证失败
            echo "fail";
        
            //调试用，写文本函数记录程序运行情况是否正常
            //logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
        }
	}
	
	/**
	 * 手机支付宝异步通知地址
	 */
	public function mobilealipayreturnAction() {
	    
	    $alipay_info = M('Payment')->where(array('pay_id' => '7'))->select(); //得到手机支付宝的相关信息
	    $alipay_info = $alipay_info[0]; //手机支付宝信息，以数组元素1为准
	    
	    //合作身份者id，以2088开头的16位纯数字
	    $alipay_config['partner']		= $alipay_info['partner_id'];;
	    
	    //安全检验码，以数字和字母组成的32位字符
	    //如果签名方式设置为“MD5”时，请设置该参数
	    $alipay_config['key']			= $alipay_info['key'];
	    
	    //商户的私钥（后缀是.pen）文件相对路径
	    //如果签名方式设置为“0001”时，请设置该参数
	    $alipay_config['private_key_path']	= getcwd() . '\\key\\rsa_private_key.pem';
	    
	    //支付宝公钥（后缀是.pen）文件相对路径
	    //如果签名方式设置为“0001”时，请设置该参数
	    $alipay_config['ali_public_key_path']= getcwd() . '\\key\\alipay_public_key.pem';
	    
	    //签名方式 不需修改
	    $alipay_config['sign_type']    = '0001';
	    
	    //字符编码格式 目前支持 gbk 或 utf-8
	    $alipay_config['input_charset']= 'utf-8';
	    
	    
	    //ca证书路径地址，用于curl中ssl校验
	    //请保证cacert.pem文件在当前文件夹目录中
	    $alipay_config['cacert']    = getcwd() . '\\key\\cacert.pem';
	    
	    
	    //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
	    $alipay_config['transport']    = 'http';
	    
	    //计算得出通知验证结果
	    $alipayNotify = new AlipayNotify1($alipay_config);
	    $verify_result = $alipayNotify->verifyNotify();
	    
	    if($verify_result) {//验证成功
	        
	        //请在这里加上商户的业务逻辑程序代	    
	        //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
	        //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
	    
	        //解析notify_data
	        //注意：该功能PHP5环境及以上支持，需开通curl、SSL等PHP配置环境。建议本地调试时使用PHP开发软件
	        $doc = new DOMDocument();
	        if ($alipay_config['sign_type'] == 'MD5') {
	            $doc->loadXML($_POST['notify_data']);
	        }
	    
	        if ($alipay_config['sign_type'] == '0001') {
	            $doc->loadXML($alipayNotify->decrypt($_POST['notify_data']));
	        }
	    
	        if( ! empty($doc->getElementsByTagName( "notify" )->item(0)->nodeValue) ) {
	            //商户订单号
	            $out_trade_no = $doc->getElementsByTagName( "out_trade_no" )->item(0)->nodeValue;
	            //支付宝交易号
	            $trade_no = $doc->getElementsByTagName( "trade_no" )->item(0)->nodeValue;
	            //交易状态
	            $trade_status = $doc->getElementsByTagName( "trade_status" )->item(0)->nodeValue;
	            
	            //——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
	            $order = M('orders')->find(array('order_sn'=>$out_trade_no));
	            D('Order', 'User', 'home')->paySuccess ($order['id']);//支付成功后订单状态修改
	            
	            
	            echo "success";		//请不要修改或删除
	    
	               
	        }
	    
	    
	    }
	    else {
	        //验证失败
	        echo "fail";
	    
	        //调试用，写文本函数记录程序运行情况是否正常
	        //logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
	    }	    
	}
	
	
	
	
	
	
	
	//微信支付
	public function weixinAction()
	{
		$out_trade_no = Controller::get('order_sn');
		
		if($out_trade_no==false){
			return null;
		}
		$result = M('orders')->where(array('order_sn'=>$out_trade_no))->getOne();
		//print_r($result);die;
		if($result['current_debt']){ 
		$current_debt = $result['current_debt'];
		$current_debt = $current_debt * 100;
		$current_debt = ceil($current_debt);
				//======================================================================================================
				//生成验证码
						//使用统一支付接口
				$unifiedOrder = new UnifiedOrder_pub();
				//设置统一支付接口参数
				//设置必填参数
				$unifiedOrder->setParameter("body","感谢捐助慈善事业");//商品描述
				//自定义订单号，此处仅作举例
				$timeStamp = time();
				//$out_trade_no = '20170208'."$timeStamp";
				$unifiedOrder->setParameter("out_trade_no",$out_trade_no);//商户订单号
				//$unifiedOrder->setParameter("product_id",'10'); //自定义的商品主键id		
				$unifiedOrder->setParameter("total_fee",$current_debt);//总金额
				//$unifiedOrder->setParameter("total_fee","1");//总金额
				$unifiedOrder->setParameter("notify_url", 'http://hbcjrfljjh.var365.cn/index.php/goods/Payment/notify');//通知地址 
				$unifiedOrder->setParameter("trade_type","NATIVE");//交易类型
				

				$unifiedOrderResult = $unifiedOrder->getResult();
		//         var_dump($unifiedOrder);
				//商户根据实际情况设置相应的处理流程
				if ($unifiedOrderResult["return_code"] == "FAIL") 
				{
					//商户自行增加处理流程
					echo "通信出错：".$unifiedOrderResult['return_msg']."<br>";
				}
				elseif($unifiedOrderResult["result_code"] == "FAIL")
				{
					//商户自行增加处理流程
					echo "错误代码：".$unifiedOrderResult['err_code']."<br>";
					echo "错误代码描述：".$unifiedOrderResult['err_code_des']."<br>";
				}
				elseif($unifiedOrderResult["code_url"] != NULL)
				{
					//从统一支付接口获取到code_url
					$code_url = $unifiedOrderResult["code_url"];
					//商户自行增加处理流程
					//......
				}
				//=========================================================================================================
			
			
			include $this->display('weixin.html');
		}else{
			return null;
		}
		
	}
	
	//查询订单
    public function orderQueryAction()
    {  
        //退款的订单号
    	if (!isset($_POST["out_trade_no"]))
    	{
    		$out_trade_no = " ";
    	}else{
    	    $out_trade_no = $_POST["out_trade_no"];
    		//使用订单查询接口
    		$orderQuery = new OrderQuery_pub();
    		//设置必填参数
    		//appid已填,商户无需重复填写
    		//mch_id已填,商户无需重复填写
    		//noncestr已填,商户无需重复填写
    		//sign已填,商户无需重复填写
    		$orderQuery->setParameter("out_trade_no","$out_trade_no");//商户订单号 
    		//非必填参数，商户可根据实际情况选填
    		//$orderQuery->setParameter("sub_mch_id","XXXX");//子商户号  
    		//$orderQuery->setParameter("transaction_id","XXXX");//微信订单号
    		
    		//获取订单查询结果
    		$orderQueryResult = $orderQuery->getResult();
    		
    		//商户根据实际情况设置相应的处理流程,此处仅作举例
    		if ($orderQueryResult["return_code"] == "FAIL") {
    			$this->error($out_trade_no);
    		}
    		elseif($orderQueryResult["result_code"] == "FAIL"){
//     			$this->ajaxReturn('','支付失败！',0);
    			$this->error($out_trade_no);
    		}
    		else{
    		     $i=$_SESSION['i'];
    		     $i--;
    		     $_SESSION['i'] = $i;
    		      //判断交易状态
    		      switch ($orderQueryResult["trade_state"])
    		      {
    		          case SUCCESS: 
                          echo json_encode(array('data'=>'支付成功', 'status'=>1));exit();
    		              break;
    		          case REFUND:
                          echo json_encode(array('data'=>'超时关闭订单2：','status'=>2));exit();    		              
    		              break;
    		          case NOTPAY:
    		            //   $this->error("超时关闭订单：".$i);
                          echo json_encode(array('data'=>'超时关闭订单1：'.$i));exit();
//     		              $this->ajaxReturn($orderQueryResult["trade_state"], "支付成功", 1);
    		              break;
    		          case CLOSED:
    		              echo json_encode(array('data'=>'超时关闭订单1：'.$i));exit();
    		              break;
    		          case PAYERROR:
    		            //   $this->error("支付失败".$orderQueryResult["trade_state"]);
                          echo json_encode(array('data'=>'支付失败'.$orderQueryResult["trade_state"]));exit();
    		              break;
    		          default:
                          echo json_encode(array('data'=>'未知失败'.$orderQueryResult["trade_state"]));exit();
    		              break;
    		      }
    		     }	
    	}
    }
    public function notifyAction()
    {
        //使用通用通知接口
        $notify = new Notify_pub();
        //存储微信的回调
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $notify->saveData($xml);
  
            if ($notify->data["return_code"] == "FAIL") {
				//$data = array('username'=>'支付错误');
              // M('member')->create($data);
            }
            elseif($notify->data["result_code"] == "FAIL"){
              // $data = array('username'=>'业务出错');
               //M('member')->create($data);
            }
            else{
                //此处应该更新一下订单状态，商户自行增删操作
				//$notify->data['out_trade_no']
               //$data = array('username'=>'支付成功'.$notify->data['trade_state']);
               //M('member')->create($data);
			
			   $result = M('orders')->where(array('order_sn'=>$notify->data['out_trade_no']))->getOne();
			   $id = $result['id'];
			   D('Order', 'User', 'home')->paySuccess ($id);//支付成功后订单状态修改
			   
            }
             
            //商户自行增加处理流程,
            //例如：更新订单状态
            //例如：数据库操作
            //例如：推送支付完成信息
        
	}
	
	public function successpayAction()
	{

		$url = Cookie::get('HTTP_REFERER');// 获取来源页面
		$this->showMessage("支付成功" , $url , 3);//跳转到商家页面

	}		
}
