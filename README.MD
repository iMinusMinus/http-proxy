# Apache httpd代理转发

## 安装

+ Windows

   ```cmd
   # 推荐使用Apache Haus(https://www.apachehaus.com/cgi-bin/download.plx)
   # 解压即可
   ```

+ Linux

   ```shell
   # 1. 替换为国内源，加速
   # 1.a kali
   # sudo vi /etc/apt/source.list
   # deb https://mirrors.ustc.edu.cn/kali kali-rolling main non-free contrib
   ## deb-src https://mirrors.ustc.edu.cn/kali kali-rolling main non-free contrib
   # 1.b debian
   # sudo sed -i 's/deb.debian.org/mirrors.ustc.edu.cn/g' /etc/apt/sources.list
   # 1.c CentOS
   # sudo sed -e 's|^mirrorlist=|#mirrorlist=|g' -e 's|^#baseurl=http://mirror.centos.org/centos|baseurl=https://mirrors.ustc.edu.cn/centos|g' -i.bak /etc/yum.repos.d/CentOS-Base.repo
   # 除中科大源外，知名的还有https://mirrors.tuna.tsinghua.edu.cn、https://developer.aliyun.com/mirror/、http://mirrors.163.com/等
   # 2. 更新索引
   # sudo apt-get update
   # 3. 安装
   sudo apt install apache2
   ```

## 配置

   在主配置文件（如httpd.conf）有配置AccessFileName指令（默认为".htaccess"，后续以此文件名为例），用于开放部分配置权限给虚拟主机或目录所有者。
   同时，也设置了该文件外部不可访问，类似如下内容：

   ```apacheconf
   AccessFileName .htaccess
   <FilesMatch "^\.ht">
       Require all denied
   </FilesMatch>
   ```

+ Windows

   ```cmd
   # Windows下文件目录一般为：
   # bin
   # cgi-bin
   # conf
   # conf/charset.conv
   # conf/httpd.conf
   # conf/magic
   # conf/mime.types
   # conf/openssl.cnf
   # conf/extra
   # conf/extra/httpd_{module}.conf
   # conf/extra/vhosts.conf
   # conf/original
   # conf/ssl
   # conf/ssl/server.crt
   # conf/ssl/server.key
   # htdocs
   # modules
   # modules/mod_{module}.so
   #
   # httpd.conf为主配置文件，包含模块加载和判断模块存在的条件指令、监听端口、CGI、错误日志及引入其他模块配置、虚拟主机配置
   ```

+ Linux

   ```shell
   # 不同平台，文件位置可能不同。如某些Linux(Debian)：
   # /etc/apache2/apache2.conf
   # /etc/apache2/conf-available
   # /etc/apache2/conf-available/charset.conf
   # /etc/apache2/conf-available/localized-error-pages.conf
   # /etc/apache2/conf-available/other-vhosts-access-log.conf
   # /etc/apache2/conf-available/security.conf
   # /etc/apache2/conf-available/serve-cgi-bin.conf
   # /etc/apache2/conf-enabled
   # /etc/apache2/envvars
   # /etc/apache2/magic
   # /etc/apache2/mods-available
   # /etc/apache2/mods-available/{module_name}.conf
   # /etc/apache2/mods-available/{module_name}.load
   # /etc/apache2/mods-enabled
   # /etc/apache2/ports.conf
   # /etc/apache2/sites-available
   # /etc/apache2/sites-available/000-default.conf
   # /etc/apache2/sites-available/default-ssl.conf
   # /etc/apache2/sites-enabled
   #
   # apache2.conf会引入"mods-enabled/*.load"、"mods-enabled/*.conf"、"ports.conf"、"conf-enabled/*.conf"、"sites-enabled/*.conf"
   # "*-enabled"目录下的文件是"*-available"下文件的链接
   # "mods-enabled/*.load"主要内容为加载模块：LoadModule {module_name}_module /usr/lib/apache2/modules/{module_file_name}.so
   # "mods-enabled/*.conf"主要内容为当模块存在时进行配置：<IfModule {module_name}_module></IfModule>
   # "ports.conf"主要内容为设置监听端口(允许内嵌在模块判断)：Listen 443
   # "conf-enabled/*.conf"包含字符集、错误页、日志文件、安全、CGI等全局配置
   # "sites-enabled/*.conf"包含虚拟主机配置
   ```

## 反向代理  

   可以通过phpinfo显示出配置的已加载apache模块来判断ProxyPass、RewriteRule等指令是否可用。（搜索"Loaded Modules"）

   1. ProxyPass

      此指令不可在.htaccess文件使用，且需要加载mod_proxy、mod_proxy_http、mod_proxy_http2（转发给HTTP2协议才需要）。

      ```shell
      # 允许使用ProxyPass指令
      # LoadModule proxy_module modules/mod_proxy.so
      cd /etc/apache2/mods-enabled
      sudo ln -s ../mods-available/proxy.load proxy.load
      # 代理协议，否则报AH01144
      # LoadModule proxy_http_module modules/mod_proxy_http.so
      sudo ln -s ../mods-available/proxy_http.load proxy_http.load
      ```

      然后设置转发规则（可参考本项目的"apache2/mods-available/proxy.conf"）。接着启动：

      ```shell
      sudo apachectl start
      # 停止
      sudo apachectl stop
      # curl -G http://localhost:80/iminusminus/docs 则会转发到 http://iminusminus.github.io/docs。但有可能被服务端识别，返回非正常信息
      ```

  2. RewriteRule

     此指令即可在conf文件，也可在.htaccess文件进行配置，需要加载mod_rewrite。

     ```shell
     # LoadModule rewrite_module modules/mod_rewrite.so
     sudo ln -s ../mods-available/rewrite.load rewrite.load
     ```

     一般虚拟主机服务商不会开放conf文件配置权限，但会允许虚拟主机通过.htaccess覆盖部分配置。

     .htaccess需要httpd递归检索目录，且每次请求都会加载，性能较低（好处是修改后无需重启，且个性化能力强）。

     ```.htaccess
     # 优先考虑Redirect/Alias/ProxyPass指令，仅在没有*.conf权限时使用。（Directory节等效)
     # https://httpd.apache.org/docs/2.4/mod/core.html#allowoverride
     # 确保*.conf中当前目录的.htaccess生效：
     # <Directory "${SRVROOT}/htdocs">
     #     Options FollowSymLinks
     #     AllowOverride all
     # 或
     #     AllowOverride FileInfo=RewriteEngine,RewriteOptions,RewriteBase,RewriteCond,RewriteRule Indexes
     # </Directory>
     # AllowOverride默认值为None，需要修改
     ```
     参考文件为本项目的iminusminus.github.io/htdocs/.htaccess

  3. php

     php支持三种方式与Apache结合：Module、CGI、FastCGI。

     当能够正常运行php时，可创建php脚本，接受请求，使用curl将请求转发给目标服务器。

     ```shell
     # 安装php，当apache2存在时，自动将libphp8.1.so安装到apache2的模块目录
     sudo apt install php8.1
     # php编译时使用"--with-curl=$libcurl_path"参数，或安装
     sudo apt install php8.1-curl
     # 安装完成后，可通过phpinfo输出，查看"cURL support"是否为"enabled"
     ```

     + Module

       ```shell
       # php7
       # Linux: LoadModule php7_module modules/libphp7.so
       # Windows: LoadModule php7_module "C:/Program Files/php/php7apache2_4.dll"
       # php8
       # LoadModule php_module modules/libphp.so
       #
       # Linux下安装完成后，可以在配置目录下看到"php8.1.load"和"php8.1.conf"，并已链接完毕
       #
       # 在php8.1.conf文件有类似如下内容：
       #<FilesMatch \.php$>
       #    SetHandler application/x-httpd-php
       #</FilesMatch>
       #
       # 然后为httpd提供php.ini文件
       # Windows：
       # PHPIniDir "C:/Program Files/php"
       # Linux：文件自动安装到"/etc/php/8.1/apache2/php.ini"，无需额外处理
       #
       sudo apachectl stop
       sudo apachectl start
       ```

       创建测试用php文件，类似内容如下：

       ```php
       <?php
       phpinfo();

       ```

     + CGI
 
       CGI定义了web服务器与动态内容生成程序（CGI程序或CGI脚本）交互的标准方式。

       mod_cgi模块会为每个请求fork出新进程。

       为避免执行高昂的fork操作，mod_cgid模块作为守护进程，负责fork子进程来执行CGI脚本，与httpd通过unix domain socket通信。

       ```shell
       # Windows版本cgi模块一般已默认加载
       # LoadModule cgi_module modules/mod_cgi.so
       # Linux既可以使用cgi，也可以使用cgid（推荐）
       sudo ln -s ../mods-available/cgid.load cgid.load
       sudo ln -s ../mods-available/cgid.conf cgid.conf
       # 在"conf-enabled/serve-cgi-bin.conf"有类似如下内容：
       # <IfModule mod_alias.c>
       #     <IfModule mod_cgi.c>
       #         Define ENABLE_USR_LIB_CGI_BIN
       #     </IfModule>
       #     <IfModule mod_cgid.c>
       #         Define ENABLE_USR_LIB_CGI_BIN
       #     </IfModule>
       #     <IfDefine ENABLE_USR_LIB_CGI_BIN>
       #         ScriptAlias /cgi-bin/ /usr/lib/cgi-bin/
       #         <Directory "/usr/lib/cgi-bin">
       #             AllowOverride None
       #             Options +ExecCGI -MultiViews +SymLinksIfOwnerMatch
       #             Require all granted
       #         </Directory>
       #     </IfDefine>
       # </IfModule>
       #
       # 或直接设置目录为CGI可执行：
       # <Directory "/cgi/Outside/ScriptAlias">
       #     Options +ExecCGI
       #     AddHandler cgi-script .php
       #     或
       #     SetHandler cgi-script
       # </Directory>
       ```

       创建测试用php文件（必须首行用"#!"指定脚本执行程序，如果是Windows系统，需空一行再开始php脚本）：

       ```php
       #!/usr/bin/php
       
       <?php
       phpinfo();
       
       ```

     + FastCGI

       每次创建请求这种操作仍影响效率，mod_fcgid将执行CGI脚本的进程池化。

       ```shell
       # Windows下载解压，将”mod_fcgid.so“文件放到”modules“目录，”httpd-fcgid.conf“文件放到"conf/extra"目录
       # 取消httpd.conf引入fcgid的注释，并编辑"conf/extra/httpd-fcgid.conf"，类似内容如下：
       # LoadModule fcgid_module modules/mod_fcgid.so
       # <IfModule fcgid_module>
       #     # Fast CGI module Settings (PHP 7)
       #     FcgidInitialEnv PHPRC "C:/Program Files/php"
       #     AddHandler fcgid-script .php
       #     FcgidWrapper "C:/Program Files/php/php-cgi.exe" .php
       # </IfModule>
       #
       sudo apt install libapache2-mod-fcgid
       # Linux安装完后已经自动链接好
       # fcgid.load有类似如下内容：
       # LoadModule fcgid_module modules/mod_fcgid.so
       # fcgid.conf有类似如下内容（默认处理".fcgi"文件，修改成处理".php"文件）：
       # <IfModule mod_fcgid.c>
       #     FcgidConnectTimeout 20
       # 
       #     <IfModule mod_mime.c>
       #         AddHandler fcgid-script .php
       #     </IfModule>
       # </IfModule>
       ```

       一般虚拟主机服务商为我们提供了虚拟主机，实例如下：

       ```apacheconf
       # Windows系统，请配置于"conf/extra/httpd-vhosts.conf"
       # Debian系发行版，请于"/etc/apache2/sites-available"目录新增配置文件（如iminusminus.github.io.conf）
       # cd /etc/apache2/sites-enabled && sudo ln -s ../sites-available/iminusminus.github.io.conf iminusminus.github.io.conf
       <Directory /home/vol1024_6/byethost7.com>
           Options FollowSymLinks ExecCGI
           AllowOverride All
           Require all granted
       </Directory>
       <VirtualHost _default_:443>
           ServerAdmin mean.leung@outlook.com
           DocumentRoot "/home/vol1024_6/byethost7.com/b7_10242048"
           ServerName iminusminus.github.io
           SSLEngine on
           SSLCertificateFile /etc/ssl/certs/ssl-cert-snakeoil.pem
           SSLCertificateKeyFile /etc/ssl/private/ssl-cert-snakeoil.key
       </VirtualHost>
       ```

       创建测试用php文件，类似内容如下：

       ```php
       <?php
       phpinfo();
       
       ```

## 参考

1. [Downloading Apache for Windows](https://httpd.apache.org/docs/current/platform/windows.html#down)
2. [mod](https://httpd.apache.org/docs/2.4/mod/)
3. [mod_proxy](https://httpd.apache.org/docs/2.4/mod/mod_proxy.html#proxypass)
4. [rewrite](https://httpd.apache.org/docs/2.4/rewrite/)
5. [mod_rewrite](https://httpd.apache.org/docs/2.4/mod/mod_rewrite.html)
6. [override](https://httpd.apache.org/docs/2.4/mod/core.html#allowoverride)
7. [apache2 php](https://www.php.net/manual/zh/install.windows.apache2.php)
8. [cgi](https://httpd.apache.org/docs/2.4/howto/cgi.html)
9. [mod_cgid](https://httpd.apache.org/docs/2.4/mod/mod_cgid.html)
10. [mod_fcgid](https://httpd.apache.org/mod_fcgid/mod/mod_fcgid.html)
11. [vhosts](https://httpd.apache.org/docs/2.4/vhosts/)
