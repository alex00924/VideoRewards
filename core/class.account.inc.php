<?php

	/*!
	* VIDEO REWARDS v2.0
	*
	* http://www.droidoxy.com
	* support@droidoxy.com
	*
	* Copyright 2018 DroidOXY ( http://www.droidoxy.com )
	*/

class account extends db_connect
{

    private $id = 0;

    public function __construct($dbo = NULL, $accountId = 0)
    {

        parent::__construct($dbo);

        $this->setId($accountId);
    }

    public function signup($username, $fullname, $password, $email)
    {

        $result = array("error" => true);

        $helper = new helper($this->db);

        if (!helper::isCorrectLogin($username)) {

            $result = array("error" => true,
                "error_code" => ERROR_UNKNOWN,
                "error_type" => 0,
                "error_description" => "Incorrect login");

            return $result;
        }

        if ($helper->isLoginExists($username)) {

            $result = array("error" => true,
                "error_code" => ERROR_LOGIN_TAKEN,
                "error_type" => 0,
                "error_description" => "Login already taken");

            return $result;
        }

        if (empty($fullname)) {

            $result = array("error" => true,
                "error_code" => ERROR_UNKNOWN,
                "error_type" => 3,
                "error_description" => "Empty user full name");

            return $result;
        }

        if (!helper::isCorrectPassword($password)) {

            $result = array("error" => true,
                "error_code" => ERROR_UNKNOWN,
                "error_type" => 1,
                "error_description" => "Incorrect password");

            return $result;
        }

        if (!helper::isCorrectEmail($email)) {

            $result = array("error" => true,
                "error_code" => ERROR_UNKNOWN,
                "error_type" => 2,
                "error_description" => "Wrong email");

            return $result;
        }

        if ($helper->isEmailExists($email)) {

            $result = array("error" => true,
                "error_code" => ERROR_EMAIL_TAKEN,
                "error_type" => 2,
                "error_description" => "Email is already registered");

            return $result;
        }
        
        $ip_addr = helper::ip_addr();
        
        // if ($helper->isIpExists($ip_addr)) {

        //     $result = array("error" => true,
        //         "error_code" => ERROR_IP_TAKEN,
        //         "error_type" => 4,
        //         "error_description" => "This Device is already registered, only one Account for one device !");

        //     return $result;
        // }

        $salt = helper::generateSalt(3);
        $refer = helper::generateRandomString();
        $passw_hash = md5(md5($password).$salt);
        $currentTime = time();

        
        $accountState = ACCOUNT_STATE_ENABLED;

        $stmt = $this->db->prepare("INSERT INTO users (state, login, fullname, passw, email, salt, regtime, ip_addr,refer) value (:state, :username, :fullname, :password, :email, :salt, :createAt, :ip_addr, :refer)");
        $stmt->bindParam(":state", $accountState, PDO::PARAM_INT);
        $stmt->bindParam(":username", $username, PDO::PARAM_STR);
        $stmt->bindParam(":fullname", $fullname, PDO::PARAM_STR);
        $stmt->bindParam(":password", $passw_hash, PDO::PARAM_STR);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->bindParam(":salt", $salt, PDO::PARAM_STR);
        $stmt->bindParam(":createAt", $currentTime, PDO::PARAM_INT);
        $stmt->bindParam(":ip_addr", $ip_addr, PDO::PARAM_STR);
        $stmt->bindParam(":refer", $refer, PDO::PARAM_STR);

        if ($stmt->execute()) {

            $this->setId($this->db->lastInsertId());

            $result = array("error" => false,
                            'accountId' => $this->id,
                            'username' => $username,
                            'password' => $password,
                            'error_code' => ERROR_SUCCESS,
                            'error_description' => 'SignUp Success!');

            return $result;
        }

        return $result;
    }

    public function signin($username, $password)
    {
        $access_data = array('error' => true);

        $username = helper::clearText($username);
        $password = helper::clearText($password);

        $stmt = $this->db->prepare("SELECT salt FROM users WHERE login = (:username) LIMIT 1");
        $stmt->bindParam(":username", $username, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {

            $row = $stmt->fetch();
            $passw_hash = md5(md5($password).$row['salt']);

            $stmt2 = $this->db->prepare("SELECT id, state FROM users WHERE login = (:username) AND passw = (:password) LIMIT 1");
            $stmt2->bindParam(":username", $username, PDO::PARAM_STR);
            $stmt2->bindParam(":password", $passw_hash, PDO::PARAM_STR);
            $stmt2->execute();

            if ($stmt2->rowCount() > 0) {

                $row2 = $stmt2->fetch();

                $access_data = array("error" => false,
                                     "error_code" => ERROR_SUCCESS,
                                     "accountId" => $row2['id']);
            }
        }

        return $access_data;
    }

    public function logout($accountId, $accessToken)
    {
        $auth = new auth($this->db);
        $auth->remove($accountId, $accessToken);
    }

    public function newPassword($password)
    {
        $newSalt = helper::generateSalt(3);
        $newHash = md5(md5($password).$newSalt);

        $stmt = $this->db->prepare("UPDATE users SET passw = (:newHash), salt = (:newSalt) WHERE id = (:accountId)");
        $stmt->bindParam(":accountId", $this->id, PDO::PARAM_INT);
        $stmt->bindParam(":newHash", $newHash, PDO::PARAM_STR);
        $stmt->bindParam(":newSalt", $newSalt, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function restorePointCreate($email, $clientId)
    {
        $result = array("error" => true,
                        "error_code" => ERROR_UNKNOWN);

        $restorePointInfo = $this->restorePointInfo();

        if ($restorePointInfo['error'] === false) {

            return $restorePointInfo;
        }

        $currentTime = time();	// Current time

        $u_agent = helper::u_agent();
        $ip_addr = helper::ip_addr();

        $hash = md5(uniqid(rand(), true));

        $stmt = $this->db->prepare("INSERT INTO restore_data (accountId, hash, email, clientId, createAt, u_agent, ip_addr) value (:accountId, :hash, :email, :clientId, :createAt, :u_agent, :ip_addr)");
        $stmt->bindParam(":accountId", $this->id, PDO::PARAM_INT);
        $stmt->bindParam(":hash", $hash, PDO::PARAM_STR);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->bindParam(":clientId", $clientId, PDO::PARAM_INT);
        $stmt->bindParam(":createAt", $currentTime, PDO::PARAM_INT);
        $stmt->bindParam(":u_agent", $u_agent, PDO::PARAM_STR);
        $stmt->bindParam(":ip_addr", $ip_addr, PDO::PARAM_STR);

        if ($stmt->execute()) {

            $result = array('error' => false,
                            'error_code' => ERROR_SUCCESS,
                            'accountId' => $this->id,
                            'hash' => $hash,
                            'email' => $email);
        }

        return $result;
    }

    public function restorePointInfo()
    {
        $result = array("error" => true,
                        "error_code" => ERROR_UNKNOWN);

        $stmt = $this->db->prepare("SELECT * FROM restore_data WHERE accountId = (:accountId) AND removeAt = 0 LIMIT 1");
        $stmt->bindParam(":accountId", $this->id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {

            $row = $stmt->fetch();

            $result = array('error' => false,
                            'error_code' => ERROR_SUCCESS,
                            'accountId' => $row['accountId'],
                            'hash' => $row['hash'],
                            'email' => $row['email']);
        }

        return $result;
    }

    public function restorePointRemove()
    {
        $result = array("error" => true,
                        "error_code" => ERROR_UNKNOWN);

        $removeAt = time();

        $stmt = $this->db->prepare("UPDATE restore_data SET removeAt = (:removeAt) WHERE accountId = (:accountId)");
        $stmt->bindParam(":accountId", $this->id, PDO::PARAM_INT);
        $stmt->bindParam(":removeAt", $removeAt, PDO::PARAM_STR);

        if ($stmt->execute()) {

            $result = array("error" => false,
                            "error_code" => ERROR_SUCCESS);
        }

        return $result;
    }

    public function setState($accountState)
    {

        $stmt = $this->db->prepare("UPDATE users SET state = (:accountState) WHERE id = (:accountId)");
        $stmt->bindParam(":accountId", $this->id, PDO::PARAM_INT);
        $stmt->bindParam(":accountState", $accountState, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getState()
    {
        $stmt = $this->db->prepare("SELECT state FROM users WHERE id = (:id) LIMIT 1");
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);

        if ($stmt->execute()) {

            $row = $stmt->fetch();

            return $row['state'];
        }

        return 0;
    }

    public function get()
    {
        $result = array("error" => true,
                        "error_code" => ERROR_ACCOUNT_ID);

        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = (:id) LIMIT 1");
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);

        if ($stmt->execute()) {

            if ($stmt->rowCount() > 0) {

                $row = $stmt->fetch();

                $result = array("error" => false,
                                "error_code" => ERROR_SUCCESS,
                                "id" => $row['id'],
                                "last_access" => $row['last_access'],
                                "last_ip_addr" => $row['last_ip_addr'],
                                "gcm" => $row['gcm_regid'],
                                "state" => $row['state'],
                                "fullname" => stripcslashes($row['fullname']),
                                "username" => $row['login'],
                                "email" => $row['email'],
                                "regtime" => $row['regtime'],
                                "ip_addr" => $row['ip_addr'],
                                "mobile" => $row['mobile'],
                                "points" => $row['points'],
                                "refer" => $row['refer'],
                                "refered" => $row['refered'],
                                "premium" => $row['premium'],
                                "payment_type" => $row['payment_type'],
                                "payment_reference" => $row['payment_reference'],
                            );
            }
        }

        return $result;
    }

    public function getreferer($refererCode)
    {
        $result = array("error" => true,
                        "error_code" => ERROR_ACCOUNT_ID);

        $stmt = $this->db->prepare("SELECT * FROM users WHERE refer = (:refercode) LIMIT 1");
        $stmt->bindParam(":refercode", $refererCode, PDO::PARAM_STR);

        if ($stmt->execute()) {

            if ($stmt->rowCount() > 0) {

                $row = $stmt->fetch();

                $result = array("error" => false,
                                "error_code" => ERROR_SUCCESS,
                                "id" => $row['id'],
                                "last_access" => $row['last_access'],
                                "last_ip_addr" => $row['last_ip_addr'],
                                "gcm" => $row['gcm_regid'],
                                "state" => $row['state'],
                                "fullname" => stripcslashes($row['fullname']),
                                "username" => $row['login'],
                                "email" => $row['email'],
                                "regtime" => $row['regtime'],
                                "ip_addr" => $row['ip_addr'],
                                "mobile" => $row['mobile'],
                                "points" => $row['points'],
                                "refer" => $row['refer'],
                                "refered" => $row['refered'],
                                "premium" => $row['premium']);
            }
        }

        return $result;
    }

    public function getOldRefersData($username)
    {
        $result = array("error" => true,
                        "error_code" => ERROR_ACCOUNT_ID);

        $stmt = $this->db->prepare("SELECT * FROM referers WHERE username = (:username) LIMIT 1");
        $stmt->bindParam(":username", $username, PDO::PARAM_STR);

        if ($stmt->execute()) {

            if ($stmt->rowCount() > 0) {

                $row = $stmt->fetch();

                $result = array("id" => $row['id'],
                                "username" => $row['username'],
                                "referer" => $row['referer'],
                                "points" => $row['points'],
                                "type" => $row['type'],
                                "date" => $row['date']);
            }
        }

        return $result;
    }
    
    

    public function getuserdata($username)
    {
        $result = array("error" => true,
                        "error_code" => ERROR_ACCOUNT_ID);

        $stmt = $this->db->prepare("SELECT * FROM users WHERE login = (:username) LIMIT 1");
        $stmt->bindParam(":username", $username, PDO::PARAM_INT);

        if ($stmt->execute()) {

            if ($stmt->rowCount() > 0) {

                $row = $stmt->fetch();

                $result = array("error" => false,
                                "error_code" => ERROR_SUCCESS,
                                "id" => $row['id'],
                                "last_access" => $row['last_access'],
                                "last_ip_addr" => $row['last_ip_addr'],
                                "gcm" => $row['gcm_regid'],
                                "state" => $row['state'],
                                "fullname" => stripcslashes($row['fullname']),
                                "username" => $row['login'],
                                "email" => $row['email'],
                                "regtime" => $row['regtime'],
                                "ip_addr" => $row['ip_addr'],
                                "mobile" => $row['mobile'],
                                "points" => $row['points'],
                                "refer" => $row['refer'],
                                "refered" => $row['refered'],
                                "premium" => $row['premium']);
            }
        }

        return $result;
    }

    public function getConfigs($fcm = 0)
    {
        
        $conf = array();
        $config = new functions($this->db);
        
        $stmt = $this->db->prepare("SELECT * FROM configuration WHERE api_status = 1");

        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                while($row = $stmt->fetch()) {
                    
                    if(strlen($row['config_value']) == 1){
                        
                        if($row['config_value'] == 1){
                            
                            $conf[$row['config_name']] = true;
                            
                        }else if($row['config_value'] == 0){
                            
                            $conf[$row['config_name']] = false;
                            
                        }else{
                            
                            $conf[$row['config_name']] = $row['config_value'];
                            
                        }
                        
                    }else{
                        
                        $conf[$row['config_name']] = $row['config_value'];
                        
                    }
                    
                    
                }
            }
        }
        
        $conf['AdmobVideoCredit_Amount'] = $config->getConfig('AdmobVideoCredit_Amount');
        $conf['StartAppVideoCredit_Amount'] = $config->getConfig('StartAppVideoCredit_Amount');
        
        $config->updateAnalyticsSessions();
        
        $ipaddr = $_SERVER['REMOTE_ADDR'];
        $time = time();
        
        if ($fcm == 0) {
            
            $stmt = $this->db->prepare("UPDATE users SET last_access = (:time),last_ip_addr = (:ipaddr),gcm_regid = (:fcm) WHERE id = (:id)");
            $stmt->bindParam(":fcm", $fcm, PDO::PARAM_STR);
            $stmt->bindParam(":ipaddr", $ipaddr, PDO::PARAM_STR);
            $stmt->bindParam(":time", $time, PDO::PARAM_STR);
            $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
            $stmt->execute();

        }else{
        
            $stmt = $this->db->prepare("UPDATE users SET last_access = (:time),last_ip_addr = (:ipaddr) WHERE id = (:id)");
            $stmt->bindParam(":ipaddr", $ipaddr, PDO::PARAM_STR);
            $stmt->bindParam(":time", $time, PDO::PARAM_STR);
            $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
            $stmt->execute();
            
        }
        
        return $conf;
    }

    public function getConfig()
    {
        $result = array("error" => true,"error_code" => ERROR_ACCOUNT_ID);

        $stmt = $this->db->prepare("SELECT * FROM configuration WHERE id = (:id) LIMIT 1");
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);

        if ($stmt->execute()) {

            if ($stmt->rowCount() > 0) {

                $row = $stmt->fetch();
				//$row['config_name'] => $row['config_value']
                $result = array("config_name" => $row['config_name'],"config_value" => $row['config_value']);
            }
        }

        return $result;
    }


    public function setId($accountId)
    {
        $this->id = $accountId;
    }

    public function getId()
    {
        return $this->id;
    }

    public function updatePaymentReferer($payType, $payReference) {
        $stmt = $this->db->prepare("UPDATE users SET payment_type = (:paymentType), payment_reference = (:referernce) WHERE id = (:accountId)");
        $stmt->bindParam(":accountId", $this->id, PDO::PARAM_INT);
        $stmt->bindParam(":paymentType", $payType, PDO::PARAM_STR);
        $stmt->bindParam(":referernce", $payReference, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function updateToPremium() {
        $stmt = $this->db->prepare("UPDATE users SET premium = 1 WHERE id = (:accountId)");
        $stmt->bindParam(":accountId", $this->id, PDO::PARAM_INT);
        $stmt->execute();
    }
}

