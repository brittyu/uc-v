<?php

namespace UcV;

use ParseXml;

class Client
{
    function ucAddslashes($string, $force = 0, $strip = FALSE) {
        !defined('MAGIC_QUOTES_GPC') && define('MAGIC_QUOTES_GPC', get_magic_quotes_gpc());
        if(!MAGIC_QUOTES_GPC || $force) {
            if(is_array($string)) {
                foreach($string as $key => $val) {
                    $string[$key] = $this->ucAddslashes($val, $force, $strip);
                }
            } else {
                $string = addslashes($strip ? stripslashes($string) : $string);
            }
        }
        return $string;
    }

    function daddslashes($string, $force = 0) {
        return $this->ucAddslashes($string, $force);
    }

    function ucStripslashes($string) {
        !defined('MAGIC_QUOTES_GPC') && define('MAGIC_QUOTES_GPC', get_magic_quotes_gpc());
        if(MAGIC_QUOTES_GPC) {
            return stripslashes($string);
        } else {
            return $string;
        }
    }

    function ucApiPost($module, $action, $arg = array()) {
        $s = $sep = '';
        foreach($arg as $k => $v) {
            $k = urlencode($k);
            if(is_array($v)) {
                $s2 = $sep2 = '';
                foreach($v as $k2 => $v2) {
                    $k2 = urlencode($k2);
                    $s2 .= "$sep2{$k}[$k2]=".urlencode($this->ucStripslashes($v2));
                    $sep2 = '&';
                }
                $s .= $sep.$s2;
            } else {
                $s .= "$sep$k=".urlencode($this->ucStripslashes($v));
            }
            $sep = '&';
        }
        $postdata = $this->ucApiRequestdata($module, $action, $s);
        return $this->ucFopen2(UC_API.'/index.php', 500000, $postdata, '', TRUE, UC_IP, 20);
    }

    function ucApiRequestdata($module, $action, $arg='', $extra='') {
        $input = $this->ucApiInput($arg);
        $post = "m=$module&a=$action&inajax=2&release=".UC_CLIENT_RELEASE."&input=$input&appid=".UC_APPID.$extra;
        return $post;
    }

    function ucApiUrl($module, $action, $arg='', $extra='') {
        $url = UC_API.'/index.php?'.$this->ucApiRequestdata($module, $action, $arg, $extra);
        return $url;
    }

    function ucApiInput($data) {
        $s = urlencode($this->ucAuthcode($data.'&agent='.md5($_SERVER['HTTP_USER_AGENT'])."&time=".time(), 'ENCODE', UC_KEY));
        return $s;
    }

    function ucSerialize($arr, $htmlon = 0, $isnormal = False, $level = 1) {
        $s = $level == 1 ? "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n<root>\r\n" : '';
        $space = str_repeat("\t", $level);
        foreach($arr as $k => $v) {
            if(!is_array($v)) {
                $s .= $space."<item id=\"$k\">".($htmlon ? '<![CDATA[' : '').$v.($htmlon ? ']]>' : '')."</item>\r\n";
            } else {
                $s .= $space."<item id=\"$k\">\r\n".$this->ucSerialize($v, $htmlon, $isnormal, $level + 1).$space."</item>\r\n";
            }
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);

        return $level == 1 ? $s."</root>" : $s;
    }

    function ucUnserialize($s) {
        $xmlParse = new ParseXml($htmlon);
        $data = $xmlParse->parse($arr);
        $xmlParse->destruct();

        return $data;
    }

    function ucAuthcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {

        $ckey_length = 4;

        $key = md5($key ? $key : UC_KEY);
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';

        $cryptkey = $keya.md5($keya.$keyc);
        $key_length = strlen($cryptkey);

        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
        $string_length = strlen($string);

        $result = '';
        $box = range(0, 255);

        $rndkey = array();
        for($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        for($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if($operation == 'DECODE') {
            if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc.str_replace('=', '', base64_encode($result));
        }
    }

    function ucFopen2($url, $limit = 0, $post = '', $cookie = '', $bysocket = FALSE, $ip = '', $timeout = 15, $block = TRUE) {
        $__times__ = isset($_GET['__times__']) ? intval($_GET['__times__']) + 1 : 1;
        if($__times__ > 2) {
            return '';
        }
        $url .= (strpos($url, '?') === FALSE ? '?' : '&')."__times__=$__times__";
        return $this->ucFopen($url, $limit, $post, $cookie, $bysocket, $ip, $timeout, $block);
    }

    function ucFopen($url, $limit = 0, $post = '', $cookie = '', $bysocket = FALSE, $ip = '', $timeout = 15, $block = TRUE) {
        $return = '';
        $matches = parse_url($url);
        !isset($matches['host']) && $matches['host'] = '';
        !isset($matches['path']) && $matches['path'] = '';
        !isset($matches['query']) && $matches['query'] = '';
        !isset($matches['port']) && $matches['port'] = '';
        $host = $matches['host'];
        $path = $matches['path'] ? $matches['path'].($matches['query'] ? '?'.$matches['query'] : '') : '/';
        $port = !empty($matches['port']) ? $matches['port'] : 80;
        if($post) {
            $out = "POST $path HTTP/1.0\r\n";
            $out .= "Accept: */*\r\n";
            $out .= "Accept-Language: zh-cn\r\n";
            $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $out .= "User-Agent: $_SERVER[HTTP_USER_AGENT]\r\n";
            $out .= "Host: $host\r\n";
            $out .= 'Content-Length: '.strlen($post)."\r\n";
            $out .= "Connection: Close\r\n";
            $out .= "Cache-Control: no-cache\r\n";
            $out .= "Cookie: $cookie\r\n\r\n";
            $out .= $post;
        } else {
            $out = "GET $path HTTP/1.0\r\n";
            $out .= "Accept: */*\r\n";
            $out .= "Accept-Language: zh-cn\r\n";
            $out .= "User-Agent: $_SERVER[HTTP_USER_AGENT]\r\n";
            $out .= "Host: $host\r\n";
            $out .= "Connection: Close\r\n";
            $out .= "Cookie: $cookie\r\n\r\n";
        }

        $errno = '';
        $errstr = '';
        $fp = $this->openSocket($ip, $host, $port, $errno, $errstr, $timeout);

        if(!$fp) {
            return '';
        } else {
            stream_set_blocking($fp, $block);
            stream_set_timeout($fp, $timeout);
            @fwrite($fp, $out);
            $status = stream_get_meta_data($fp);
            if(!$status['timed_out']) {
                while (!feof($fp)) {
                    if(($header = @fgets($fp)) && ($header == "\r\n" ||  $header == "\n")) {
                        break;
                    }
                }

                $stop = false;
                while(!feof($fp) && !$stop) {
                    $data = fread($fp, ($limit == 0 || $limit > 8192 ? 8192 : $limit));
                    $return .= $data;
                    if($limit) {
                        $limit -= strlen($data);
                        $stop = $limit <= 0;
                    }
                }
            }
            @fclose($fp);
            return $return;
        }
    }

    function openSocket($ip, $host, $port, $errno, $errstr, $timeout)
    {
        if(function_exists('fsockopen')) {
            $fp = @fsockopen(($ip ? $ip : $host), $port, $errno, $errstr, $timeout);
        } elseif (function_exists('pfsockopen')) {
            $fp = @pfsockopen(($ip ? $ip : $host), $port, $errno, $errstr, $timeout);
        } else {
            $fp = false;
        }

        return $fp;
    }

    function ucAppLs() {
        $return = $this->ucApiPost('app', 'ls', array());
        return UC_CONNECT == 'mysql' ? $return : $this->ucUnserialize($return);
    }

    function ucFeedAdd($icon, $uid, $username, $title_template='', $title_data='', $body_template='', $body_data='', $body_general='', $target_ids='', $images = array()) {
        return $this->ucApiPost('feed', 'add',
            array(  'icon'=>$icon,
                'appid'=>UC_APPID,
                'uid'=>$uid,
                'username'=>$username,
                'title_template'=>$title_template,
                'title_data'=>$title_data,
                'body_template'=>$body_template,
                'body_data'=>$body_data,
                'body_general'=>$body_general,
                'target_ids'=>$target_ids,
                'image_1'=>$images[0]['url'],
                'image_1_link'=>$images[0]['link'],
                'image_2'=>$images[1]['url'],
                'image_2_link'=>$images[1]['link'],
                'image_3'=>$images[2]['url'],
                'image_3_link'=>$images[2]['link'],
                'image_4'=>$images[3]['url'],
                'image_4_link'=>$images[3]['link']
            )
        );
    }

    function ucFeedGet($limit = 100, $delete = TRUE) {
        $return = $this->ucApiPost('feed', 'get', array('limit'=>$limit, 'delete'=>$delete));
        return UC_CONNECT == 'mysql' ? $return : $this->ucUnserialize($return);
    }

    function ucFriendAdd($uid, $friendid, $comment='') {
        return $this->ucApiPost('friend', 'add', array('uid'=>$uid, 'friendid'=>$friendid, 'comment'=>$comment));
    }

    function ucFriendDelete($uid, $friendids) {
        return $this->ucApiPost('friend', 'delete', array('uid'=>$uid, 'friendids'=>$friendids));
    }

    function ucFriendTotalnum($uid, $direction = 0) {
        return $this->ucApiPost('friend', 'totalnum', array('uid'=>$uid, 'direction'=>$direction));
    }

    function ucFriendLs($uid, $page = 1, $pagesize = 10, $totalnum = 10, $direction = 0) {
        $return = $this->ucApiPost('friend', 'ls', array('uid'=>$uid, 'page'=>$page, 'pagesize'=>$pagesize, 'totalnum'=>$totalnum, 'direction'=>$direction));
        return UC_CONNECT == 'mysql' ? $return : $this->ucUnserialize($return);
    }

    function ucUserRegister($username, $password, $email, $questionid = '', $answer = '', $regip = '') {
        return $this->ucApiPost('user', 'register2', array('username'=>$username, 'password'=>$password, 'email'=>$email, 'questionid'=>$questionid, 'answer'=>$answer, 'regip' => $regip));
    }

    function ucUserLogin($username, $password, $isuid = 0, $checkques = 0, $questionid = '', $answer = '') {
        $isuid = intval($isuid);
        $return = $this->ucApiPost('user', 'login', array('username'=>$username, 'password'=>$password, 'isuid'=>$isuid, 'checkques'=>$checkques, 'questionid'=>$questionid, 'answer'=>$answer));
        return UC_CONNECT == 'mysql' ? $return : $this->ucUnserialize($return);
    }

    function ucUserSynlogin($uid) {
        $uid = intval($uid);
        if(@include UC_ROOT.'./data/cache/apps.php') {
            if(count($_CACHE['apps']) > 1) {
                $return = ucApiPost('user', 'synlogin', array('uid'=>$uid));
            } else {
                $return = '';
            }
        }
        return $return;
    }

    function ucUserSynlogout() {
        if(@include UC_ROOT.'./data/cache/apps.php') {
            if(count($_CACHE['apps']) > 1) {
                $return = ucApiPost('user', 'synlogout', array());
            } else {
                $return = '';
            }
        }
        return $return;
    }

    function ucUserEdit($username, $oldpw, $newpw, $email, $ignoreoldpw = 0, $questionid = '', $answer = '') {
        return $this->ucApiPost('user', 'edit', array('username'=>$username, 'oldpw'=>$oldpw, 'newpw'=>$newpw, 'email'=>$email, 'ignoreoldpw'=>$ignoreoldpw, 'questionid'=>$questionid, 'answer'=>$answer));
    }

    function ucUserDelete($uid) {
        return $this->ucApiPost('user', 'delete', array('uid'=>$uid));
    }

    function ucUserDeleteavatar($uid) {
        ucApiPost('user', 'deleteavatar', array('uid'=>$uid));
    }

    function ucUserCheckname($username) {
        return $this->ucApiPost('user', 'check_username', array('username'=>$username));
    }

    function ucUserCheckemail($email) {
        return $this->ucApiPost('user', 'check_email', array('email'=>$email));
    }

    function ucUserCheckmobile($mobile) {
        return $this->ucApiPost('user', 'check_mobile', array('mobile'=>$mobile));
    }

    function ucUserAddprotected($username, $admin='') {
        return $this->ucApiPost('user', 'addprotected', array('username'=>$username, 'admin'=>$admin));
    }

    function ucUserDeleteprotected($username) {
        return $this->ucApiPost('user', 'deleteprotected', array('username'=>$username));
    }

    function ucUserGetprotected() {
        $return = $this->ucApiPost('user', 'getprotected', array('1'=>1));
        return UC_CONNECT == 'mysql' ? $return : $this->ucUnserialize($return);
    }

    function ucGetUser($username, $isuid=0) {
        $return = $this->ucApiPost('user', 'get_user', array('username'=>$username, 'isuid'=>$isuid));
        return UC_CONNECT == 'mysql' ? $return : $this->ucUnserialize($return);
    }

    function ucUserMerge($oldusername, $newusername, $uid, $password, $email) {
        return $this->ucApiPost('user', 'merge', array('oldusername'=>$oldusername, 'newusername'=>$newusername, 'uid'=>$uid, 'password'=>$password, 'email'=>$email));
    }

    function ucUserMergeRemove($username) {
        return $this->ucApiPost('user', 'merge_remove', array('username'=>$username));
    }

    function ucUserGetcredit($appid, $uid, $credit) {
        return ucApiPost('user', 'getcredit', array('appid'=>$appid, 'uid'=>$uid, 'credit'=>$credit));
    }

    function ucPmLocation($uid, $newpm = 0) {
        $apiurl = ucApiUrl('pm_client', 'ls', "uid=$uid", ($newpm ? '&folder=newbox' : ''));
        @header("Expires: 0");
        @header("Cache-Control: private, post-check=0, pre-check=0, max-age=0", FALSE);
        @header("Pragma: no-cache");
        @header("location: $apiurl");
    }

    function ucPmChecknew($uid, $more = 0) {
        $return = $this->ucApiPost('pm', 'check_newpm', array('uid'=>$uid, 'more'=>$more));
        return (!$more || UC_CONNECT == 'mysql') ? $return : $this->ucUnserialize($return);
    }

    function ucPmSend($fromuid, $msgto, $subject, $message, $instantly = 1, $replypmid = 0, $isusername = 0, $type = 0) {
        if($instantly) {
            $replypmid = @is_numeric($replypmid) ? $replypmid : 0;
            return $this->ucApiPost('pm', 'sendpm', array('fromuid'=>$fromuid, 'msgto'=>$msgto, 'subject'=>$subject, 'message'=>$message, 'replypmid'=>$replypmid, 'isusername'=>$isusername, 'type' => $type));
        } else {
            $fromuid = intval($fromuid);
            $subject = rawurlencode($subject);
            $msgto = rawurlencode($msgto);
            $message = rawurlencode($message);
            $replypmid = @is_numeric($replypmid) ? $replypmid : 0;
            $replyadd = $replypmid ? "&pmid=$replypmid&do=reply" : '';
            $apiurl = ucApiUrl('pm_client', 'send', "uid=$fromuid", "&msgto=$msgto&subject=$subject&message=$message$replyadd");
            @header("Expires: 0");
            @header("Cache-Control: private, post-check=0, pre-check=0, max-age=0", FALSE);
            @header("Pragma: no-cache");
            @header("location: ".$apiurl);
        }
    }

    function ucPmDelete($uid, $folder, $pmids) {
        return $this->ucApiPost('pm', 'delete', array('uid'=>$uid, 'pmids'=>$pmids));
    }

    function ucPmDeleteuser($uid, $touids) {
        return $this->ucApiPost('pm', 'deleteuser', array('uid'=>$uid, 'touids'=>$touids));
    }

    function ucPmDeletechat($uid, $plids, $type = 0) {
        return $this->ucApiPost('pm', 'deletechat', array('uid'=>$uid, 'plids'=>$plids, 'type'=>$type));
    }

    function ucPmReadstatus($uid, $uids, $plids = array(), $status = 0) {
        return $this->ucApiPost('pm', 'readstatus', array('uid'=>$uid, 'uids'=>$uids, 'plids'=>$plids, 'status'=>$status));
    }

    function ucPmList($uid, $page = 1, $pagesize = 10, $folder = 'inbox', $filter = 'newpm', $msglen = 0) {
        $uid = intval($uid);
        $page = intval($page);
        $pagesize = intval($pagesize);
        $return = $this->ucApiPost('pm', 'ls', array('uid'=>$uid, 'page'=>$page, 'pagesize'=>$pagesize, 'filter'=>$filter, 'msglen'=>$msglen));
        return UC_CONNECT == 'mysql' ? $return : $this->ucUnserialize($return);
    }

    function ucPmIgnore($uid) {
        $uid = intval($uid);
        return $this->ucApiPost('pm', 'ignore', array('uid'=>$uid));
    }

    function ucPmView($uid, $pmid = 0, $touid = 0, $daterange = 1, $page = 0, $pagesize = 10, $type = 0, $isplid = 0) {
        $uid = intval($uid);
        $touid = intval($touid);
        $page = intval($page);
        $pagesize = intval($pagesize);
        $pmid = @is_numeric($pmid) ? $pmid : 0;
        $return = $this->ucApiPost('pm', 'view', array('uid'=>$uid, 'pmid'=>$pmid, 'touid'=>$touid, 'daterange'=>$daterange, 'page' => $page, 'pagesize' => $pagesize, 'type'=>$type, 'isplid'=>$isplid));
        return UC_CONNECT == 'mysql' ? $return : $this->ucUnserialize($return);
    }

    function ucPmViewNum($uid, $touid, $isplid) {
        $uid = intval($uid);
        $touid = intval($touid);
        $isplid = intval($isplid);
        return $this->ucApiPost('pm', 'viewnum', array('uid' => $uid, 'touid' => $touid, 'isplid' => $isplid));
    }

    function ucPmViewnode($uid, $type, $pmid) {
        $uid = intval($uid);
        $type = intval($type);
        $pmid = @is_numeric($pmid) ? $pmid : 0;
        $return = $this->ucApiPost('pm', 'viewnode', array('uid'=>$uid, 'type'=>$type, 'pmid'=>$pmid));
        return UC_CONNECT == 'mysql' ? $return : $this->ucUnserialize($return);
    }

    function ucPmChatpmmemberlist($uid, $plid = 0) {
        $uid = intval($uid);
        $plid = intval($plid);
        $return = $this->ucApiPost('pm', 'chatpmmemberlist', array('uid'=>$uid, 'plid'=>$plid));
        return UC_CONNECT == 'mysql' ? $return : $this->ucUnserialize($return);
    }

    function ucPmKickchatpm($plid, $uid, $touid) {
        $uid = intval($uid);
        $plid = intval($plid);
        $touid = intval($touid);
        return $this->ucApiPost('pm', 'kickchatpm', array('uid'=>$uid, 'plid'=>$plid, 'touid'=>$touid));
    }

    function ucPmAppendchatpm($plid, $uid, $touid) {
        $uid = intval($uid);
        $plid = intval($plid);
        $touid = intval($touid);
        return $this->ucApiPost('pm', 'appendchatpm', array('uid'=>$uid, 'plid'=>$plid, 'touid'=>$touid));
    }

    function ucPmBlacklsGet($uid) {
        $uid = intval($uid);
        return $this->ucApiPost('pm', 'blackls_get', array('uid'=>$uid));
    }

    function ucPmBlacklsSet($uid, $blackls) {
        $uid = intval($uid);
        return $this->ucApiPost('pm', 'blackls_set', array('uid'=>$uid, 'blackls'=>$blackls));
    }

    function ucPmBlacklsAdd($uid, $username) {
        $uid = intval($uid);
        return $this->ucApiPost('pm', 'blackls_add', array('uid'=>$uid, 'username'=>$username));
    }

    function ucPmBlacklsDelete($uid, $username) {
        $uid = intval($uid);
        return $this->ucApiPost('pm', 'blackls_delete', array('uid'=>$uid, 'username'=>$username));
    }

    function ucDomainLs() {
        $return = $this->ucApiPost('domain', 'ls', array('1'=>1));
        return UC_CONNECT == 'mysql' ? $return : $this->ucUnserialize($return);
    }

    function ucCreditExchangeRequest($uid, $from, $to, $toappid, $amount) {
        $uid = intval($uid);
        $from = intval($from);
        $toappid = intval($toappid);
        $to = intval($to);
        $amount = intval($amount);
        return $this->ucApiPost('credit', 'request', array('uid'=>$uid, 'from'=>$from, 'to'=>$to, 'toappid'=>$toappid, 'amount'=>$amount));
    }

    function ucTagGet($tagname, $nums = 0) {
        $return = $this->ucApiPost('tag', 'gettag', array('tagname'=>$tagname, 'nums'=>$nums));
        return UC_CONNECT == 'mysql' ? $return : $this->ucUnserialize($return);
    }

    function ucAvatar($uid, $type = 'virtual', $returnhtml = 1) {
        $uid = intval($uid);
        $uc_input = $this->ucApiInput("uid=$uid");
        $uc_avatarflash = UC_API.'/images/camera.swf?inajax=1&appid='.UC_APPID.'&input='.$uc_input.'&agent='.md5($_SERVER['HTTP_USER_AGENT']).'&ucapi='.urlencode(str_replace('http://', '', UC_API)).'&avatartype='.$type.'&uploadSize=2048';
        if($returnhtml) {
            return '<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,0,0" width="450" height="253" id="mycamera" align="middle">
                <param name="allowScriptAccess" value="always" />
                <param name="scale" value="exactfit" />
                <param name="wmode" value="transparent" />
                <param name="quality" value="high" />
                <param name="bgcolor" value="#ffffff" />
                <param name="movie" value="'.$uc_avatarflash.'" />
                <param name="menu" value="false" />
                <embed src="'.$uc_avatarflash.'" quality="high" bgcolor="#ffffff" width="450" height="253" name="mycamera" align="middle" allowScriptAccess="always" allowFullScreen="false" scale="exactfit"  wmode="transparent" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer" />
            </object>';
        } else {
            return array(
                'width', '450',
                'height', '253',
                'scale', 'exactfit',
                'src', $uc_avatarflash,
                'id', 'mycamera',
                'name', 'mycamera',
                'quality','high',
                'bgcolor','#ffffff',
                'menu', 'false',
                'swLiveConnect', 'true',
                'allowScriptAccess', 'always'
            );
        }
    }

    function ucMailQueue($uids, $emails, $subject, $message, $frommail = '', $charset = 'gbk', $htmlon = FALSE, $level = 1) {
        return $this->ucApiPost('mail', 'add', array('uids' => $uids, 'emails' => $emails, 'subject' => $subject, 'message' => $message, 'frommail' => $frommail, 'charset' => $charset, 'htmlon' => $htmlon, 'level' => $level));
    }

    function ucCheckAvatar($uid, $size = 'middle', $type = 'virtual') {
        $url = UC_API."/avatar.php?uid=$uid&size=$size&type=$type&check_file_exists=1";
        $res = $this->ucFopen2($url, 500000, '', '', TRUE, UC_IP, 20);
        if($res == 1) {
            return 1;
        } else {
            return 0;
        }
    }

    function ucCheckVersion() {
        $return = ucApiPost('version', 'check', array());
        $data = $this->ucUnserialize($return);
        return is_array($data) ? $data : $return;
    }

    function ucSmsGetRegcode($mobile, $client_ip = '') {
        $return = $this->ucApiPost('sms', 'get_regcode', array('mobile'=>$mobile, 'client_ip'=>$client_ip));
        return UC_CONNECT == 'mysql' ? $return : $this->ucUnserialize($return);
    }

    function ucSmsGetEditcode($mobile, $client_ip = '') {
        $return = $this->ucApiPost('sms', 'get_editcode', array('mobile'=>$mobile, 'client_ip'=>$client_ip));
        return UC_CONNECT == 'mysql' ? $return : $this->ucUnserialize($return);
    }

    function ucSmsCheckCode($mobile, $code) {
        return $this->ucApiPost('sms', 'check_code', array('mobile'=>$mobile, 'code'=>$code));
    }

}
