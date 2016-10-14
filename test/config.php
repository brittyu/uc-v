<?php

define('IN_UC', TRUE);
define('UC_CLIENT_VERSION', '1.6.0');
define('UC_CLIENT_RELEASE', '20110501');
define('UC_ROOT', substr(__FILE__, 0, -10));
define('UC_DATADIR', UC_ROOT.'./data/');
define('UC_DATAURL', UC_API.'/data');
define('UC_API_FUNC', UC_CONNECT == 'mysql' ? 'uc_api_mysql' : 'uc_api_post');

define('UC_CONNECT', 'NULL');				// 连接 UCenter 的方式: mysql/NULL, 默认为空时为 fscoketopen()


//通信相关
define('UC_APPID', 2);									// 当前应用的 ID
define('UC_KEY', 'xxxxxxxxxxxxxxxxx');	// 与 UCenter 的通信密钥, 要与 UCenter 保持一致
define('UC_API', 'http://check.com');					// UCenter 的 URL 地址, 在调用头像时依赖此常量
define('UC_CHARSET', 'utf8');							// UCenter 的字符集
define('UC_IP', '');									// UCenter 的 IP, 当 UC_CONNECT 为非 mysql 方式时, 并且当前应用服务器解析域名有问题时, 请设置此值
