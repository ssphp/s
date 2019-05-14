<?php
// +----------------------------------------------------------------------+
// | S Framework ( MSVC ) - More than the MVC |
// +----------------------------------------------------------------------+
// | Version: 0.2 |
// +----------------------------------------------------------------------+
// | Author: Star <msvctop@126.com> |
// | QQ Group: 380748 |
// +----------------------------------------------------------------------+
class MSVC
{

    static $ROOT = null;
    private static $_config = [
        'SITE' => [],
        'MODE' => [],
        'REWRITE' => [],
        'DB' => [],
        'MM' => [],
        'MM_OPTIONS' => [],
        'SERVICE' => [],
        'MODULE' => [],
        'JSON_CONTENT_TYPE' => 'application/json; charset=UTF-8',
        'VIEW_CACHE_PATH' => '/tmp/_views/',
        'LANG_PATH' => 'langs/',
        'LOG_PATH' => 'logs/',
        'DATA_PATH' => 'data/',
        'SESSION_PLACE' => 'COOKIE',
        'SESSION_NAME' => 'MSVCSESSION',
        'DISABLE_VIEW_CACHE' => false,
        'ENABLE_MSVC_CROSS' => false,
        'ONREWRITE' => [],
        'ONIN' => [],
        'ONOUT' => [],
        'ONERROR' => [],
    ];
    private static $_site_paths = [];
    private static $_site_view_tags = [];
    private static $_site_view_replace = [];
    private static $_auto_classes = [];
    private static $_mode = null;
    private static $_site = null;
    private static $_path = null;
    private static $_exists_site_path = false;
    private static $_is_service = false;
    private static $_all_errors = [];
    private static $_response_code = 200;
    private static $_response_headers = [];
    static $_call_logs = [];
    private static $_is_succeed = false;
    private static $_display_errors = false;
    private static $_default_view_tag_replaces = [
        '<!--\s*@(.+?)\s*-->',
        '<!--\s*#(.+?)\s*-->',
        '<!--\s*/(.*?)-->',
        '<([\w\.]*?:\w+)\s*(.*?)>',
        '</([\w\.]*?):(\w+?)\s*>',
        '<!--\s*if\s+(.+?)\s*-->',
        '<!--\s*/if(.*?)-->',
        '<!--\s*else\s*-->',
        '<!--\s*else\s*if\s+(.+?)\s*-->',
        '{(.+?\\?.+?\\:.+?)}',
        '{\$(.+?)}',
        '{#(.+?)}',
    ];
    private static $_view_tag_replaces = [
        '<?php $tmp_view_file = MSVC::_makeViewFile( "\\1" ); if( $tmp_view_file ) include $tmp_view_file; ?>',
        '<?php if( is_array($\\1) ) foreach( $\\1 as $key => $value ){ if( is_array($value) ) extract($value); ?>',
        '<?php } ?>',
        '<?php $tmp_tag_result = MSVC::callTagController( "\\1" ); if( is_array($tmp_tag_result) ) extract($tmp_tag_result); ?>',
        '',
        '<?php if( \\1 ){ ?>',
        '<?php } ?>',
        '<?php }else{ ?>',
        '<?php }else if( \\1 ){ ?>',
        '<?=($\\1)?>',
        '<?=$\\1?>',
        '<?=MSVC::getLang("\\1")?>',
    ];

    //http response
    public static $swoole_response;

    public static $is_swoole;

    public static $caller;

    /**
     * 调用其他服务
     */
    public static function caller()
    { }

    public static function header($string, $value = '', $replace = true, $http_response_code = 0)
    {
        if (self::$is_swoole) {
            self::$swoole_response->header($string, $value);
            if ($http_response_code > 0) {
                self::$swoole_response->status($http_response_code);
            }
        } else {
            header($string . ': ' . $value, $replace, $http_response_code);
        }
    }

    public static function setcookie($name, $value = "", $expire = 0, $path = "", $domain = "", $secure = false, $httponly = false)
    {
        $ret = false;
        if (self::$is_swoole) {
            $ret = self::$swoole_response->cookie($name, $value, $expire, $path, $domain, $secure, $httponly);
        } else {
            $ret = setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
        }
        return $ret;
    }

    public static function end()
    {
        if (self::$is_swoole) {
            $result = ob_get_contents();
            self::$swoole_response->end($result);
            // return
        }
        // exit();
        // return;
    }

    public static function file_get_contents()
    {
        if (self::$is_swoole) {
            return self::$swoole_request->rawContent();
        }
        return file_get_contents("php://input");
    }

    public static function getSite()
    {
        return MSVC::$_site;
    }

    public static function getRunMode()
    {
        return MSVC::$_mode;
    }

    public static function getResponseCode()
    {
        return MSVC::$_response_code;
    }

    public static function getResponseHeaders()
    {
        return MSVC::$_response_headers;
    }

    public static function isDev()
    {
        return MSVC::$_display_errors;
    }

    private static function _loadSite($site)
    {

        $config_file = $site ? MSVC::$ROOT . "$site/config.php" : MSVC::$ROOT . "config.php";
        $user_config_file = str_replace('config.php', 'user_config.php', $config_file);
        $check_config_files = [
            $config_file,
            $user_config_file,
        ];
        if ($site) {
            $check_config_files[] = MSVC::$ROOT . "config.php";
            $check_config_files[] = MSVC::$ROOT . "user_config.php";
        }

        // 判断是否已经缓存配置
        // $cached_configs = MSVC::_getCache( $config_file, $check_config_files );
        // if( $cached_configs )
        // {
        // list( MSVC::$_config, MSVC::$_site_paths, MSVC::$_site_view_replace, MSVC::$_site_view_tags ) = $cached_configs;
        // return;
        // }

        if (file_exists($config_file)) {

            $config = null;
            include $config_file;

            if (file_exists($user_config_file)) {
                include $user_config_file;
            }

            if ($config === null) {
                $config = [];
            }
        }

        if ($config) {

            if ($config['SERVICE']) {
                // 确保 SERVICE 配置中路径都是 . 结尾，url都是 / 结尾
                $service_config = [];
                foreach ($config['SERVICE'] as $part => $url) {
                    $service_config[$part[strlen($part) - 1] != '.' ? $part . '.' : $part] = $url[strlen($url) - 1] != '/' ? $url . '/' : $url;
                }

                $config['SERVICE'] = $service_config;
            }

            foreach (MSVC::$_config as $key => &$configs) {
                if ($config[$key]) {
                    if (is_array($config[$key])) {
                        $configs = array_merge($configs, $config[$key]);
                    } else if (strpos($site, 'modules/') === false) // 其他设置忽略模块中的配置
                    {
                        $configs = $config[$key];
                    }
                }
            }

            foreach ($config as $key => $value) {
                if (!MSVC::$_config[$key]) {
                    MSVC::$_config[$key] = $value;
                }
            }
        }

        MSVC::$_site_paths[$site] = array();
        MSVC::$_site_paths[$site]['M'] = $config['PATH'] && $config['PATH']['M'] ? $config['PATH']['M'] : '_m';
        MSVC::$_site_paths[$site]['S'] = $config['PATH'] && $config['PATH']['S'] ? $config['PATH']['S'] : '_s';
        MSVC::$_site_paths[$site]['V'] = $config['PATH'] && $config['PATH']['V'] ? $config['PATH']['V'] : '_v';
        MSVC::$_site_paths[$site]['C'] = $config['PATH'] && $config['PATH']['C'] ? $config['PATH']['C'] : '_c';
        MSVC::$_site_paths[$site]['TASK'] = $config['PATH'] && $config['PATH']['TASK'] ? $config['PATH']['TASK'] : '_task';
        MSVC::$_site_paths[$site]['TEST'] = $config['PATH'] && $config['PATH']['TEST'] ? $config['PATH']['TEST'] : '_test';

        MSVC::$_site_view_replace[$site] = $site ? MSVC::$_site_view_replace[''] : [
            [],
            [],
        ];
        if ($config['VIEW_REPLACE']) {
            foreach ($config['VIEW_REPLACE'] as $k => $v) {
                $k = "~$k~i";
                MSVC::$_site_view_replace[$site][0][] = $k;
                MSVC::$_site_view_replace[$site][1][] = $v;
            }
        }

        if ($config['VIEW_TAG']) {
            MSVC::$_site_view_tags[$site] = $config['VIEW_TAG'];
            foreach (MSVC::$_site_view_tags[$site] as &$tag) {
                $tag = "~$tag~i";
            }
        } else if ($site) {
            MSVC::$_site_view_tags[$site] = MSVC::$_site_view_tags[''];
        } else {
            MSVC::$_site_view_tags[$site] = MSVC::$_default_view_tag_replaces;
            foreach (MSVC::$_site_view_tags[$site] as &$tag) {
                $tag = "~$tag~i";
            }
        }

        // MSVC::_setCache( $config_file, [MSVC::$_config, MSVC::$_site_paths, MSVC::$_site_view_replace, MSVC::$_site_view_tags], $check_config_files );
    }

    private static function _callFuncs($funcs, $data)
    {
        if ($funcs && is_array($funcs)) {
            foreach ($funcs as $func) {
                $result = $func($data);
                if ($result !== null) {
                    $data = $result;
                }
            }
        }
        return $data;
    }

    private static function _makeError($message)
    {
        MSVC::log($message, 'error');
        if (MSVC::$_config['ONERROR']) {
            if (MSVC::$_config['ONERROR']) {
                MSVC::_callFuncs(MSVC::$_config['ONERROR'], $message);
            }
        } else if (!MSVC::$_is_service && MSVC::$_display_errors && MSVC::$_mode != 'MS') {
            if (MSVC::$_mode[0] != 'T') {
                echo nl2br("\n" . $message);
            } else {
                echo "\n" . $message;
            }
        }
    }

    public static function log($message, $case = 'info')
    {
        if (!is_string($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }

        $log_time = date('Y-m-d H:i:s');
        if ($case == 'error') {
            ob_start();
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $trace = ob_get_contents();
            ob_end_clean();
            MSVC::$_all_errors[] = "$log_time	$message";
            $message = "$message\n$trace";
        }
        $message = "$log_time	$message\n";

        $log_file_path = MSVC::$_config['LOG_PATH'] . (MSVC::$_site ? MSVC::$_site : 'default') . '/' . $case;
        if (!file_exists($log_file_path)) {
            mkdir($log_file_path, 0755, true);
        }

        $log_file_file = $log_file_path . '/' . date('Ymd') . '.log';
        if (file_put_contents($log_file_file, $message, FILE_APPEND) === false) {
            openlog('MSVC', LOG_PID, LOG_LOCAL0);
            syslog(LOG_WARNING, "MSVC log file [$log_file_file] can't access!\n[$case] " . $message);
            closelog();
        }

        MSVC::$_all_errors = [];
    }

    public static function getAllErrors()
    {
        return MSVC::$_all_errors;
    }

    public static function _errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        if (
            $errno == E_DEPRECATED || $errno == E_USER_DEPRECATED || $errno == E_STRICT || ($errno == E_NOTICE && (strstr($errstr, 'ndefined') || strstr($errstr, 'Uninitialized')))
        ) {
            return true;
        }

        if ($errno == E_ERROR || $errno == E_WARNING || $errno == E_USER_ERROR || $errno == E_USER_WARNING) {
            //             if( $errno == E_ERROR || $errno == E_USER_ERROR ) header( 'HTTP/1.1 500 Internal Server Error' );
            $error_str = "$errno, $errstr, $errfile, $errline";
            MSVC::_makeError($error_str);
        } else if (MSVC::$_display_errors) {
            MSVC::log("$errno, $errstr, $errfile, $errline", 'notice');
        }
        return true;
    }

    // static function _exceptionHandler( $ex )
    // {
    // header( 'HTTP/1.1 500 Internal Server Error' );
    // MSVC::_makeError( $ex->getMessage()."\n".$ex->getTraceAsString() );
    // return true;
    // }
    public static function _shutdownHandler()
    {
        if ($error = error_get_last()) {
            $errno = $error['type'];
            if ($errno == E_ERROR || $errno == E_WARNING || $errno == E_USER_ERROR || $errno == E_USER_WARNING) {
                $error_str = join(', ', error_get_last());
                if ($errno == E_ERROR || $errno == E_USER_ERROR) {
                    if (MSVC::$_mode == 'MS') {
                        // header('Content-Type: ' . MSVC::$_config['JSON_CONTENT_TYPE']);
                        self::header('Content-Type: ' . MSVC::$_config['JSON_CONTENT_TYPE']);
                        echo json_encode(FAILED('未知错误', 500), JSON_UNESCAPED_UNICODE);
                    } else {
                        // header('HTTP/1.1 500 Internal Server Error');
                        self::header('HTTP/1.1 500 Internal Server Error');
                    }
                }
                MSVC::_makeError($error_str);
            }
        }
    }

    private static function _autoload($name)
    {
        if (isset(MSVC::$_auto_classes[$name])) {
            include MSVC::$_auto_classes[$name];
        }
    }

    // static function tt( $name )
    // {
    // if( !$GLOBALS['prev_time'] ) $GLOBALS['prev_time'] = $_SERVER['REQUEST_TIME_FLOAT'];
    // $now = microtime( true );
    // $used = $now - $GLOBALS['prev_time'];
    // $GLOBALS['prev_time'] = $now;
    // file_put_contents( '/tmp/a', str_pad($name, 20).$used." ".($now-$_SERVER['REQUEST_TIME_FLOAT'])."\n", FILE_APPEND );
    // }

    /**
     * 启动
     */
    public static function init($root_path)
    {
        try {
            // 设置错误处理函数
            set_error_handler(array(
                'MSVC',
                '_errorHandler',
            ), E_ALL);
            // set_exception_handler( array('MSVC', '_exceptionHandler'));
            register_shutdown_function(array(
                'MSVC',
                '_shutdownHandler',
            ));
            // 初始化路径
            // if ($root_path[0] == '.') {
            //     if ($root_path[1] == '.') {
            //         $root_path = dirname(getcwd());
            //     } else {
            //         $root_path = getcwd();
            //     }

            // } else if ($root_path[0] != '/') {
            //     $root_path = getcwd() . '/' . $root_path;
            // }
            $root_path = __DIR__ . '/..';

            MSVC::$ROOT = $root_path . '/';
            MSVC::$_config['LOG_PATH'] = MSVC::$ROOT . MSVC::$_config['LOG_PATH'];
            MSVC::$_config['DATA_PATH'] = MSVC::$ROOT . MSVC::$_config['DATA_PATH'];

            // 载入基本配置
            MSVC::$_display_errors = ini_get('display_errors');
            MSVC::_loadSite('');
            if (MSVC::$_config['LANG_PATH'][0] != '/') {
                MSVC::$_config['LANG_PATH'] = MSVC::$ROOT . MSVC::$_config['LANG_PATH'];
            }

            if (MSVC::$_config['LOG_PATH'][0] != '/' && DIRECTORY_SEPARATOR != '\\') {
                MSVC::$_config['LOG_PATH'] = MSVC::$ROOT . MSVC::$_config['LOG_PATH'];
            }

            if (MSVC::$_config['DATA_PATH'][0] != '/' && DIRECTORY_SEPARATOR != '\\') {
                MSVC::$_config['DATA_PATH'] = MSVC::$ROOT . MSVC::$_config['DATA_PATH'];
            }

            if (DIRECTORY_SEPARATOR == '\\') {
                MSVC::$_config['VIEW_CACHE_PATH'] = 'c:/_views/';
            }

            // 注册自动加载
            spl_autoload_register([
                'MSVC',
                '_autoload',
            ]);

            // 处理 lib 自动加载
            $lib_dir = MSVC::$ROOT . 'lib/';
            if (file_exists($lib_dir)) {
                $d = dir($lib_dir);
                if ($d) {
                    while ($f = $d->read()) {
                        $full_path = MSVC::$ROOT . 'lib/' . $f;
                        if ($f[0] == '.') {
                            continue;
                        }

                        if (is_dir($full_path)) {
                            $loader_path = "$full_path/loader.php";
                            $class_path = "$full_path/$f.php";
                            if (file_exists($loader_path)) // 自动引用 loader.php 处理自动载入初始化
                            {
                                include $loader_path;
                            } else if (file_exists($class_path)) // 将入口类放入自动加载列表
                            {
                                MSVC::$_auto_classes[$f] = $class_path;
                            }
                        } else if (strpos($f, '.php') !== false) {
                            MSVC::$_auto_classes[substr($f, 0, -4)] = $full_path;
                        }
                    }
                    $d->close();
                }
            }
        } catch (Exception $ex) {
            echo $ex->getMessage();
        }
    }

    private static function _test_log($msg, $title = '')
    {
        echo "\n\n--------------------------------------- $title--------------------------------------\n";
        var_dump($msg);
        echo "--------------------------------------end $title--------------------------------------\n\n";
    }

    /**
     * 启动
     */
    public static function start($mode = 'MSVC')
    {
        MSVC::$_mode = $mode;
        if (!ini_get('date.timezone')) {
            ini_set('date.timezone', 'PRC');
        }

        if ($mode == 'MS') {
            ini_set('display_errors', false);
        }

        try {
            if ($mode[0] != 'T') {
                if ($_SERVER['PATH_INFO'] == '/favicon.ico') {
                    // exit();
                    return;
                }

                ob_start();
            }

            // 限制 Test Task 必须使用命令行调用
            if ($mode[0] == 'T') {
                if (PHP_SAPI != 'cli') {
                    return E("call $mode must on cli!\n");
                }

                if ($GLOBALS['argc'] < 2) {
                    return E("call $mode must have args!\n");
                }

                list($t_site, $t_class_path) = explode('.', $GLOBALS['argv'][1], 2);
                if (!$t_site) {
                    $t_site = 'default';
                }

                if (!$t_site || !$t_class_path) {
                    return E("call $mode must have args!\n");
                }
            }

            if ($mode[0] != 'T') {
                // 处理URL映射
                $port_pos = strpos($_SERVER['HTTP_HOST'], ':');
                $request = ($port_pos === false ? $_SERVER['HTTP_HOST'] : substr($_SERVER['HTTP_HOST'], 0, $port_pos)) . $_SERVER['PATH_INFO'];
                if (MSVC::$_config['REWRITE']) {
                    foreach (MSVC::$_config['REWRITE'] as $from => $to) {
                        $request = preg_replace("#$from#", $to, $request);
                    }
                }

                // 处理前置回调
                if (MSVC::$_config['ONREWRITE']) {
                    $request = MSVC::_callFuncs(MSVC::$_config['ONREWRITE'], $request);
                }

                // 定位站点
                $path = null;
                foreach (MSVC::$_config['SITE'] as $tmp_url => $tmp_site) {
                    if (strpos($request, $tmp_url) === 0) {
                        MSVC::$_site = $tmp_site;
                        $path = str_replace($tmp_url, '', $request);
                        break;
                    }
                }

                if (!MSVC::$_site && MSVC::$_config['SITE']['default'] !== null) {
                    MSVC::$_site = MSVC::$_config['SITE']['default'];
                }
                $path = !empty($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : $_SERVER['REQUEST_URI'];
            } else {
                MSVC::$_site = $t_site;
            }

            if (MSVC::$_site === null) {
                throw new Exception("No Site $request", 403);
            }

            if (MSVC::$_config['MODE'][MSVC::$_site]) {
                $mode = MSVC::$_config['MODE'][MSVC::$_site];
            }

            if ($mode == 'MVC' || $mode[0] == 'T') {
                MSVC::$_config['ENABLE_MSVC_CROSS'] = true;
            }

            MSVC::$_is_service = $mode == 'MS';
            $site_path = MSVC::$ROOT . MSVC::$_site;

            if (MSVC::$_site && file_exists($site_path)) {
                MSVC::$_exists_site_path = true;
                MSVC::_loadSite(MSVC::$_site);
            }

            if (MSVC::$_config['LANG_PATH'][strlen(MSVC::$_config['LANG_PATH']) - 1] != '/') {
                MSVC::$_config['LANG_PATH'] .= '/';
            }

            if (MSVC::$_config['LOG_PATH'][strlen(MSVC::$_config['LOG_PATH']) - 1] != '/') {
                MSVC::$_config['LOG_PATH'] .= '/';
            }

            if (MSVC::$_config['DATA_PATH'][strlen(MSVC::$_config['DATA_PATH']) - 1] != '/') {
                MSVC::$_config['DATA_PATH'] .= '/';
            }

            if (MSVC::$_config['VIEW_CACHE_PATH'][strlen(MSVC::$_config['VIEW_CACHE_PATH']) - 1] != '/') {
                MSVC::$_config['VIEW_CACHE_PATH'] .= '/';
            }

            $site = MSVC::$_site ? MSVC::$_site : 'default';
            if (strpos(MSVC::$_config['DATA_PATH'], $site) === false) {
                MSVC::$_config['DATA_PATH'] .= $site . '/';
            }
            // MSVC::$_config['DATA_PATH'] .= (MSVC::$_site ? MSVC::$_site : 'default') . '/';
            if (!file_exists(MSVC::$_config['DATA_PATH'])) {
                mkdir(MSVC::$_config['DATA_PATH'], 0755, true);
            }

            if ($mode[0] != 'T') {
                // 启动 session
                $session_name = MSVC::$_config['SESSION_NAME'];
                session_name($session_name);
                if (MSVC::$_config['SESSION_PLACE'] === 'HEADER') {
                    $session_id = $_SERVER['HTTP_' . $session_name];
                    ini_set('session.use_cookies', 0);
                } else {
                    $session_id = $_COOKIE[$session_name];
                }
                if (!$session_id) {
                    $prefix = dechex(mt_rand(100000000, 999999999));
                    if (function_exists('uuid_create')) {
                        $session_id = $prefix . strtolower(str_replace('-', '', uuid_create()));
                    } else {
                        $session_id = str_replace('.', '', uniqid($prefix, true)) . dechex(mt_rand(100000000, 999999999));
                    }

                    if (MSVC::$_config['SESSION_PLACE'] === 'HEADER') {
                        // header('SID: ' . $session_id);
                        self::header('SID: ' . $session_id);
                    }
                }
                session_id($session_id);
                if (PHP_SAPI != 'cli') {
                    session_start();
                }

                $call_start_time = microtime(true);
            }
            $output = null;
            if ($mode[0] != 'T') {
                // 处理前置回调
                if (MSVC::$_config['ONIN']) {
                    $output = MSVC::_callFuncs(MSVC::$_config['ONIN'], $output);
                }
            }
            if ($output === null) {
                if ($mode == 'TEST') {
                    $output = MSVC::callTest($t_class_path);
                } else if ($mode == 'TASK') {
                    $output = MSVC::callTask($t_class_path);
                } else if (MSVC::$_is_service) {
                    if (!$_POST && ($json = file_get_contents('php://input'))) {
                        if ($json[0] == '{') {
                            $_POST = json_decode($json, true);
                        }
                    }
                    $tmp_paths = explode('	', trim(str_replace('/', '	', $path))); // 路径
                    $service_name = join('.', $tmp_paths);
                    if (MSVC::$_display_errors && !$_POST && $_GET) {
                        $_POST = $_GET;
                    }

                    $output = MSVC::callService($service_name, $_POST);
                } else {
                    $output = C($path);
                }
            }
            if ($mode[0] != 'T') {
                // 处理后置回调
                if (MSVC::$_config['ONOUT']) {
                    $output = MSVC::_callFuncs(array_reverse(MSVC::$_config['ONOUT']), $output);
                }
            }
            // 输出结果
            if (is_string($output)) {
                //                if (strpos(MSVC::$_path, '.html') !== false) header('Content-Type: text/html; charset=UTF-8');
                echo $output;
            } else if ($output !== null) {
                // header('Content-Type: ' . MSVC::$_config['JSON_CONTENT_TYPE']);
                echo json_encode($output, $mode[0] != 'T' ? JSON_UNESCAPED_UNICODE : JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            if ($mode[0] != 'T') {
                // 记录访问日志
                if (is_array($output)) {
                    if ($output['error']) {
                        MSVC::$_all_errors[] = "[FAILED]	{$output['error']}	{$output['message']}";
                    }
                }
                $server_info = [];
                $server_info['PATH_INFO'] = $_SERVER['PATH_INFO'];
                $server_info['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
                $server_info['HTTP_HOST'] = $_SERVER['HTTP_HOST'];
                $server_info['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];
                $server_info['HTTP_REFERER'] = $_SERVER['HTTP_REFERER'];
                $server_info['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'];
                $server_info['QUERY_STRING'] = $_SERVER['QUERY_STRING'];
                $server_info['ERROR_TIMES'] = count(MSVC::$_all_errors);
                $server_info['REQUEST_TIME_FLOAT'] = $_SERVER['REQUEST_TIME_FLOAT'];
                $server_info['INIT_TIME'] = $call_start_time - $_SERVER['REQUEST_TIME_FLOAT'];
                $server_info['CALL_TIME'] = microtime(true) - $call_start_time;

                $server_info['RESPONSE_CODE'] = http_response_code();
                $server_info['RESPONSE_CONTENT_LENGTH'] = ob_get_length();
                $result = ob_get_contents();
                ob_end_clean();

                MSVC::log([
                    $server_info,
                    $_GET,
                    $_POST,
                    $_COOKIE,
                    $_SESSION,
                    $_FILES,
                    MSVC::$_call_logs,
                ], 'access');
                MSVC::$_call_logs = [];

                return $result;
            }
        } catch (Exception $ex) {

            // 处理后置回调
            if (MSVC::$_mode == 'MS') {
                self::header('Content-Type: ' . MSVC::$_config['JSON_CONTENT_TYPE']);
                echo json_encode(FAILED($ex->getMessage(), $error_code), JSON_UNESCAPED_UNICODE);
            } else if (MSVC::$_mode[0] != 'T') {
                //test
                return $ex->getMessage();

                switch ($error_code) {
                    case 403:
                        self::header('HTTP/1.1 403 Forbidden');
                        break;
                    case 404:
                        self::header('HTTP/1.1 404 Not Found');
                        break;
                    default:
                        self::header('HTTP/1.1 500 Internal Server Error');
                }
            }
            MSVC::_makeError($ex->getMessage() . "\n" . $ex->getTraceAsString());
            if ($mode[0] != 'T') {
                $result = ob_get_contents();
                ob_end_clean();
                return $result;
            }
        }
    }

    /**
     * 按优先级搜索文件
     */
    private static function _search($type, $file, &$site)
    {
        // 搜索控制器
        $search_sites = MSVC::$_exists_site_path ? [
            MSVC::$_site,
        ] : [];
        $search_sites[] = '';
        // TODO 增加 modules
        foreach ($search_sites as $tmp_site) {
            $path = MSVC::$_site_paths[$tmp_site][$type];
            if ($path[0] == '/') {
                $path = MSVC::$ROOT . substr($path, 1);
            } else {
                $path = MSVC::$ROOT . ($tmp_site ? "$tmp_site/" : '') . $path;
            }

            if ($file[0] == '/') {
                $file = substr($file, 1);
            }

            $tmp_path = "$path/$file";
            // echo "[$type] [$tmp_site] $tmp_path <br/>\n";
            if (file_exists($tmp_path)) {
                $site = $tmp_site;
                return $tmp_path;
            }
        }
        $site = null;
        return null;
    }

    /**
     * 调用控制器
     */
    public static function callController($path)
    {
        if (!MSVC::$_config['ENABLE_MSVC_CROSS'] && MSVC::$_sercice_call_counting > 0) {
            throw new Exception("Can't call Controller [$path] in Service!");
        }

        // 初始化应用系统路径
        $tmp_paths = explode('	', trim(str_replace('/', '	', $path))); // 路径
        if (!$tmp_paths || !$tmp_paths[0]) {
            $tmp_paths = [
                'index',
            ];
        }
        // 默认访问 index 类
        if (strstr($tmp_paths[0], '.')) {
            $tmp_paths = [
                'index',
                $tmp_paths[0],
            ];
        }
        // 第一段中有 . 直接将这个作为控制器的方法

        // 根据路径最后一段处理控制器的方法
        $method = $tmp_paths[count($tmp_paths) - 1];
        /*
if (!strstr($method, '.')) {
// 没有 . 表示访问控制器的默认方法
$tmp_paths[] = 'index.html';
$method = $tmp_paths[count($tmp_paths) - 1];
}
 */
        // nginx 会把 PATH_INFO 处理成 index.php
        if ($method == 'index.php') {
            $method = 'index.html';
            $tmp_paths[count($tmp_paths) - 1] = 'index.html';
        }
        if ($method[0] == '_') {
            throw new Exception("No Access for Controller $class_name | $method", 403);
        }

        MSVC::$_path = '/' . join('/', $tmp_paths);

        $method = str_replace([
            '.html',
            '.php',
        ], '', $method); // 如果是 .html 去掉 .html 就是控制器的方法
        $method = str_replace('.', '_', $method); // 如果是其他扩展名 将 . 替换为 _ 就是控制器的完整方法名
        unset($tmp_paths[count($tmp_paths) - 1]); // 去掉方法名，路径就是完整的控制器类路径
        // 取出对应的控制器的类和方法
        $class_path = join('/', $tmp_paths) . '.php';
        $class_name = 'c_' . join('_', $tmp_paths);

        if (MSVC::$_display_errors && isset($_GET['d']) && !$_GET['d']) {
            return "$class_path | $class_name | $method";
            // die("$class_path | $class_name | $method");
        }

        // 定位文件
        $class_file = MSVC::_search('C', $class_path, $site);
        // self::_test_log(MSVC::$_path);
        // self::_test_log($class_file);
        if (!$class_file) {
            throw new Exception("No Controller File $class_path", 404);
        }

        if (!class_exists($class_name)) {
            include $class_file;
        }

        if (!class_exists($class_name)) {
            throw new Exception("No Controller Class $class_file | $class_name", 404);
        }

        return MSVC::_doCall($class_name, $method, null);
    }

    /**
     * 调用标签（控制器）
     */
    public static function callTagController($path)
    {
        if (!MSVC::$_config['ENABLE_MSVC_CROSS'] && MSVC::$_sercice_call_counting > 0) {
            throw new Exception("Can't call Tag Controller [$path] in Service!");
        }

        list($class_name, $method) = explode(':', $path);
        if (!$class_name) {
            $class_name = 'index';
        }

        $method = '_TAG_' . $method;

        $class_path = str_replace('.', '/', $class_name) . '.php';
        $class_name = 'c_' . str_replace('.', '_', $class_name);
        // echo "$class_path | $class_name | $method<br/>\n";

        // 定位文件
        $class_file = MSVC::_search('C', $class_path, $site);
        if (!$class_file) {
            return [];
        }

        if (!class_exists($class_name)) {
            include $class_file;
        }

        if (!class_exists($class_name)) {
            return [];
        }

        return MSVC::_doCall($class_name, $method, null);
    }

    /**
     * 调用 Task
     */
    public static function callTask($path)
    {
        $class_name = 'task_' . str_replace('.', '_', $path);
        $class_path = str_replace('.', '/', $path) . '.php';

        // 定位文件
        $class_file = MSVC::_search('TASK', $class_path, $site);
        if (!$class_file) {
            if (!E("Task file $class_path not exists")) {
                return "Task file $class_path not exists\n";
            }
        }

        if (!class_exists($class_name)) {
            include $class_file;
        }

        if (!class_exists($class_name)) {
            if (!E("Task $path not exists")) {
                return ("Task $path not exists\n");
            }
        }

        return MSVC::_doCall($class_name, 'run', null);
    }

    /**
     * 调用 Test
     */
    public static function callTest($path)
    {
        $pos = strrpos($path, '.');
        if ($pos === false) {
            if (!E("Test $path no method")) {
                return ("Test $path no method\n");
            }
        }

        // 取出对应的控制器的类和方法
        $method = substr($path, $pos + 1);
        if ($method[0] == '_') {
            if (!E("Test $path No Access")) {
                return ("Test $path No Access\n");
            }
        }

        $class_name = substr($path, 0, $pos);

        $class_path = str_replace('.', '/', $class_name) . '.php';
        $class_name = 'test_' . str_replace('.', '_', $class_name);

        // 定位文件
        $class_file = MSVC::_search('TEST', $class_path, $site);
        if (!$class_file) {
            if (!E("Test file $class_path not exists")) {
                return ("Test file $class_path not exists\n");
            }
        }

        if (!class_exists($class_name)) {
            include $class_file;
        }

        if (!class_exists($class_name)) {
            if (!E("Test $path not exists")) {
                return ("Test $path not exists\n");
            }
        }

        return MSVC::_doCall($class_name, $method, null);
    }

    /**
     * 调用服务
     *
     * @param string $service_name
     *            服务名称 package.class.method
     * @param array $args
     *            服务的参数，k-v数组
     */
    private static $_sercice_call_counting = 0;

    public static function callService($service_name, $args)
    {
        try {
            // TODO 判断配置 SERVICE 中 处理远程 Service
            if (MSVC::$_config['SERVICE']) {
                foreach (MSVC::$_config['SERVICE'] as $part => $url) {
                    if (strpos($service_name, $part) === 0) {
                        // 匹配到了，从 远程访问
                        $url .= str_replace('.', '/', substr($service_name, strlen($part)));
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $output = curl_exec($ch);
                        curl_close($ch);
                        $result = json_decode($output, JSON_UNESCAPED_UNICODE);
                        if ($result === null) {
                            MSVC::_makeError('No result from ' . $url);
                            return FAILED('No result from ' . $url, 500);
                        }
                        return $result;
                    }
                }
            }

            // curl .... json_decode
            $pos = strrpos($service_name, '.');
            if ($pos === false) {
                return FAILED('No Access', 403);
            }

            // 取出对应的控制器的类和方法
            $method = substr($service_name, $pos + 1);
            if ($method[0] == '_') {
                return FAILED("No Access", 403);
            }

            $class_name = substr($service_name, 0, $pos);
            $class_path = str_replace('.', '/', $class_name) . '.php';
            $class_name = 's_' . str_replace('.', '_', $class_name);
            if (MSVC::$_display_errors && isset($_GET['d']) && !$_GET['d']) {
                return ("$class_path | $class_name | $method");
            }

            // 定位文件
            $class_file = MSVC::_search('S', $class_path, $site);
            if (!$class_file) {
                throw new Exception("No Service File $class_path", 404);
            }

            if (!class_exists($class_name)) {
                include $class_file;
            }

            if (!class_exists($class_name)) {
                throw new Exception("No Service Class $class_file | $class_name", 404);
            }

            MSVC::$_sercice_call_counting++;
            $result = MSVC::_doCall($class_name, $method, $args);
            MSVC::$_sercice_call_counting--;
            return $result;
        } catch (Exception $ex) {
            // header( 'HTTP/1.1 500 Internal Server Error' );
            MSVC::_makeError($ex->getMessage() . "\n" . $ex->getTraceAsString());
            return FAILED('发生未知错误', 500);
        }
    }

    private static function _doCall($class_name, $method, $args)
    {
        $is_controller = $args === null;
        // 验证参数（基于 _checks 设置自动验证）
        $has_common_checks = property_exists($class_name, '_checks');
        $has_action_checks = property_exists($class_name, '_checks_' . $method);
        if ($has_common_checks || $has_action_checks) {
            if ($has_action_checks) {
                $action_checks_name = '_checks_' . $method;
                $action_checks = $class_name::$$action_checks_name;
            }
            if ($is_controller) {
                $args = array_merge($_GET, $_POST);
            }
            // 控制器同时验证 GET POST 参数
            if (is_array($args)) {
                foreach ($args as $k => $v) {
                    if ($has_action_checks) {
                        $reg = $action_checks[$k];
                    } else {
                        $reg = $class_name::$_checks[$k];
                    }

                    if ($reg && !preg_match($reg, $v)) {
                        MSVC::_makeError("Arg: $k=>$v [$reg] check failed at [$class_name $method]");
                        if ($is_controller) {
                            throw new Exception("Arg $k=>$v check failed", 405);
                        }

                        return FAILED("Arg: $k=>$v [$reg] check failed", 405);
                    }
                }
            }
        }

        $class_obj = new $class_name();

        // 验证参数（调用 _willCall 进行验证） 返回 false 表示拒绝执行， true 表示通过检查，返回其他内容表示直接处理不需要再进行调用（相当于魔术方法）
        if (method_exists($class_obj, '_willCall')) {
            if ($is_controller) {
                $tmp_result = call_user_func([
                    $class_obj,
                    '_willCall',
                ], $method);
            } else {
                $tmp_result = call_user_func([
                    $class_obj,
                    '_willCall',
                ], $method, $args);
            }

            if ($tmp_result === false) {
                if ($is_controller) {
                    throw new Exception("do willCall failed");
                }

                return FAILED("do willCall failed", 500);
            }
            if ($tmp_result !== true && $tmp_result !== null) {
                return $tmp_result;
            }
        }

        // 调用控制器进行处理
        if (!method_exists($class_obj, $method) && !method_exists($class_obj, '__call')) {
            if ($is_controller) {
                throw new Exception("No Method [$class_name $method]", 404);
            }

            return FAILED("No Method [$class_name $method]", 404);
        }

        if ($is_controller) {
            $result = call_user_func([
                $class_obj,
                $method,
            ]);
        } else {
            $result = call_user_func([
                $class_obj,
                $method,
            ], $args);
        }

        // 后置调用（调用 _didCall 进行验证） 返回 false 表示拒绝执行， true 表示通过检查， 返回其他内容表示直接处理不需要再进行调用（相当于魔术方法）
        if (method_exists($class_obj, '_didCall')) {
            if ($is_controller) {
                $tmp_result = call_user_func([
                    $class_obj,
                    '_didCall',
                ], $method, $result);
            } else {
                $tmp_result = call_user_func([
                    $class_obj,
                    '_didCall',
                ], $method, $args, $result);
            }

            if ($tmp_result !== null) {
                return $tmp_result;
            }
        }

        return $result;
    }

    public static function display($view_file, &$data)
    {
        if (is_array($view_file)) {
            $data = $view_file;
            $view_file = null;
        }
        if (!MSVC::$_config['ENABLE_MSVC_CROSS'] && MSVC::$_sercice_call_counting > 0) {
            throw new Exception("Can't display View [$view_file] in Service!");
        }

        if (!$view_file) {
            $view_file = str_replace('index/', '', MSVC::$_path);
        }

        if (is_array($GLOBALS['V'])) {
            extract($GLOBALS['V']);
        }

        extract($data);
        $cahce_file = MSVC::_makeViewFile($view_file);
        ob_start();
        if ($cahce_file) {
            include $cahce_file;
        } else {
            MSVC::_makeError("View file $view_file not exists.");
        }

        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }

    private static function _makeViewFile($view_file)
    {
        $file = MSVC::_search('V', $view_file, $site);
        if (!$file) {
            return;
        }
        // view 不存在
        $cache_file = MSVC::$_config['VIEW_CACHE_PATH'] . MSVC::$_site . "/$view_file.php";
        $cache_info_file = MSVC::$_config['VIEW_CACHE_PATH'] . MSVC::$_site . "/$view_file.info";

        $file_mtime = filemtime($file);
        if (file_exists($cache_info_file) && file_exists($cache_file) && MSVC::$_config['DISABLE_VIEW_CACHE']) {
            $cached_mtime = @file_get_contents($cache_info_file);
            if ($cached_mtime == $file_mtime) {
                return $cache_file;
            }
        }

        $cache_file_path = dirname($cache_file);
        if (!file_exists($cache_file_path)) {
            mkdir($cache_file_path, 0755, true);
        }

        $view_src = file_get_contents($file);

        $view_dst = preg_replace('~<\?.*?\?>~is', '', $view_src);
        $view_dst = preg_replace(MSVC::$_site_view_tags[$site], MSVC::$_view_tag_replaces, $view_dst);
        if (MSVC::$_site_view_replace[$site]) {
            $view_dst = preg_replace(MSVC::$_site_view_replace[$site][0], MSVC::$_site_view_replace[$site][1], $view_dst);
        }

        file_put_contents($cache_file, $view_dst);
        file_put_contents($cache_info_file, $file_mtime);
        return $cache_file;
    }

    private static $_models = [];

    public static function getModel($model_name)
    {
        if (!MSVC::$_config['ENABLE_MSVC_CROSS'] && MSVC::$_sercice_call_counting <= 0) {
            throw new Exception("Can't use Model [$model_name] without Service!");
        }

        $timeout = 3;
        $stringify = false;
        $persistent = false;
        $db = $table = '';
        $dsn = $user = $pwd = $table_prefix = $options = null;
        if (strpos($model_name, '.') !== false) {
            list($db, $table) = explode('.', $model_name);
        }

        if ($db && MSVC::$_config['DB'][$db]) {
            list($dsn, $user, $pwd, $table_prefix, $options) = MSVC::$_config['DB'][$db];
        }

        if (is_array($table_prefix)) {
            $options = $table_prefix;
            $table_prefix = '';
        }
        if (!is_array($options)) {
            $options = null;
        }

        if (!$table_prefix) {
            $table_prefix = '';
        }

        $real_table = $table_prefix . $table;
        $m = static::$_models[$model_name];
        if (!$m) {
            if ($db && $table) {
                $class_name = "m_{$db}_{$table}";
                $class_file = MSVC::_search('M', "$db/$table.php", $site);
                if (!class_exists($class_name) && $class_file) {
                    include $class_file;
                }
            }
            if ($class_file && class_exists($class_name)) {
                $m = new $class_name($db, $real_table, [
                    $dsn,
                    $user,
                    $pwd,
                    $options,
                ], MSVC::$_config['MM'], MSVC::$_config['MM_OPTIONS']);
            } else {
                // 使用默认的Model类
                $m = new Model($db, $real_table, [
                    $dsn,
                    $user,
                    $pwd,
                    $options,
                ], MSVC::$_config['MM'], MSVC::$_config['MM_OPTIONS']);
            }
            static::$_models[$model_name] = $m;
        }
        return $m;
    }

    private static $_has_apc = null;

    private static function _getCache($key, $files = null)
    {
        if (MSVC::$_has_apc === null) {
            MSVC::$_has_apc = function_exists('apc_fetch');
        }

        if (!MSVC::$_has_apc) {
            return null;
        }

        if (!$files) {
            $files = [
                $key,
            ];
        }

        if (!is_array($files)) {
            $files = [
                $files,
            ];
        }

        foreach ($files as $file) {
            $cache_mtime = apc_fetch($key . 'mtime');
            if (file_exists($file) && (!$cache_mtime || intval(@filemtime($file)) != $cache_mtime)) {
                return null;
            }
        }
        return apc_fetch($key);
    }

    private static function _setCache($key, $data, $files = null)
    {
        D($files);
        if (!MSVC::$_has_apc) {
            return;
        }

        if (!$files) {
            $files = [
                $key,
            ];
        }

        if (!is_array($files)) {
            $files = [
                $files,
            ];
        }

        foreach ($files as $file) {
            if (file_exists($file)) {
                apc_store($key . 'mtime', intval(@filemtime($file)));
            }
        }
        apc_store($key, $data);
    }

    private static $_langs = [];
    private static $_cur_lang_set = null;

    public static function getLang($text, $langs = null)
    {
        $text_l = strtolower($text);
        if (!$langs) {
            if (!MSVC::$_cur_lang_set) {
                $al = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
                if (!$al) {
                    return $text;
                }

                MSVC::$_cur_lang_set = $al[2] == '-' ? [
                    substr($al, 0, 5),
                    substr($al, 0, 2),
                ] : [
                    substr($al, 0, 2),
                ];
            }
            $langs = MSVC::$_cur_lang_set;
        } else {
            if (!is_array($langs)) {
                $langs = [
                    $langs,
                ];
            }
        }

        foreach ($langs as $lang) {
            $lang = strtolower($lang);
            $lang_file = MSVC::$_config['LANG_PATH'] . $lang;

            // 尝试从缓存中获取语言数据
            if (!MSVC::$_langs[$lang] && file_exists($lang_file)) {
                MSVC::$_langs[$lang] = MSVC::_getCache($lang_file);
                if (!MSVC::$_langs[$lang]) {
                    MSVC::$_langs[$lang] = [];
                    $fp = fopen($lang_file, 'r');
                    if ($fp) {
                        while ($line = fgets($fp)) {
                            if ($line[0] == '#') {
                                continue;
                            }
                            // #开头为注释
                            list($k, $v) = explode("\t", $line, 2);
                            MSVC::$_langs[$lang][strtolower(trim($k))] = trim($v);
                        }
                        fclose($fp);
                    }
                    MSVC::_setCache($lang_file, MSVC::$_langs[$lang]);
                }
            }
            if (MSVC::$_langs[$lang] && MSVC::$_langs[$lang][$text_l]) {
                return MSVC::$_langs[$lang][$text_l];
            }
        }
        return $text;
    }

    public static function getConfig($name)
    {
        return MSVC::$_config[$name];
    }
}

class Model
{
    private $_read_pdo = null;
    private $_write_pdo = null;
    private $_last_pdo = null;
    private $_mm = null;
    private $_mm_servers = null;
    private $_mm_options = null;
    private $_mm_prefix = '';
    private $_table = '';
    private $_where = ''; // find 的条件
    private $_where_args = []; // find 的条件参数
    private $_join = '';
    private $_join_args = [];
    private $_order = ''; // find 的排序
    private $_group = ''; // find 的分组
    private $_cache_time = -1; // 是否使用缓存
    private $_full_sql = ''; // query 的完整Sql
    private $_full_sql_args = []; // query 的完整Sql参数
    private $_last_sql = ''; // 最后一次执行的Sql
    private $_last_sql_args = []; // 最后一次执行的Sql参数
    private static $_pdo_caches = [];
    private $_write_pdo_info = [];
    private $_read_pdo_infos = [];
    private $_pdo_options = [];

    public function __construct($db, $table, $db_info, $mm_servers, $mm_options)
    {
        $this->_mm_servers = $mm_servers;
        $this->_mm_options = $mm_options;
        $this->_mm_prefix = "{$db}.{$table}_";

        if (!$db_info) {
            return;
        }

        list($dsn, $user, $pwd, $options) = $db_info;
        $this->_write_pdo_info = [
            $dsn,
            $user,
            $pwd,
        ];
        $this->_read_pdo_infos = [
            [
                $dsn,
                $user,
                $pwd,
            ],
        ];
        if (strstr($dsn, '|')) {
            // mysql:host=w.db:3306|w.db:3307;dbname=GY_AUTH
            preg_match('/^(.+=)([\w\.:\|]+)(;.+)$/', $dsn, $m);
            if (count($m) == 4) {
                $hosts = explode('|', $m[2]);
                if (count($hosts) >= 2) {
                    $this->_write_pdo_info = [
                        $m[1] . $hosts[0] . $m[3],
                        $user,
                        $pwd,
                    ];
                    $this->_read_pdo_infos = [];
                    for ($i = count($hosts) - 1; $i >= 1; $i--) {
                        $this->_read_pdo_infos[] = [
                            $m[1] . $hosts[$i] . $m[3],
                            $user,
                            $pwd,
                        ];
                    }
                }
            }
        }
        if (!$options) {
            $options = [
                PDO::ATTR_TIMEOUT => 3,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_PERSISTENT => false,
            ];
        }
        $this->_pdo_options = $options;

        $this->_table = $table;
    }

    private function _initPDO($read_or_write)
    {
        if ($read_or_write == 'write') {
            if ($this->_write_pdo) {
                return $this->_write_pdo;
            }

            list($dsn, $user, $pwd) = $this->_write_pdo_info;
        } else {
            if ($this->_read_pdo) {
                return $this->_read_pdo;
            }

            $r_i = 0;
            $r_num = count($this->_read_pdo_infos);
            if ($r_num > 1) {
                $r_i = rand(0, $r_num - 1);
            }

            list($dsn, $user, $pwd) = $this->_read_pdo_infos[$r_i];
        }

        $pdo_key = "$dsn, $user, $pwd";
        $pdo = Model::$_pdo_caches[$pdo_key];
        if (!$pdo) {
            if ($pwd) {
                $pwd = base64_decode($pwd);
            }

            $pdo = new PDO($dsn, $user, $pwd, $this->_pdo_options);
            if (!$pdo) {
                throw new Exception("No db conntion by [$dsn, $user]");
            }

            Model::$_pdo_caches[$pdo_key] = $pdo;
        }

        if ($read_or_write == 'write') {
            $this->_write_pdo = $pdo;
        } else {
            $this->_read_pdo = $pdo;
        }
        return $pdo;
    }

    private function _initMM()
    {
        if ($this->_mm) {
            return;
        }

        $this->_mm = new Memcached('MM_POOL');
        if ($this->_mm->getServerList()) {
            return;
        }

        if ($this->_mm_options) {
            $this->_mm->setOptions($this->_mm_options);
        }

        if ($this->_mm_servers) {
            $this->_mm->addServers($this->_mm_servers);
        }
    }

    public function get($key)
    {
        if (!$this->_mm) {
            $this->_initMM();
        }

        return $this->_mm->get($this->_mm_prefix . $key);
    }

    public function set($key, $value, $expires = 0)
    {
        if (!$this->_mm) {
            $this->_initMM();
        }

        if ($value === null) {
            return $this->_mm->delete($this->_mm_prefix . $key);
        } else {
            return $this->_mm->set($this->_mm_prefix . $key, $value, $expires);
        }
    }

    public function cache($cache_time)
    {
        $this->_cache_time = $cache_time;
        return $this;
    }

    private function _clear_query()
    {
        $this->_join = '';
        $this->_join_args = [];
        $this->_where = '';
        $this->_where_args = [];
        $this->_order = '';
        $this->_group = '';
        $this->_cache_time = -1;
        $this->_full_sql = '';
        $this->_full_sql_args = [];
    }

    private function _makeWheres($wheres, $args)
    {
        if (is_array($wheres)) // 简单的条件查询
        {
            $keys = [];
            $values = [];
            foreach ($wheres as $key => $value) {
                $key = '`' . str_replace('.', '`.`', $key) . '`';
                if ($value[0] == ':') {
                    $keys[] = "$key=" . substr($value, 1);
                } else if (is_array($value)) {
                    $keys[] = "$key in " . IN($value);
                } else {
                    $keys[] = "$key=?";
                    $values[] = $value;
                }
            }
            $where = join(' and ', $keys);
            $where_args = $values;
        } else {
            $where = $wheres;
            $where_args = is_string($args) ? [
                $args,
            ] : $args;
        }
        return [
            $where,
            $where_args,
        ];
    }

    public function find($wheres = '1', $args = [])
    {
        $this->_clear_query();
        if (!$this->_table) {
            return $this;
        }
        // 未指定表名不允许执行 find

        list($where, $where_args) = $this->_makeWheres($wheres, $args);
        $this->_where = ' where ' . $where;
        $this->_where_args = $where_args;

        return $this;
    }

    public function join($table, $wheres, $args = [])
    {
        if (!$this->_table) {
            return $this;
        }
        // 未指定表名不允许执行 find

        list($where, $where_args) = $this->_makeWheres($wheres, $args);
        $this->_join .= " left join $table on " . $where;
        $this->_join_args = array_merge($this->_join_args, $where_args);

        return $this;
    }

    public function order($orders)
    {
        if (is_array($orders)) {
            $this->_order = ' order by ' . join(',', $orders);
        } else {
            $this->_order = ' order by ' . $orders;
        }

        return $this;
    }

    public function group($groups)
    {
        if (is_array($groups)) {
            $this->_group = ' group by ' . join(',', $groups);
        } else {
            $this->_group = ' group by ' . $groups;
        }

        return $this;
    }

    public function limit($num)
    {
        $this->_group = ' limit ' . $num;
        return $this;
    }

    public function query($sql, $args = [])
    {
        $this->_clear_query();
        $this->_full_sql = $sql;
        $this->_full_sql_args = is_string($args) ? [
            $args,
        ] : $args;
        return $this;
    }

    private function _exec($sql, $args = [], $return_q = false)
    {
        try {
            $start_time = microtime(true);
            $sql_l = strtolower($sql);
            $sql_action = substr($sql_l, 0, 7);
            $is_read = ($sql_action == 'select ' && !strstr($sql_l, ' into '));
            $this->_last_pdo = $this->_initPDO($is_read ? 'read' : 'write');

            $q = $this->_last_pdo->prepare($this->_last_sql);
            $isok = $q->execute($this->_last_sql_args);
            MSVC::$_call_logs[] = [
                'M',
                $sql,
                microtime(true) - $start_time,
                $args,
                '',
            ];
            return $return_q ? $q : $isok;
        } catch (Exception $ex) {
            MSVC::$_call_logs[] = [
                'M',
                $sql,
                microtime(true) - $start_time,
                $args,
                $ex->getMessage(),
            ];
            throw new Exception($ex->getMessage() . "\n------------------------------\n" . $this->getLastSql() . "\n------------------------------");
        }
    }

    private function _doSelect($fields = null, $make_first = false)
    {
        if ($this->_full_sql) {
            $this->_last_sql = $this->_full_sql;
            $this->_last_sql_args = $this->_full_sql_args;
        } else if ($this->_table) {
            if (is_array($fields)) {
                $fields = '`' . join('`,`', $fields) . '`';
            }

            if (!$fields) {
                $fields = '*';
            }

            $sql = "select $fields from `" . $this->_table . '`' . $this->_join . $this->_where . $this->_order . $this->_group;
            if ($make_first && !stristr($sql, 'limit')) {
                $sql .= ' limit 1';
            }

            $this->_last_sql = $sql;
            $this->_last_sql_args = $this->_join_args ? array_merge($this->_join_args, $this->_where_args) : $this->_where_args;
        } else {
            return null;
        }
        if ($this->_cache_time < 0) {
            return $this->_exec($this->_last_sql, $this->_last_sql_args, true);
        }

        // 使用缓存
        $cache_sql_key = preg_replace(
            '/[^a-zA-Z0-9\x{4e00}-\x{9fff}]/u',
            '',
            str_replace([
                'select',
                'from',
                'where',
                'orderby',
                'groupby',
                'leftjoin',
            ], '', strtolower($this->_last_sql . join('', array_values($this->_last_sql_args))))
        );
        if ($cache_sql_key > 250) {
            $cache_sql_key = md5($cache_sql_key) . substr($cache_sql_key, 0, 218 - strlen($this->_mm_prefix));
        }

        $result = $this->get($cache_sql_key);
        if (!$result) {
            $result = $this->_exec($this->_last_sql, $this->_last_sql_args, true);
            $this->set($cache_sql_key, $result, $this->_cache_time);
        }
        return $result;
    }

    public function first($fields = null, $return_col1 = false)
    {
        $q = $this->_doSelect($fields);
        $r = $q->fetch($return_col1 ? PDO::FETCH_NUM : PDO::FETCH_ASSOC);

        if ($return_col1 && $r) {
            return $r[0];
        } else {
            return $r;
        }
    }

    public function count()
    {
        return $this->first("count(*)", true);
    }

    public function all($fields = null, $is_num_array = false, $return_col1 = false)
    {
        $q = $this->_doSelect($fields);
        if ($return_col1) {
            $results = [];
            $all = $q->fetchAll(PDO::FETCH_NUM);
            foreach ($all as $r) {
                $results[] = $r[0];
            }
            return $results;
        }
        return $q->fetchAll($is_num_array ? PDO::FETCH_NUM : PDO::FETCH_ASSOC);
    }

    public function kv($fields = null, $id_field = null)
    {
        $q = $this->_doSelect($fields);
        $all_by_id = array();
        $fetch_type = !$id_field ? PDO::FETCH_NUM : PDO::FETCH_ASSOC;
        while ($r = $q->fetch($fetch_type)) {
            if (!$id_field) {
                $all_by_id[$r[0]] = $r[1];
            } else {
                $all_by_id[$r[$id_field]] = $r;
            }
        }
        return $all_by_id;
    }

    public function _makeTree($datas, $make_func, $id_field, $parent_field, $children_field, $parent, $level)
    {
        $list = [];
        foreach ($datas as $node) {
            if ($node[$parent_field] != $parent) {
                continue;
            }

            if ($make_func && is_callable($make_func) && $new_node = call_user_func($make_func, $node, $level)) {
                $node = $new_node;
            }

            $node[$children_field] = $this->_makeTree($datas, $make_func, $id_field, $parent_field, $children_field, $node[$id_field], $level + 1);
            $list[] = $node;
        }
        return $list ? $list : null;
    }

    public function tree($fields = null, $make_func = null, $id_field = 'id', $parent_field = 'parent', $children_field = 'children', $parent = 0)
    {
        $datas = $this->all($fields);
        return $this->_makeTree($datas, $make_func, $id_field, $parent_field, $children_field, $parent, 0);
    }

    public function insert($data, $use_replace = false)
    {
        if (!$this->_table || !$data || !is_array($data)) {
            return false;
        }

        $key_list_a = [];
        $var_list_a = [];
        $values = [];
        foreach ($data as $key => $value) {
            if ($value[0] == ':') {
                $key_list_a[] = $key;
                $var_list_a[] = substr($value, 1);
            } else {
                $key_list_a[] = $key;
                $var_list_a[] = '?';
                $values[] = $value;
            }
        }
        $key_list = '`' . join('`,`', $key_list_a) . '`';
        $var_list = join(',', $var_list_a);
        $operate = $use_replace ? 'replace' : 'insert';

        $sql = "$operate into `" . $this->_table . "` ( $key_list ) values ( $var_list )";
        $this->_last_sql = $sql;
        $this->_last_sql_args = $values;
        return $this->_exec($sql, $values);
    }

    public function replace($data)
    {
        return $this->insert($data, true);
    }

    public function update($data)
    {
        if (!$this->_table || !$data || !is_array($data) || !$this->_where) {
            return false;
        }

        $direct_vars = [];
        foreach ($data as $key => $value) {
            if ($value[0] == ':') {
                $direct_vars[] = "`$key`=" . substr($value, 1);
                unset($data[$key]);
            }
        }
        $key_list = '';
        $values = [];
        if ($data) {
            $key_list = '`' . join('`=?,`', array_keys($data)) . '`=?';
            $values = array_values($data);
        }
        if ($direct_vars) {
            $key_list .= ($key_list ? ',' : '') . join(',', $direct_vars);
        }
        if ($this->_where_args) {
            $values = array_merge($values, $this->_where_args);
        }

        $sql = "update `" . $this->_table . "` set $key_list" . $this->_where;
        $this->_last_sql = $sql;
        $this->_last_sql_args = $values;
        return $this->_exec($sql, $values);
    }

    public function delete()
    {
        if (!$this->_table || !$this->_where) {
            return false;
        }

        $sql = "delete from `" . $this->_table . "`" . $this->_where;
        return $this->_exec($sql, $this->_where_args);
    }

    public function begin()
    {
        if (!$this->_write_pdo) {
            $this->_initPDO('write');
        }

        $this->_write_pdo->beginTransaction();
        return $this;
    }

    public function end($isok = true)
    {
        if (!$this->_write_pdo) {
            $this->_initPDO('write');
        }

        if ($isok) {
            $this->_write_pdo->commit();
        } else {
            $this->_write_pdo->rollBack();
        }

        return $this;
    }

    public function getLastInsertID()
    {
        if (!$this->_write_pdo) {
            $this->_initPDO('write');
        }

        return $this->_write_pdo->lastInsertId();
    }

    public function getLastSql()
    {
        return $this->_last_sql . ' [' . join(', ', $this->_last_sql_args) . ']';
    }

    public function getLastErrorCode()
    {
        return $this->_last_pdo ? $this->_last_pdo->errorCode() : 0;
    }

    public function getLastError()
    {
        return $this->_last_pdo ? $this->_last_pdo->errorInfo() : '';
    }
}

abstract class Task
{
    protected $task_name = '';
    protected $info = [];

    public function _willCall()
    {
        $this->task_name = str_replace('_', '.', str_replace('task_', '', get_called_class()));
        $this->info = $this->info();
        $now_time = intval(date('Hi'));
        if ($this->info['start'] && $now_time < $this->info['start']) {
            if (!$this->log("[NORUN]	$now_time < " . $this->info['start'])) {
                return ("[NORUN]	$now_time < " . $this->info['start'] . "\n");
            }
        }

        if ($this->info['stop'] && $now_time > $this->info['stop']) {
            if (!$this->log("[NORUN]	$now_time > " . $this->info['stop'])) {
                return ("[NORUN]	$now_time > " . $this->info['stop'] . "\n");
            }
        }

        $running_num = trim(`ps ax | grep {$this->task_name} | grep -v grep | wc -l`);
        if ($running_num > 1) {
            if (!$this->log("[RUNNING]	$running_num {$this->task_name} is running")) {
                return ("[RUNNING]	$running_num {$this->task_name} is running\n");
            }
        }

        set_time_limit($this->info['timeout'] ?: 86400);
        $this->log('[START]');
    }

    abstract protected function info();

    abstract public function run();

    public function log($message)
    {
        MSVC::log($message, 'task/' . $this->task_name);
    }

    public function _didCall()
    {
        $this->log('[STOP]');
    }
}

/**
 * 启动 MSVC 框架
 *
 * @param string $root_path
 *            入口文件相对于站点的路径，或一个绝对路径表示站点根目录，多数情况都是 ..
 */
function MSVC($root_path = '..')
{
    MSVC::init($root_path);
    echo MSVC::start('MSVC');
}
/**
 * 启动 MVC 框架
 *
 * @param string $root_path
 *            入口文件相对于站点的路径，或一个绝对路径表示站点根目录，多数情况都是 ..
 */
function MVC($root_path = '..')
{
    MSVC::init($root_path);
    echo MSVC::start('MVC');
}

/**
 * 启动 MS 框架，只对外提供 Service 服务，忽略 V 和 C 层
 *
 * @param string $root_path
 *            入口文件相对于站点的路径，或一个绝对路径表示站点根目录，多数情况都是 ..
 */
function MS($root_path = '..')
{
    MSVC::init($root_path);
    echo MSVC::start('MS');
}

/**
 * 调用 Model，默认只允许 Service 调用 Model，想要在 Controller 直接调用 Model 需要打开配置 ENABLE_MSVC_CROSS = true
 *
 * @param string $model_name
 *            库别名.表名，只有一个库时可以只写表名
 */
function M($model_name = '')
{
    return MSVC::getModel($model_name);
}

/**
 * 调用 Service，应当以 OK 或 FAILED 函数构造一个结果进行输出
 *
 * @param string $service_name
 *            服务名（包名.类名.方法名），用 . 连接
 * @param array $args
 *            Key-Value 数组，所有参数以这种方式传递给 Service，远程调用接口以 POST 或者 JSON(顶层为Key-Value格式) 传递参数
 * @return array <string , any>，固定返回的数据： code=>int 200 表示没有错误，非0表示错误代码，message
 */
function S($service_name, $args = [])
{
    $start_time = microtime(true);
    $r = MSVC::callService($service_name, $args);
    MSVC::$_call_logs[] = [
        'S',
        $service_name,
        microtime(true) - $start_time,
        $args,
        $r['code'],
    ];
    return $r;
}

/**
 * * 调用 View 显示一个页面 * @param string $view_file View文件的相对路径
 *
 * @param array $data
 *            要跟界面结合的数据，如果是列表使用数组，多重嵌套的列表使用嵌套的数组 * @return string
 */
function V($view_file = null, $data = [])
{
    $start_time = microtime(true);
    $r = MSVC::display($view_file, $data);
    MSVC::$_call_logs[] = [
        'V',
        $view_file,
        microtime(true) - $start_time,
    ];
    return $r;
}

/**
 * * 调用 Controller 处理一个页面，可以在控制其中递归调用实现页面跳转 * @param
 * string $path 页面的相对路径 * @return string or array 返回字符串表示输出页面，返回对象以 JSON
 * 格式输出数据
 */
function C($path)
{
    $start_time = microtime(true);
    $r = MSVC::callController($path);
    MSVC::$_call_logs[] = [
        'C',
        $path,
        microtime(true) - $start_time,
    ];
    return $r;
}

/**
 * *
 * 调用语言处理函数，将一段文本转换成对应的语言文本 * @param string $text 要转换的文本 * @return string
 * 对应的语言文本
 */
function L($text, $langs = null)
{
    return MSVC::getLang($text, $langs);
}

/**
 * * 记录错误信息 * @param string $message 错误信息
 */
function E($message)
{
    MSVC::log($message, 'code');
}

/**
 * * 记录错误信息
 *
 * @param string $message
 */
function I($message)
{
    MSVC::log($message, 'info');
}

/**
 * * 记录错误信息 * @param string $message 错误信息
 */
function D($message)
{
    MSVC::log($message, 'debug');
}

/**
 * * 打印内容后立刻结束 * @param string $message 错误信息
 */
function P($message)
{
    echo "\n";
    if (is_string($message)) {
        echo $message;
    } else {
        echo json_encode($message, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    echo "\n";
    //exit;
}

/**
 * *
 * 构造一个成功的标准结果，code 为 200 * @param array $obj 要返回的数据 * @return array
 * 加上标准输出参数 code、message 之后的结果（Key-Value格式）
 */
function OK($data = null)
{
    return [
        'code' => 200,
        'message' => 'OK',
        'data' => $data,
    ];
}

/**
 * * 构造一个处理失败的标准结果，code 如果没有指定 默认为 500 * @param
 * array $obj 要返回的数据 * @return array 加上标准输出参数 code、message
 * 之后的结果（Key-Value格式）
 */
function FAILED($message, $code = 500, $data = null)
{
    return [
        'code' => $code,
        'message' => $message,
        'data' => $data,
    ];
}

/**
 * * 生成 in sql
 * 语句，对所有成员进行 mysql_escape_string * @param array $values 要生成 in sql 语句的数据 *
 *
 * @return array
 */
function IN($values)
{
    if (!is_array($values)) {
        return '()';
    }

    foreach ($values as &$v) {
        $v = addslashes($v);
    }

    return "('" . join("','", $values) . "')";
}

/**
 * 获取配置
 */
function DATAPATH($path)
{
    $new_path = MSVC::getConfig('DATA_PATH') . $path;
    if ($new_path[strlen($new_path) - 1] != '/') {
        $new_path .= '/';
    }

    if (!file_exists($new_path)) {
        mkdir($new_path, 0755, true);
    }

    return $new_path;
}

/*
 * ------------------------------ 启动处理
 * -------------------------------
 */
// if( PHP_SAPI == 'cli' )
// {
//     if( $argc < 2 ) die( "Usage:\n php index.php TASK package.taskclass args\n php index.php TEST package.class.testaction args\n" );
//     $mode = $argv[1];
//     if( in_array( $mode, [
//         'TEST',
//         'TASK'
//     ] ) ) {
//         MSVC::init( '..' );
//         echo MSVC::start( $mode );
//     }
// }
// else {
//     MSVC( '..' );
// }
