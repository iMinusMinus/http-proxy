<?php

// $_REQUEST包含了$_GET，$_POST和$_COOKIE的数组
// $_SESSION

// 通过URL参数（又叫query string）传递给当前脚本的变量的数组。 注意：该数组不仅仅对method为GET的请求生效，而是会针对所有带query string的请求
// $_GET

// 当HTTP POST请求的Content-Type是application/x-www-form-urlencoded或multipart/form-data时，会将变量以关联数组形式传入当前脚本
// $_POST

// 通过HTTP Cookies方式传递给当前脚本的变量的数组
// $_COOKIE
$proxy_pass = "";
$debug = True;
$db_url = "sql202.byethost7.com";
$db_name = "";
$db_user = "";
$db_password = "";

function proxy() {
    global $db_url, $db_name, $db_user, $db_password;
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['PATH_INFO']; // $_SERVER['REQUEST_URI'];
    $query_string = $_SERVER['QUERY_STRING']; // or use http_build_query($_GET)
    $header = getallheaders();
    $ip = $_SERVER['REMOTE_ADDR'];
    $body = $GLOBALS['HTTP_RAW_POST_DATA'];
    if(empty($body)) {
        $body = file_get_contents('php://input');
    }
    if($GLOBALS['debug']) {
        try {
            $con = new PDO("mysql:host=$db_url;dbname=$db_name", $db_user, $db_password);
            $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $con->beginTransaction();
            $sql = "INSERT INTO http_log(REQUEST_METHOD, REQUEST_PATH, REQUEST_QUERY_STRING, REQUEST_HEADER, REQUEST_BODY) VALUES (:method, :path, :query_string, :header, :body)";
            $stmt = $con->prepare($sql);
            // https://dev.mysql.com/doc/refman/8.0/en/char.html
            // 超出768字节的不会和行数据放在一起
            $path_seg = substr($path, 0, min(strlen($path), 768));
            $qs_seg = $query_string;
            if (!empty($query_string)) {
                $qs_seg = substr($query_string, 0, min(strlen($query_string), 768));
            }
            $header_seg = '';
            foreach ($header as $key=>$value) {
                $header_seg = $header_seg . $key . "=" . $value . "\n"; // http_build_query($header);
                $header_seg = substr($header_seg, 0, min(strlen($header_seg), 192));
            }
            $body_seg = $body;
            if (!empty($body)) {
                $body_seg = substr($body, 0, min(strlen($body), 192)); // 可能存在非ASCII字符
            }
            // $stmt->bindParam(":method", $method, PDO::PARAM_STR); $stmt->bindParam(1, $method, PDO::PARAM_STR)
            $stmt->execute(array(":method" => $method, ":path" => $path_seg, ":query_string" => $qs_seg, ":header" => $header_seg, ":body" => $body_seg));
            unset($stmt);
            $GLOBALS['http_log_id'] = $con->lastInsertId();
            $con->commit();
            unset($con);
        } catch (Exception $e) {
            echo $e;
        }
    }
    curl($method, $path, $query_string, $header, $body);
}

function header_function($ch, $header){
    header($header); // https://www.php.net/manual/zh/function.header
    return strlen($header);
}
function write_function($ch, $body){
    echo $body;
    return strlen($body);
}
function curl($method, $path, $query_string, $header, $body) {
    // https://www.php.net/manual/zh/function.curl-setopt.php
    if(empty($query_string)) {
        $qs = "";
    } else {
        $qs = "?" . $query_string;
    }
    $curl_opts = array(
        // CURLOPT_USERAGENT => "", // 在HTTP请求中包含一个"User-Agent: "头的字符串
        CURLOPT_URL => $GLOBALS['proxy_pass'] . $path . $qs, // 需要获取的 URL 地址，也可以在curl_init() 初始化会话的时候
        CURLOPT_CONNECTTIMEOUT => 3, // 在尝试连接时等待的秒数
        CURLOPT_TIMEOUT => 5, // 允许 cURL 函数执行的最长秒数
        CURLOPT_AUTOREFERER => true, // 根据 Location: 重定向时，自动设置 header 中的Referer:信息
        CURLOPT_FOLLOWLOCATION => true, // 将会根据服务器返回 HTTP 头中的 "Location: " 重定向
        CURLOPT_MAXREDIRS => 5, // 指定最多的 HTTP 重定向次数
        CURLOPT_RETURNTRANSFER => true, // 将curl_exec()获取的信息以字符串返回，而不是直接输出
        CURLOPT_HEADERFUNCTION => 'header_function', // 设置一个回调函数，这个函数有两个参数，第一个是cURL的资源句柄，第二个是输出的 header 数据
        CURLOPT_WRITEFUNCTION => 'write_function', // 回调函数名。该函数应接受两个参数。第一个是 cURL resource；第二个是要写入的数据字符串
        CURLOPT_CUSTOMREQUEST => $method, // HTTP 请求时，使用自定义的 Method 来代替"GET"或"HEAD"
        CURLOPT_SSL_VERIFYPEER => false, // cURL 验证对等证书
        CURLOPT_HTTPHEADER => $header, // 设置 HTTP 头字段的数组
        CURLOPT_SSL_VERIFYHOST => 0 // 检查服务器SSL证书
    );

    if ($method == 'POST')//如果是POST就读取POST信息,不支持
    {
        $curl_opts[CURLOPT_POST] = true;
        $curl_opts[CURLOPT_POSTFIELDS] = $body;
    }
    $curl = curl_init();
    curl_setopt_array ($curl, $curl_opts);
    curl_exec ($curl);
    curl_close($curl);
    unset($curl);
}

proxy();