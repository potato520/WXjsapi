<?php
/**
 * Class WeixinController
 */

class WeixinController extends HomeController {

    private $appid;
    private $appsecret;
    private $filename;
    private $filePic = "./Public/Admin/qrcode.jpg";
    private $m3Result;

	public function init()
	{
		parent::init();
        $this->filename = DIR_BF_ROOT . "Tool/weixin/weixin.txt";
        $this->appid = "wxc3ec3fc5c2957492";
        $this->appsecret = "850c69b5ee23fe828cc2e33f9bc61819";

        // 包含数据基础接口
        include DIR_BF_ROOT .'Tool/SMS/M3Result.php';
        $this->m3Result = new M3Result();

        // 引入短信发送接口
        include DIR_BF_ROOT .'Tool/SMS/SendTemplateSMS.php';

    }

    /*
     * test 获取当前用户的openid
     */
    public function getOpenIdAction()
    {
		$url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=wxc3ec3fc5c2957492&redirect_uri=http://caohongda.var365.cn/weixin/Weixin/getCode&response_type=code&scope=snsapi_base&state=1#wechat_redirect";
		
		header("Location：".$url);
    }
	public function getCodeAction()
	{
		$code=$_GET['code'];
		$url="https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$this->appid."&secret=". 
		$this->appsecret."&code=".$code."&grant_type=authorization_code";

		$weixin=file_get_contents($url);//通过code换取网页授权access_token
		$jsondecode=json_decode($weixin); //对JSON格式的字符串进行编码
		$array = get_object_vars($jsondecode);//转换成数组
		$openid = $array['openid'];//输出openid
		return $openid;
	}
	public function getInfoAction()
	{
		
	}

    //获取用户列表
    public function getUserAction()
    {
        // 请求方式get
        $url = "https://api.weixin.qq.com/cgi-bin/user/get?access_token=".$this->getCacheToken()."&next_openid=";
        $data = $this->request($url);
        $data = json_decode($data, true);
        //获取用户列表
        p($data['data']['openid']);
    }


    /**
     * 手机注册
     * @return mixed
     */
    public function registerAction()
    {
        if(isAjax()){
            $phone = Controller::post('phone', '');
            $phone_code = Controller::post('phone_code', '');

            if($phone != ''){
                if($phone_code =='' || strlen($phone_code) != 4){
                    $this->m3Result->status = 3;
                    $this->m3Result->message = '手机验证码为4位';
                    return $this->m3Result->toJson();
                }
            }

            // 检测手机号是否存在
            $isPhone = M('member')->getOne();
            if($isPhone){
                $this->m3Result->status = 6;
                $this->m3Result->message = '手机号已注册';
                return $this->m3Result->toJson();
            }
            $tempPhone = M('tempPhone')->where(array('phone'=>$phone))->getOne();

            // 如果数据库中的手机验证码 等于 我获取的验证码
            if($tempPhone['code'] == $phone_code){
                // 判断验证码有效期
                if(time() > $tempPhone['deadline']) {
                    $this->m3Result->status = 4;
                    $this->m3Result->message = '验证码不正确或已过期，重新获取';
                    return $this->m3Result->toJson();
                }

                // 验证码正确，并且没有过期
                $data = array();
                $data = array('phone' => $phone);
                if(M('member')->create($data)){
                    $this->m3Result->status = 0;
                    $this->m3Result->message = '注册成功';
                    return $this->m3Result->toJson();
                }
            }else{
                $this->m3Result->status = 5;
                $this->m3Result->message = '验证码不正确,重新获取';
                return $this->m3Result->toJson();
            }

        }
        include $this->display('weixin/register.html');
    }

    /**
     * 发送短信接口
     * @return mixed
     */
    public function sendSMSAction()
    {
        $phone = Controller::post('phone', '');
        if($phone == ''){
            $this->m3Result->status = 1;
            $this->m3Result->message = '手机号不能为空';
            return $this->m3Result->toJson(); # 返回json 状态
        }
        if(strlen($phone) != 11 || $phone[0] != '1'){
            $this->m3Result->status = 2;
            $this->m3Result->message = '手机格式不正确';
            return $this->m3Result->toJson();
        }

        // 生成随机验证码
        $charset = '1234567890';
        $code = '';
        $_len = strlen($charset) - 1;
        for ($i = 0;$i < 4;++$i) {
            $code .= $charset[mt_rand(0, $_len)];
        }

        // 检查是否已经注册过的手机号码
        $tempPhone = M('tempPhone')->where(array('phone'=>$phone))->getOne();
        if($tempPhone == null){
            // 保存数据到表中
            $insetTemp = array();
            $endtime = time()+ 60*60; # 验证码的过期时间是 60分钟);
            $insetTemp = array('phone'=>$phone, 'code'=>$code, 'deadline'=>$endtime);
            $isSave = M('tempPhone')->create($insetTemp);
            if($isSave){
                // 如果发送成功短信   // 短信接口内部会返回 成功失败信息
                $SendTemplateSMS = new SendTemplateSMS1();
                // 手机号码， 验证码，有效时间，1：是默认免费模板
                $SendTemplateSMS->sendTemplateSMS($phone, array($code, 60), 1);
            }
        }else{
            $this->m3Result->status = 1;
            $this->m3Result->message = '手机号已注册';
            return $this->m3Result->toJson(); # 返回json 状态
        }

        return $this->m3Result->toJson();
    }


    /**
     * 生成菜单
     */
    public function createMenuAction()
    {
        //使用 post协议
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$this->getCacheToken();
        $data =  '{
    "button": [
        {
            "type": "view",
            "name": "短线掘金",
            "url": "http://www.qq.com/",
            "sub_button": [ ]
        },
        {
            "type": "view",
            "name": "短线掘金",
            "url": "http://www.qq.com/",
            "sub_button": [ ]
        },
        {
            "type": "view",
            "name": "个人中心",
            "url": "http://caohongda.var365.cn/weixin/Weixin/getCode",
            "sub_button": [ ]
        }
    ]
}';
        $data = $this->request($url, true, "post", $data);
        $data = json_decode($data);
        $status = $data->errmsg;
        if($status == "ok"){
            echo "生成菜单成功..";
        }else{
            echo "生成菜单失败,错误代码" . $data->errcode;
        }
    }

    /**
     * 查看缓存 token
     */
	public function testAction()
    {
       p($this->getCacheToken());
    }


    /**
     * @return string
     */
    public function getCacheToken()
    {
        $access_token = file_get_contents($this->filename);
//         echo $access_token;
        return $access_token;
    }


    /**
     * 获取 token
     */
    public function getTokenAction()
    {
        if(!file_exists($this->filename)){
			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appid."&secret=".$this->appsecret;
			#返回json字符串获取里面的access_token 数据
			$data = $this->request($url);
			$data = json_decode($data);
			$access_token = $data->access_token;
			// echo $access_token;
			file_put_contents($this->filename, $access_token);
		}
		
		// 获取文件修改时间
		$updateTime=filemtime($this->filename);
		// 文件修改时间加上 +7200s 两个小时以后
		$upTime = $updateTime + 7200;
		// 超过两个小时
		if($upTime < time()){
//            echo '过期重新获取';die;
			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appid."&secret=".$this->appsecret;
			#返回json字符串获取里面的access_token 数据
			$data = $this->request($url);
			$data = json_decode($data);
			$access_token = $data->access_token;
			// echo $access_token;
			file_put_contents($this->filename, $access_token);
		}else{
//            echo '没过期，读取缓存';die;
			$this->getCacheToken();
		}
    }


    /**
     * @param $url
     * @param bool|true $https
     * @param string $method
     * @param null $data
     * @return mixed
     */
    public function request($url,$https=true,$method='get',$data=null){
        //1.初始化url
        $ch = curl_init($url);
        //2.设置相关的参数
        //字符串不直接输出,进行一个变量的存储
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //判断是否为https请求
        if($https === true){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        //判断是否为post请求
        if($method == 'post'){
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        //3.发送请求
        $str = curl_exec($ch);
        //4.关闭连接
        curl_close($ch);
        //返回请求到的结果
        return $str;
    }


}
