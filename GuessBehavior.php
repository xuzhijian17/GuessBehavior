<?php
class GuessBehavior extends CBehavior
{
	//public $init_Num = 5341;
	//public $init_rate = 47;

	// Set number of guess.
	private $guessNum = 1;

	// Set number of point.
	private $guessGold = 100;

	//private $type = 103;

	private $cOpeningQuotationTime;
	private $cClosingQuotationTime;

	// Custom user can guess time(include official holiday).
	protected $aCustomTime;

	public function __construct(){

		// init opening quotation time and closing quotation time.
		$this->cOpeningQuotationTime = mktime(9, 30, 0);
		$this->cClosingQuotationTime = mktime(15, 0, 0);

		// init custom guess time and offical holiday.
		$this->aCustomTime = array(
			'NewYearsDay'=>array('startTime'=>mktime(15, 0, 0, 12, 31), 'endTime'=>mktime(9, 30, 0, 1, 4)),
			'MayDay'=>array('startTime'=>mktime(15, 0, 0, 4, 30), 'endTime'=>mktime(9, 30, 0, 5, 4)),
			'NationalDay'=>array('startTime'=>mktime(15, 0, 0, 9, 30), 'endTime'=>mktime(9, 30, 0, 10, 8)),
			//'CustomDay'=>array('startTime'=>mktime(0, 0, 0, 9, 30),'endTime'=>mktime(0, 0, 0, 10, 8))
		);
	}
	
	/** 
	* Get user can allow guess time.
	*/
	public function getGuessTime($aGuessTime=array(),$status=0)
	{
		// If status is 0 is allow guess time else is not allow guess time.
		$aGuessTime['status'] = $status;

		//取毫秒数
		list($s1, $s2) = explode(' ', microtime());
		$tm = (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000)/1000;

		if (!$status) {
			// Custom allow guess time and official holiday.
			foreach ($this->aCustomTime as $key => $value) {
				if ($tm > $value['startTime'] && $tm < $value['endTime']) {
					$aGuessTime['startTime'] = $value['startTime'];
					$aGuessTime['endTime'] = $value['endTime'];
				}
			}

			// Have not assign any custom time and official holiday.
			if (!isset($aGuessTime['startTime']) && !isset($aGuessTime['endTime'])) {
				//$cWeeHours = mktime(0,0,0);
				$cOpeningQuotationTime = $this->cOpeningQuotationTime;	//mktime(9, 30, 0);
				$cClosingQuotationTime = $this->cClosingQuotationTime;	//mktime(15,0,0);
				
				// Detection current whether or not guess time.
				if (date('w') == 6) { 	// Saturday
					$aGuessTime['startTime'] = $cClosingQuotationTime - 3600*24;
					$aGuessTime['endTime'] = $cOpeningQuotationTime + 3600*24*2;
				}elseif (date('w') == 0) {		// Sunday
					$aGuessTime['startTime'] = $cClosingQuotationTime - 3600*24*2;
					$aGuessTime['endTime'] = $cOpeningQuotationTime + 3600*24;
				}elseif ($tm < $cOpeningQuotationTime) {		// Every day before 9:30 AM.
					$aGuessTime['startTime'] = $cClosingQuotationTime - 3600*24;
					$aGuessTime['endTime'] = $cOpeningQuotationTime;
					if (date('w') == 1) {	// If today is monday, then start time is last friday.
						$aGuessTime['startTime'] = $cClosingQuotationTime - 3600*24*3;
					}
				}elseif ($tm > $cClosingQuotationTime) {		// Every day after 15:00 PM.
					$aGuessTime['startTime'] = $cClosingQuotationTime;
					$aGuessTime['endTime'] = $this->getNextEndTime($cOpeningQuotationTime + 3600*24);//$cOpeningQuotationTime + 3600*24;
					if (date('w') == 5) {	// If today is friday, then end time is next monday.
						$aGuessTime['endTime'] = $this->getNextEndTime($cOpeningQuotationTime + 3600*24*3);//$cOpeningQuotationTime + 3600*24*3;
					}
				}else{	// Between 9:30 AM and 15:00 PM
					$aGuessTime['status'] = 1;
					$aGuessTime['startTime'] = $cOpeningQuotationTime;
					$aGuessTime['endTime'] = $cClosingQuotationTime;
				}
			}
		}else{
			// If $status is not 0, then $aGuessTime must be not empty.
			if (empty($aGuessTime)) {
				throw new Exception("Custom not allow guess time is empty.", 1);
			}
		}
		
		return $aGuessTime;
	}

	/**
    * Get end time is holiday time.
    */
	public function getNextEndTime($endTime)
	{
		if ($endTime > mktime(0,0,0) + 3600*24) {		// End time gt then today.
			// End time in holiday
			foreach ($this->aCustomTime as $key => $value) {
				if ($endTime > $value['startTime'] && $endTime < $value['endTime']) {
					$endTime = $value['endTime'];
				}
			}
			// End time in weekend.
			if (date('w',$endTime) == 6) {
				$endTime += 3600*24*2;
				$this->getNextEndTime($endTime);
			}
		}
		
		return $endTime;
	}

	/**
    * Get next opening quotation time.
    */
	public function getNextOpeningQuotationTime($aGuessTime)
	{
		$NextOpeningQuotationTime = $aGuessTime['endTime'];

		// Does not allow guess time in the day.
		if ($aGuessTime['status'] && $NextOpeningQuotationTime < mktime(0,0,0) + 3600*24) {		
			$NextOpeningQuotationTime += 3600*24;
		}

		$NextOpeningQuotationTime = $this->getNextEndTime($NextOpeningQuotationTime);
		
		return $NextOpeningQuotationTime;
	}

	/**
	* Get number of quizzes
	*/
	public function getGuessNum($value='')
	{
		$aHitNum = array();

		// False data
		$sql = "SELECT up_gold,down_gold FROM tb_activity_winnum WHERE id=1";
		$connection = Yii::app()->db_sns;
		$command = $connection->createCommand($sql);
		$aFalseData = $command->queryRow();

		// True data
		$nUp = Guess::model()->count("pledge_type=1 AND accounts_status=0");
		$nDown = Guess::model()->count("pledge_type=0 AND accounts_status=0");

		$aHitNum['Up'] = $nUp*$this->guessGold+$aFalseData['up_gold'];
		$aHitNum['Down'] = $nDown*$this->guessGold+$aFalseData['down_gold'];

		return $aHitNum;
	}

	/** 
	* Is user guess in allow guess time.
	*/
    public function isGuess($uid,$aGuessTime=array())
	{
		$isGuess = false;

		// Get guess time.
		$aGuessTime = $this->getGuessTime($aGuessTime);

		// Is guess time.
		if (!$aGuessTime['status']) {
			$nGuessRecord = Guess::model()->count("uid={$uid} and unix_timestamp(pledge_time) between {$aGuessTime['startTime']} and {$aGuessTime['endTime']}");
			if ($nGuessRecord < $this->guessNum) {
				$isGuess = true;
			}
		}

		return $isGuess;
	}
	
    /**
    * Whether users COINS to insufficient
    */
    public function isSurplusPoint($uid,$point){

		$boolen = false;

		$oUserInfo = $this->getUserInfo($uid);
		if ($oUserInfo && !empty($oUserInfo)) {
			if((int) $oUserInfo['point'] >= $point){
				$boolen = true;
			}
		}
		
		return $boolen;
	}

    /**
     * 修改金币, 金币类型PointsLog::GAMESUBTRACT常量默认103.
     * @uid
     * @point 金币数量
     */
    public function isUpdatePoint($uid,$point)
    {
    	$boolen = false;

		$result = Asset::changePoints($uid,$point,PointsLog::GAMESUBTRACT);
        if($result == 'succ'){
            $boolen = true;
        }

		return $boolen;
    }


    /*
     * 获取用户信息
     * @uid
     * @tm @token
     * return:{"error":0,"rs":{"username":"用户名","user_role":"用户角色","phone":"电话","phone_verify":"手机绑定",
     * "avatar":"头像地址","auth_verify":"身份认证",
     * "realname":"真实姓名","money":"账户资金","point":"剩余金币"}}
     */
    public function getUserInfo($uid)
    {
        $db_read = Yii::app()->db_read;
        $sql = "SELECT u.username,u.user_role,u.phone,u.phone_verify,p.avatar,p.auth_verify,p.realname,a.money,a.point FROM tb_user u 
                LEFT JOIN tb_user_profile p  ON u.id = p.uid LEFT JOIN tb2_asset a ON u.id = a.uid
                WHERE u.id = '{$uid}'";

        $data = $db_read->createCommand($sql)->queryRow();
        if ($data && !empty($data)){
            if (isset($data['avatar'])){
                $data['avatar'] = (strpos($data['avatar'], 'http') ===false)?'http://'.$_SERVER['HTTP_HOST'].$data['avatar']:$data['avatar'];
            }
            return $data;
        }

        return false;
    }

	public function curlPost($url,$data){
		// 创建一个新cURL资源
		$ch = curl_init();

		$header = array('Content-Type: application/x-www-form-urlencoded');
		
		// 设置URL和相应的选项
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_HEADER => false,
			CURLOPT_HTTPHEADER => $header,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($data),
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_RETURNTRANSFER => true,
		);

		curl_setopt_array($ch, $options);

		// 抓取URL并把它传递给浏览器
		$rs = curl_exec($ch);

		// 关闭cURL资源，并且释放系统资源
		curl_close($ch);
		
		return $rs;
	}
}